/**
 * @fileoverview Model Configuration for Creator AI Proxy
 * @module types/ModelConfig
 *
 * @description
 * Simple model selection: Gemini 3 Pro or Claude Sonnet 4
 * Each model is the fallback for the other.
 */

/**
 * Available AI models
 */
export type AIModel = "gemini" | "claude";

/**
 * Model identifiers for API calls
 */
export const MODEL_IDS = {
  gemini: "gemini-3-pro-preview",
  claude: "claude-sonnet-4-20250514",
} as const;

/**
 * Model display names
 */
export const MODEL_NAMES = {
  gemini: "Gemini 3 Pro",
  claude: "Claude Sonnet 4",
} as const;

/**
 * Model descriptions
 */
export const MODEL_DESCRIPTIONS = {
  gemini: "Google's most advanced model for complex tasks and reasoning",
  claude: "Anthropic's balanced model for coding and creative tasks",
} as const;

/**
 * Get fallback model
 */
export function getFallbackModel(model: AIModel): AIModel {
  return model === "gemini" ? "claude" : "gemini";
}

/**
 * Check if a string is a valid model
 */
export function isValidModel(model: string): model is AIModel {
  return model === "gemini" || model === "claude";
}

/**
 * Model request interface
 */
export interface ModelRequest {
  /** Selected model */
  model: AIModel;

  /** User prompt */
  prompt: string;

  /** Site context from WordPress */
  context?: Record<string, unknown>;

  /** System prompt override */
  system_prompt?: string;

  /** Chat ID for session tracking */
  chat_id?: string;

  /** Temperature (0-1) */
  temperature?: number;

  /** Max tokens */
  max_tokens?: number;
}

/**
 * Model response interface
 */
export interface ModelResponse {
  /** Overall success */
  success: boolean;

  /** Generated content */
  content: string;

  /** Model used */
  model: AIModel;

  /** Model ID used */
  model_id: string;

  /** Whether fallback was used */
  used_fallback: boolean;

  /** Tokens used */
  tokens_input: number;
  tokens_output: number;
  total_tokens: number;

  /** Cost in USD */
  cost_usd: number;

  /** Latency in ms */
  latency_ms: number;

  /** Error if failed */
  error?: string;
  error_code?: string;
}
