/**
 * @fileoverview AI Route Request Endpoint for Creator AI Proxy
 * @module api/ai/routeRequest
 *
 * @description
 * POST /api/ai/route-request
 *
 * Routes AI generation requests to the optimal provider based on task type,
 * with automatic fallback when primary providers fail.
 *
 * Requires: Bearer token authentication (site_token)
 */

import { onRequest } from "firebase-functions/v2/https";
import { Request, Response } from "express";
import { v4 as uuidv4 } from "uuid";

import { jwtSecret, openaiApiKey, geminiApiKey, claudeApiKey } from "../../lib/secrets";
import { createRequestLogger } from "../../lib/logger";
import { authenticateRequest, sendAuthErrorResponse } from "../../middleware/auth";
import {
  getLicenseByKey,
  incrementTokensUsed,
  createAuditLog,
  updateCostTracking,
  checkAndIncrementRateLimit,
  deductCredits,
} from "../../lib/firestore";
import { sanitizePrompt, validatePrompt } from "../../services/aiRouter";
import { TierChainService } from "../../services/tierChain";
import {
  RouteRequest,
  isValidTaskType,
  MAX_PROMPT_LENGTH,
  LOW_QUOTA_WARNING_THRESHOLD,
  QUOTA_EXCEEDED_THRESHOLD,
  AI_RATE_LIMIT_PER_MINUTE,
} from "../../types/Route";
import {
  PerformanceTier,
  isValidTier,
  determineOptimalTier,
  TIER_CREDITS,
} from "../../types/PerformanceTier";

/**
 * Extracts client IP from request
 */
function getClientIP(req: Request): string {
  const forwarded = req.headers["x-forwarded-for"];
  if (typeof forwarded === "string") {
    return forwarded.split(",")[0].trim();
  }
  return req.ip || req.socket.remoteAddress || "unknown";
}

/**
 * POST /api/ai/route-request
 *
 * Routes an AI generation request to the optimal provider.
 *
 * @description
 * Request body:
 * ```json
 * {
 *   "task_type": "TEXT_GEN" | "CODE_GEN" | "DESIGN_GEN" | "ECOMMERCE_GEN",
 *   "prompt": "string (max 10000 chars)",
 *   "context": { optional site context },
 *   "system_prompt": "optional system prompt",
 *   "temperature": 0.7,
 *   "max_tokens": 4096
 * }
 * ```
 *
 * Required headers:
 * - Authorization: Bearer {site_token}
 *
 * Success response (200):
 * ```json
 * {
 *   "success": true,
 *   "content": "generated content",
 *   "provider": "gemini",
 *   "model": "gemini-1.5-flash",
 *   "tokens_used": 1250,
 *   "cost_usd": 0.0942,
 *   "latency_ms": 2341
 * }
 * ```
 *
 * Error responses:
 * - 401: Missing or invalid Authorization header
 * - 403: License suspended/expired, URL mismatch, quota exceeded
 * - 400: Invalid request body
 * - 429: Rate limited
 * - 503: All providers failed
 */
