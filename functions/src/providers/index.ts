/**
 * @fileoverview Central export for all AI provider implementations
 * @module providers
 *
 * @description
 * Exports all AI provider clients and a factory function for creating
 * provider instances. Use createProvider() for consistent provider
 * instantiation with proper error handling.
 *
 * @example
 * ```typescript
 * import { createProvider, GeminiProvider } from "./providers";
 *
 * // Using factory function (recommended)
 * const provider = createProvider("gemini", apiKey);
 *
 * // Or direct instantiation
 * const gemini = new GeminiProvider(apiKey);
 * ```
 */

import { GeminiProvider } from "./gemini";
import { ClaudeProvider } from "./claude";
import {
  IAIProvider,
  ProviderName,
  AIProviderError,
} from "../types/AIProvider";

// Export provider classes
export { GeminiProvider } from "./gemini";
export { ClaudeProvider } from "./claude";

/**
 * Configuration for creating a provider
 */
export interface ProviderConfig {
  /**
   * API key for the provider
   */
  apiKey: string;

  /**
   * Optional model override
   */
  model?: string;
}

/**
 * Creates an AI provider instance
 *
 * @param {ProviderName} providerName - Name of the provider to create
 * @param {string} apiKey - API key for authentication
 * @param {string} model - Optional model override
 * @returns {IAIProvider} Provider instance
 *
 * @throws {AIProviderError} If provider name is unknown or API key is missing
 *
 * @example
 * ```typescript
 * // Create Gemini provider with specific model
 * const gemini = createProvider("gemini", process.env.GEMINI_KEY, "gemini-1.5-pro");
 *
 * // Use the provider
 * const response = await gemini.generate("Hello, world!");
 * ```
 */
export function createProvider(
  providerName: ProviderName,
  apiKey: string,
  model?: string
): IAIProvider {
  switch (providerName) {
    case "gemini":
      return new GeminiProvider(apiKey, model);
    case "claude":
      return new ClaudeProvider(apiKey, model);
    default:
      throw new AIProviderError(
        `Unknown provider: ${providerName}`,
        providerName as ProviderName,
        "INVALID_REQUEST",
        false
      );
  }
}

/**
 * Map of provider names to their default models
 */
export const DEFAULT_MODELS: Record<ProviderName, string> = {
  gemini: "gemini-2.5-flash-preview-05-20",
  claude: "claude-sonnet-4-20250514",
};

/**
 * Map of provider names to their alternative models
 */
export const ALTERNATIVE_MODELS: Record<ProviderName, string[]> = {
  gemini: [
    "gemini-2.5-flash-preview-05-20",
    "gemini-2.5-pro-preview-05-06",
    "gemini-2.0-flash-exp",
    "gemini-1.5-pro",
  ],
  claude: [
    "claude-opus-4-5-20251101",
    "claude-sonnet-4-20250514",
    "claude-3-5-sonnet-20241022",
  ],
};

/**
 * Checks if a model is valid for a provider
 *
 * @param {ProviderName} provider - Provider name
 * @param {string} model - Model name to check
 * @returns {boolean} True if model is valid for provider
 */
export function isValidModel(provider: ProviderName, model: string): boolean {
  const models = ALTERNATIVE_MODELS[provider];
  return models ? models.includes(model) : false;
}
