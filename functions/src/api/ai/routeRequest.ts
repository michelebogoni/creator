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
} from "../../lib/firestore";
import { AIRouter, sanitizePrompt, validatePrompt } from "../../services/aiRouter";
import {
  RouteRequest,
  isValidTaskType,
  MAX_PROMPT_LENGTH,
  LOW_QUOTA_WARNING_THRESHOLD,
  QUOTA_EXCEEDED_THRESHOLD,
  AI_RATE_LIMIT_PER_MINUTE,
} from "../../types/Route";

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

      // 5. Route request to AI provider
      const router = new AIRouter(
        {
          openai: openaiApiKey.value(),
          gemini: geminiApiKey.value(),
          claude: claudeApiKey.value(),
        },
        logger
      );

      const result = await router.route(body.task_type, sanitizedPrompt, {
        temperature: body.temperature,
        max_tokens: body.max_tokens,
        system_prompt: body.system_prompt,
      });

      // 6. Handle result
      if (result.success) {
        // Update license tokens
        await incrementTokensUsed(licenseId, result.total_tokens);

        // Update cost tracking
        await updateCostTracking(
          licenseId,
          result.provider,
          result.tokens_input,
          result.tokens_output,
          result.cost_usd
        );

        // Create audit log
        await createAuditLog({
          license_id: licenseId,
          request_type: "ai_request",
          provider_used: result.provider,
          tokens_input: result.tokens_input,
          tokens_output: result.tokens_output,
          cost_usd: result.cost_usd,
          status: "success",
          response_time_ms: result.latency_ms,
          ip_address: ipAddress,
        });

        logger.info("Request completed successfully", {
          license_id: licenseId,
          task_type: body.task_type,
          provider: result.provider,
          tokens_used: result.total_tokens,
          cost_usd: result.cost_usd,
        });

        res.status(200).json({
          success: true,
          content: result.content,
          provider: result.provider,
          model: result.model,
          tokens_used: result.total_tokens,
          cost_usd: result.cost_usd,
          latency_ms: result.latency_ms,
        });
      } else {
        // All providers failed
        await createAuditLog({
          license_id: licenseId,
          request_type: "ai_request",
          status: "failed",
          error_message: result.error || "All providers failed",
          ip_address: ipAddress,
        });

        logger.error("All providers failed", {
          license_id: licenseId,
          task_type: body.task_type,
          providers_attempted: result.providers_attempted,
          error: result.error,
        });

        res.status(503).json({
          success: false,
          error: "Service temporarily unavailable. Please try again later.",
          code: "SERVICE_UNAVAILABLE",
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
