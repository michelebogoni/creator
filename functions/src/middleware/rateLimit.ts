/**
 * @fileoverview Rate limiting middleware for Creator AI Proxy
 * @module middleware/rateLimit
 */

import { Request, Response } from "express";
import { checkAndIncrementRateLimit } from "../lib/firestore";
import { Logger } from "../lib/logger";
import { ERROR_MESSAGE_MAP, ERROR_STATUS_MAP } from "../types/Auth";

/**
 * Rate limit configuration
 */
export interface RateLimitConfig {
  /** Maximum requests per window */
  maxRequests: number;
  /** Window size in seconds (not used with minute-bucket approach) */
  windowSeconds?: number;
  /** Endpoint identifier for the rate limiter */
  endpoint: string;
}

/**
 * Default rate limit configuration
 */
const DEFAULT_CONFIG: RateLimitConfig = {
  maxRequests: 10,
  windowSeconds: 60,
  endpoint: "default",
};

/**
 * Extracts the client IP address from a request
 *
 * @param {Request} req - The incoming request
 * @returns {string} The client IP address
 *
 * @description
 * Attempts to get the real client IP from various headers (for proxied requests)
 * Falls back to socket remote address if headers not present
 */
export function getClientIP(req: Request): string {
  // Check X-Forwarded-For header (common with proxies/load balancers)
  const forwardedFor = req.headers["x-forwarded-for"];
  if (forwardedFor) {
    // X-Forwarded-For can contain multiple IPs, take the first (client)
    const ips = Array.isArray(forwardedFor)
      ? forwardedFor[0]
      : forwardedFor.split(",")[0];
    return ips.trim();
  }

  // Check X-Real-IP header
  const realIP = req.headers["x-real-ip"];
  if (realIP) {
    return Array.isArray(realIP) ? realIP[0] : realIP;
  }

  // Fallback to socket remote address
  return req.socket?.remoteAddress || req.ip || "unknown";
}

/**
 * Checks rate limit for a request
 *
 * @param {Request} req - The incoming request
 * @param {RateLimitConfig} config - Rate limit configuration
 * @param {Logger} logger - Logger instance
 * @returns {Promise<{ allowed: boolean; count: number }>} Rate limit check result
 *
 * @example
 * ```typescript
 * const { allowed, count } = await checkRateLimit(req, {
 *   maxRequests: 10,
 *   endpoint: "validate_license"
 * }, logger);
 *
 * if (!allowed) {
 *   return res.status(429).json({ error: "Rate limited" });
 * }
 * ```
 */
export async function checkRateLimit(
  req: Request,
  config: RateLimitConfig,
  logger: Logger
): Promise<{ allowed: boolean; count: number }> {
  const ipAddress = getClientIP(req);
  const { maxRequests, endpoint } = { ...DEFAULT_CONFIG, ...config };

  try {
    const { limited, count } = await checkAndIncrementRateLimit(
      endpoint,
      ipAddress,
      maxRequests
    );

    if (limited) {
      logger.warn("Rate limit exceeded", {
        ip_address: ipAddress,
        endpoint,
        count,
        limit: maxRequests,
      });
    }

    return { allowed: !limited, count };
  } catch (error) {
    // On error, allow the request but log the issue
    logger.error("Rate limit check failed", {
      error: error instanceof Error ? error.message : "Unknown error",
      ip_address: ipAddress,
      endpoint,
    });
    // Fail open - allow request if rate limiter is broken
    return { allowed: true, count: 0 };
  }
}

/**
 * Sends a rate limit error response
 *
 * @param {Response} res - The response object
 * @param {number} retryAfter - Seconds until rate limit resets
 * @returns {void}
 */
export function sendRateLimitResponse(
  res: Response,
  retryAfter: number = 60
): void {
  res.setHeader("Retry-After", retryAfter.toString());
  res.setHeader("X-RateLimit-Reset", new Date(Date.now() + retryAfter * 1000).toISOString());

  res.status(ERROR_STATUS_MAP.RATE_LIMITED).json({
    success: false,
    error: ERROR_MESSAGE_MAP.RATE_LIMITED,
    code: "RATE_LIMITED",
  });
}

/**
 * Rate limiting middleware factory
 *
 * @param {Partial<RateLimitConfig>} config - Rate limit configuration
 * @returns {Function} Express-style middleware function
 *
 * @example
 * ```typescript
 * const rateLimiter = createRateLimitMiddleware({
 *   maxRequests: 10,
 *   endpoint: "validate_license"
 * });
 *
 * // In your handler:
 * const rateLimitResult = await rateLimiter(req, res, logger);
 * if (!rateLimitResult.continue) return;
 * ```
 */
export function createRateLimitMiddleware(config: Partial<RateLimitConfig> = {}) {
  const finalConfig = { ...DEFAULT_CONFIG, ...config };

  return async (
    req: Request,
    res: Response,
    logger: Logger
  ): Promise<{ continue: boolean }> => {
    const { allowed, count } = await checkRateLimit(req, finalConfig, logger);

    if (!allowed) {
      // Add rate limit headers
      res.setHeader("X-RateLimit-Limit", finalConfig.maxRequests.toString());
      res.setHeader("X-RateLimit-Remaining", "0");
      res.setHeader("X-RateLimit-Count", count.toString());

      sendRateLimitResponse(res);
      return { continue: false };
    }

    // Add rate limit info headers for successful requests
    res.setHeader("X-RateLimit-Limit", finalConfig.maxRequests.toString());
    res.setHeader(
      "X-RateLimit-Remaining",
      Math.max(0, finalConfig.maxRequests - count).toString()
    );

    return { continue: true };
  };
}