export const routeRequest = onRequest(
  {
    secrets: [jwtSecret, openaiApiKey, geminiApiKey, claudeApiKey],
    cors: true,
    maxInstances: 100,
  },
  async (req: Request, res: Response) => {
    const requestId = uuidv4();
    const ipAddress = getClientIP(req);
    const logger = createRequestLogger(requestId, "/api/ai/route-request", ipAddress);

    // Only allow POST
    if (req.method !== "POST") {
      logger.warn("Method not allowed", { method: req.method });
      res.status(405).json({
        success: false,
        error: "Method not allowed",
        code: "METHOD_NOT_ALLOWED",
      });
      return;
    }

    try {
      // 1. Authenticate request
      const authResult = await authenticateRequest(req, jwtSecret.value(), logger);

      if (!authResult.authenticated || !authResult.claims) {
        sendAuthErrorResponse(res, authResult);
        return;
      }

      const { claims } = authResult;
      const licenseId = claims.license_id;

      // 2. Rate limiting (per license, 100 req/min)
      const rateLimitKey = `ai_route:${licenseId}`;
      const { limited, count } = await checkAndIncrementRateLimit(
        rateLimitKey,
        ipAddress,
        AI_RATE_LIMIT_PER_MINUTE
      );

      if (limited) {
        logger.warn("Rate limited", { license_id: licenseId, count });
        res.status(429).json({
          success: false,
          error: "Too many requests. Please try again later.",
          code: "RATE_LIMITED",
        });
        return;
      }

      // 3. Validate request body
      const body = req.body as RouteRequest;

      if (!body || typeof body !== "object") {
        logger.warn("Invalid request body");
        res.status(400).json({
          success: false,
          error: "Request body is required",
          code: "INVALID_REQUEST",
        });
        return;
      }

      // Validate task_type
      if (!body.task_type || !isValidTaskType(body.task_type)) {
        logger.warn("Invalid task type", { task_type: body.task_type });
        res.status(400).json({
          success: false,
          error: "Invalid task_type. Must be one of: TEXT_GEN, CODE_GEN, DESIGN_GEN, ECOMMERCE_GEN",
          code: "INVALID_TASK_TYPE",
        });
        return;
      }

      // Validate and sanitize prompt
      const promptValidation = validatePrompt(body.prompt, MAX_PROMPT_LENGTH);
      if (!promptValidation.valid) {
        logger.warn("Invalid prompt", { error: promptValidation.error });
        res.status(400).json({
          success: false,
          error: promptValidation.error,
          code: "INVALID_PROMPT",
        });
        return;
      }

      const sanitizedPrompt = sanitizePrompt(body.prompt);

      // Validate performance_tier if provided
      const requestedTier = body.performance_tier as string | undefined;
      if (requestedTier && !isValidTier(requestedTier)) {
        logger.warn("Invalid performance tier", { tier: requestedTier });
        res.status(400).json({
          success: false,
          error: "Invalid performance_tier. Must be 'flow' or 'craft'",
          code: "INVALID_TIER",
        });
        return;
      }

      // 4. Check quota
      const license = await getLicenseByKey(licenseId);

      if (!license) {
        logger.error("License not found after auth", { license_id: licenseId });
        res.status(500).json({
          success: false,
          error: "Internal error",
          code: "INTERNAL_ERROR",
        });
        return;
      }

      const tokensRemaining = license.tokens_limit - license.tokens_used;

      // Quota exceeded check
      if (tokensRemaining < QUOTA_EXCEEDED_THRESHOLD) {
        logger.warn("Quota exceeded", {
          license_id: licenseId,
          tokens_remaining: tokensRemaining,
        });

        await createAuditLog({
          license_id: licenseId,
          request_type: "ai_request",
          status: "failed",
          error_message: "Quota exceeded",
          ip_address: ipAddress,
        });

        res.status(403).json({
          success: false,
          error: "Token quota exceeded. Please upgrade your plan.",
          code: "QUOTA_EXCEEDED",
        });
        return;
      }

      // Low quota warning (included in response header)
      if (tokensRemaining < LOW_QUOTA_WARNING_THRESHOLD) {
        res.setHeader("X-Quota-Warning", "low");
        res.setHeader("X-Tokens-Remaining", tokensRemaining.toString());
      }

      // 5. Determine performance tier and check credits
      const creditsAvailable = license.credits_available ?? 100; // Default credits if not set

      // Build tier request
      const tierRequest = {
        user_id: licenseId,
        credits_available: creditsAvailable,
        performance_tier: requestedTier as PerformanceTier | undefined,
        task_complexity: body.task_complexity as "simple" | "moderate" | "complex" | undefined,
        is_elementor_template: body.is_elementor_template ?? false,
        prompt: sanitizedPrompt,
        context: body.context,
        system_prompt: body.system_prompt,
        chat_id: body.chat_id,
      };

      // Determine optimal tier
      const selectedTier = determineOptimalTier(tierRequest);
      const requiredCredits = TIER_CREDITS[selectedTier];

      // Check if user has enough credits
      if (creditsAvailable < requiredCredits) {
        logger.warn("Insufficient credits", {
          license_id: licenseId,
          tier: selectedTier,
          required: requiredCredits,
          available: creditsAvailable,
        });

        res.status(403).json({
          success: false,
          error: `Insufficient credits for ${selectedTier.toUpperCase()} mode. Required: ${requiredCredits}, Available: ${creditsAvailable}`,
          code: "INSUFFICIENT_CREDITS",
          tier: selectedTier,
          credits_required: requiredCredits,
          credits_available: creditsAvailable,
        });
        return;
      }

      // 6. Execute tier chain
      const tierChainService = new TierChainService(
        {
          gemini: geminiApiKey.value(),
          claude: claudeApiKey.value(),
        },
        logger
      );

      const result = await tierChainService.execute(tierRequest, selectedTier);

      // 7. Handle result
      if (result.success) {
        // Update license tokens
        await incrementTokensUsed(licenseId, result.total_tokens);

        // Deduct credits
        await deductCredits(licenseId, result.credits_used);

        // Update cost tracking for each step
        for (const step of result.steps) {
          await updateCostTracking(
            licenseId,
            step.provider as "openai" | "gemini" | "claude",
            step.tokens_input,
            step.tokens_output,
            step.cost_usd
          );
        }

        // Create audit log
        const lastProvider = result.steps.length > 0
          ? result.steps[result.steps.length - 1].provider as "openai" | "gemini" | "claude"
          : "gemini";

        await createAuditLog({
          license_id: licenseId,
          request_type: "ai_request",
          provider_used: lastProvider,
          tokens_input: result.steps.reduce((sum, s) => sum + s.tokens_input, 0),
          tokens_output: result.steps.reduce((sum, s) => sum + s.tokens_output, 0),
          cost_usd: result.total_cost_usd,
          status: "success",
          response_time_ms: result.total_latency_ms,
          ip_address: ipAddress,
          metadata: {
            tier: selectedTier,
            credits_used: result.credits_used,
            steps: result.steps.map((s) => ({ step: s.step, provider: s.provider, model: s.model })),
          },
        });

        logger.info("Request completed successfully", {
          license_id: licenseId,
          task_type: body.task_type,
          tier: selectedTier,
          tokens_used: result.total_tokens,
          cost_usd: result.total_cost_usd,
          credits_used: result.credits_used,
        });

        res.status(200).json({
          success: true,
          content: result.content,
          tier: selectedTier,
          strategy: result.strategy,
          validation: result.validation,
          steps: result.steps.map((s) => ({
            step: s.step,
            provider: s.provider,
            model: s.model,
            latency_ms: s.latency_ms,
          })),
          tokens_used: result.total_tokens,
          cost_usd: result.total_cost_usd,
          credits_used: result.credits_used,
          latency_ms: result.total_latency_ms,
        });
      } else {
        // Chain failed
        await createAuditLog({
          license_id: licenseId,
          request_type: "ai_request",
          status: "failed",
          error_message: result.error || "Tier chain failed",
          ip_address: ipAddress,
          metadata: {
            tier: selectedTier,
            steps_completed: result.steps.length,
          },
        });

        logger.error("Tier chain failed", {
          license_id: licenseId,
          task_type: body.task_type,
          tier: selectedTier,
          error: result.error,
        });

        res.status(503).json({
          success: false,
          error: result.error || "Service temporarily unavailable. Please try again later.",
          code: result.error_code || "SERVICE_UNAVAILABLE",
          tier: selectedTier,
        });
      }
    } catch (error) {
      logger.error("Unhandled error in route-request", {
        error: error instanceof Error ? error.message : "Unknown error",
        stack: error instanceof Error ? error.stack : undefined,
      });

      res.status(500).json({
        success: false,
        error: "Internal server error",
        code: "INTERNAL_ERROR",
      });
    }
  }
);
