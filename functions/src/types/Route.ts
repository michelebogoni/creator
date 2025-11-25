/**
 * @fileoverview Route request/response type definitions for AI routing
 * @module types/Route
 *
 * @description
 * Defines types for the smart router endpoint that handles
 * AI generation requests with automatic provider fallback.
 */

import { ProviderName } from "./AIProvider";

/**
 * Supported task types for AI routing
 *
 * @description
 * Each task type maps to a specific routing configuration:
 * - TEXT_GEN: Articles, descriptions - prioritizes speed/cost
 * - CODE_GEN: Code generation - prioritizes quality
 * - DESIGN_GEN: Layout/design - prioritizes context window
 * - ECOMMERCE_GEN: Product descriptions - prioritizes context window
 */
export type TaskType = "TEXT_GEN" | "CODE_GEN" | "DESIGN_GEN" | "ECOMMERCE_GEN";

/**
 * Request body for the route-request endpoint
 *
 * @interface RouteRequest
 */
export interface RouteRequest {
  /**
   * Type of AI task to perform
   */
  task_type: TaskType;

  /**
   * The prompt to send to the AI provider
   * Must be non-empty and under 10000 characters
   */
  prompt: string;

  /**
   * Optional context about the requesting site
   * Can include site_title, theme, plugins, etc.
   */
  context?: Record<string, unknown>;

  /**
   * Optional system prompt override
   */
  system_prompt?: string;

  /**
   * Optional temperature override (0-1)
   */
  temperature?: number;

  /**
   * Optional max tokens override
   */
  max_tokens?: number;
}

/**
 * Successful response from route-request endpoint
 *
 * @interface RouteResponseSuccess
 */
export interface RouteResponseSuccess {
  /** Always true for success */
  success: true;

  /** Generated content */
  content: string;

  /** Provider that handled the request */
  provider: ProviderName;

  /** Specific model used */
  model: string;

  /** Total tokens used (input + output) */
  tokens_used: number;

  /** Cost in USD */
  cost_usd: number;

  /** Response latency in milliseconds */
  latency_ms: number;
}

/**
 * Error response from route-request endpoint
 *
 * @interface RouteResponseError
 */
export interface RouteResponseError {
  /** Always false for errors */
  success: false;

  /** Human-readable error message */
  error: string;

  /** Machine-readable error code */
  code: string;
}

/**
 * Union type for route responses
 */
export type RouteResponse = RouteResponseSuccess | RouteResponseError;

/**
 * Routing configuration for a provider
 *
 * @interface ProviderRouteConfig
 */
export interface ProviderRouteConfig {
  /** Provider name */
  provider: ProviderName;

  /** Model to use */
  model: string;
}

/**
 * Routing matrix entry for a task type
 *
 * @interface TaskRouteConfig
 */
export interface TaskRouteConfig {
  /** Primary provider to try first */
  primary: ProviderRouteConfig;

  /** First fallback if primary fails */
  fallback1: ProviderRouteConfig;

  /** Second fallback if first fallback fails */
  fallback2: ProviderRouteConfig;
}

/**
 * Complete routing matrix mapping task types to provider chains
 */
export type RoutingMatrix = Record<TaskType, TaskRouteConfig>;

/**
 * Default routing matrix as per roadmap specifications
 *
 * @description
 * TEXT_GEN: Gemini Flash (fast/cheap) → OpenAI GPT-4o-mini → Claude
 * CODE_GEN: Claude (best code) → OpenAI GPT-4o → Gemini Pro
 * DESIGN_GEN: Gemini Pro (large context) → OpenAI GPT-4o → Claude
 * ECOMMERCE_GEN: Gemini Pro (large context) → OpenAI GPT-4o → Claude
 */
export const DEFAULT_ROUTING_MATRIX: RoutingMatrix = {
  TEXT_GEN: {
    primary: { provider: "gemini", model: "gemini-1.5-flash" },
    fallback1: { provider: "openai", model: "gpt-4o-mini" },
    fallback2: { provider: "claude", model: "claude-3-5-sonnet-20241022" },
  },
  CODE_GEN: {
    primary: { provider: "claude", model: "claude-3-5-sonnet-20241022" },
    fallback1: { provider: "openai", model: "gpt-4o" },
    fallback2: { provider: "gemini", model: "gemini-1.5-pro" },
  },
  DESIGN_GEN: {
    primary: { provider: "gemini", model: "gemini-1.5-pro" },
    fallback1: { provider: "openai", model: "gpt-4o" },
    fallback2: { provider: "claude", model: "claude-3-5-sonnet-20241022" },
  },
  ECOMMERCE_GEN: {
    primary: { provider: "gemini", model: "gemini-1.5-pro" },
    fallback1: { provider: "openai", model: "gpt-4o" },
    fallback2: { provider: "claude", model: "claude-3-5-sonnet-20241022" },
  },
};

/**
 * Valid task types for validation
 */
export const VALID_TASK_TYPES: TaskType[] = [
  "TEXT_GEN",
  "CODE_GEN",
  "DESIGN_GEN",
  "ECOMMERCE_GEN",
];

/**
 * Checks if a string is a valid task type
 *
 * @param {string} taskType - The task type to validate
 * @returns {boolean} True if valid
 */
export function isValidTaskType(taskType: string): taskType is TaskType {
  return VALID_TASK_TYPES.includes(taskType as TaskType);
}

/**
 * Maximum prompt length in characters
 */
export const MAX_PROMPT_LENGTH = 10000;

/**
 * Minimum quota threshold for warnings
 */
export const LOW_QUOTA_WARNING_THRESHOLD = 1000;

/**
 * Minimum quota threshold for errors
 */
export const QUOTA_EXCEEDED_THRESHOLD = 100;

/**
 * Rate limit for AI requests per license per minute
 */
export const AI_RATE_LIMIT_PER_MINUTE = 100;
