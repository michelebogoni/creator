/**
 * @fileoverview AI Models Configuration - Single Source of Truth
 * @module config/models
 *
 * @description
 * This file is the ONLY source of truth for AI model configurations in Creator.
 * All other files MUST import from here. DO NOT define model IDs elsewhere.
 *
 * Last updated: December 2025
 */

// ============================================================================
// MODEL DEFINITIONS
// ============================================================================

/**
 * Supported AI providers
 */
export type AIProvider = "gemini" | "claude";

/**
 * Model pricing per 1000 tokens (USD)
 */
export interface ModelPricing {
  /** Cost per 1000 input tokens */
  input: number;
  /** Cost per 1000 output tokens */
  output: number;
}

/**
 * Complete model configuration
 */
export interface ModelConfig {
  /** Official API model identifier */
  id: string;
  /** Human-readable display name */
  displayName: string;
  /** Short description */
  description: string;
  /** Provider name */
  provider: AIProvider;
  /** Pricing per 1000 tokens */
  pricing: ModelPricing;
  /** Maximum context window (tokens) */
  contextWindow: number;
  /** Maximum output tokens */
  maxOutputTokens: number;
  /** Whether this model supports multimodal (images) */
  supportsMultimodal: boolean;
  /** Whether this model is currently available/active */
  isActive: boolean;
}

// ============================================================================
// GEMINI MODELS
// ============================================================================

/**
 * Google Gemini model configurations
 *
 * @description
 * Gemini models for various use cases:
 * - Flash: Fast, cost-effective for simple tasks
 * - Pro: Balanced performance for complex reasoning
 */
export const GEMINI_MODELS = {
  /**
   * Gemini 2.5 Flash - Fast and cost-effective
   * Best for: Quick iterations, CSS, simple snippets
   */
  FLASH: {
    id: "gemini-2.5-flash-preview-05-20",
    displayName: "Gemini 2.5 Flash",
    description: "Fast and cost-effective for iterative tasks",
    provider: "gemini" as const,
    pricing: {
      input: 0.00015,
      output: 0.0006,
    },
    contextWindow: 1000000,
    maxOutputTokens: 8192,
    supportsMultimodal: true,
    isActive: true,
  },

  /**
   * Gemini 2.5 Pro - Balanced performance
   * Best for: Complex reasoning, strategy generation
   */
  PRO: {
    id: "gemini-2.5-pro-preview-05-06",
    displayName: "Gemini 2.5 Pro",
    description: "Advanced reasoning and complex task handling",
    provider: "gemini" as const,
    pricing: {
      input: 0.00125,
      output: 0.005,
    },
    contextWindow: 1000000,
    maxOutputTokens: 8192,
    supportsMultimodal: true,
    isActive: true,
  },
} as const;

// ============================================================================
// CLAUDE MODELS
// ============================================================================

/**
 * Anthropic Claude model configurations
 *
 * @description
 * Claude models for various use cases:
 * - Sonnet: Balanced cost/quality for most tasks
 * - Opus: Highest quality for critical code generation
 */
export const CLAUDE_MODELS = {
  /**
   * Claude Sonnet 4 - Balanced performance
   * Best for: Standard code generation, content creation
   */
  SONNET: {
    id: "claude-sonnet-4-20250514",
    displayName: "Claude Sonnet 4",
    description: "Balanced model for coding and creative tasks",
    provider: "claude" as const,
    pricing: {
      input: 0.003,
      output: 0.015,
    },
    contextWindow: 200000,
    maxOutputTokens: 8192,
    supportsMultimodal: true,
    isActive: true,
  },

  /**
   * Claude Opus 4.5 - Highest quality
   * Best for: Complex implementations, critical code
   */
  OPUS: {
    id: "claude-opus-4-5-20251101",
    displayName: "Claude Opus 4.5",
    description: "Highest quality for complex, critical tasks",
    provider: "claude" as const,
    pricing: {
      input: 0.015,
      output: 0.075,
    },
    contextWindow: 200000,
    maxOutputTokens: 8192,
    supportsMultimodal: true,
    isActive: true,
  },
} as const;

// ============================================================================
// UNIFIED MODEL REGISTRY
// ============================================================================

/**
 * AI_MODELS - The single source of truth for all AI models
 *
 * @description
 * Import this constant to access any model configuration.
 * Use AI_MODELS.GEMINI.FLASH, AI_MODELS.CLAUDE.OPUS, etc.
 *
 * @example
 * ```typescript
 * import { AI_MODELS } from '../config/models';
 *
 * const modelId = AI_MODELS.GEMINI.PRO.id;
 * const price = AI_MODELS.CLAUDE.OPUS.pricing;
 * ```
 */
export const AI_MODELS = {
  GEMINI: GEMINI_MODELS,
  CLAUDE: CLAUDE_MODELS,
} as const;

/**
 * All model configurations as a flat array
 * Useful for iteration and validation
 */
export const ALL_MODELS: ModelConfig[] = [
  GEMINI_MODELS.FLASH,
  GEMINI_MODELS.PRO,
  CLAUDE_MODELS.SONNET,
  CLAUDE_MODELS.OPUS,
];

/**
 * Map of model ID to configuration for quick lookup
 */
export const MODEL_BY_ID: Record<string, ModelConfig> = ALL_MODELS.reduce(
  (acc, model) => {
    acc[model.id] = model;
    return acc;
  },
  {} as Record<string, ModelConfig>
);

// ============================================================================
// DEFAULT MODELS
// ============================================================================

/**
 * Default models for each provider
 * Used when no specific model is requested
 */
export const DEFAULT_MODELS = {
  gemini: GEMINI_MODELS.PRO,
  claude: CLAUDE_MODELS.OPUS,
} as const;

// ============================================================================
// TIER CONFIGURATION
// ============================================================================

/**
 * Performance tier types
 */
export type PerformanceTier = "flow" | "craft";

/**
 * Tier model chain configuration
 */
export interface TierModelChain {
  /** Tier identifier */
  tier: PerformanceTier;
  /** Credit cost for this tier */
  credits: number;
  /** Model for context analysis (first step) */
  analyzer: ModelConfig;
  /** Model for strategy generation (CRAFT only) */
  strategist?: ModelConfig;
  /** Model for implementation */
  implementer: ModelConfig;
  /** Model for validation (optional) */
  validator?: ModelConfig;
}

/**
 * TIER_CONFIGURATION - Models used in each performance tier
 *
 * @description
 * Defines which models are used in the FLOW and CRAFT chains:
 *
 * FLOW (0.5 credits):
 * - Analyzer: Gemini Flash (fast context analysis)
 * - Implementer: Claude Sonnet (balanced code generation)
 *
 * CRAFT (2.0 credits):
 * - Analyzer: Gemini Flash (fast context analysis)
 * - Strategist: Gemini Pro (deep strategy planning)
 * - Implementer: Claude Opus (highest quality code)
 * - Validator: Claude Opus (optional AI validation)
 */
export const TIER_CONFIGURATION: Record<PerformanceTier, TierModelChain> = {
  /**
   * FLOW Mode - Fast and cost-effective
   * Best for: Iterative work, CSS modifications, quick snippets
   */
  flow: {
    tier: "flow",
    credits: 0.5,
    analyzer: GEMINI_MODELS.FLASH,
    implementer: CLAUDE_MODELS.SONNET,
  },

  /**
   * CRAFT Mode - Maximum quality
   * Best for: Complex templates, critical code, multi-plugin integrations
   */
  craft: {
    tier: "craft",
    credits: 2.0,
    analyzer: GEMINI_MODELS.FLASH,
    strategist: GEMINI_MODELS.PRO,
    implementer: CLAUDE_MODELS.OPUS,
    validator: CLAUDE_MODELS.OPUS,
  },
} as const;

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Checks if a model ID is valid and exists in the registry
 *
 * @param {string} modelId - The model ID to validate
 * @returns {boolean} True if the model exists and is active
 *
 * @example
 * ```typescript
 * if (isValidModel("gemini-2.5-pro-preview-05-06")) {
 *   // Model is valid, proceed
 * }
 * ```
 */
export function isValidModel(modelId: string): boolean {
  const model = MODEL_BY_ID[modelId];
  return model !== undefined && model.isActive;
}

/**
 * Gets the pricing configuration for a model
 *
 * @param {string} modelId - The model ID to get pricing for
 * @returns {ModelPricing | null} Pricing object or null if model not found
 *
 * @example
 * ```typescript
 * const pricing = getModelPricing("claude-opus-4-5-20251101");
 * if (pricing) {
 *   const inputCost = tokens * pricing.input / 1000;
 * }
 * ```
 */
export function getModelPricing(modelId: string): ModelPricing | null {
  const model = MODEL_BY_ID[modelId];
  return model?.pricing ?? null;
}

/**
 * Gets the full model configuration by ID
 *
 * @param {string} modelId - The model ID to look up
 * @returns {ModelConfig | null} Full model configuration or null
 *
 * @example
 * ```typescript
 * const model = getModelConfig("gemini-2.5-flash-preview-05-20");
 * console.log(model?.displayName); // "Gemini 2.5 Flash"
 * ```
 */
export function getModelConfig(modelId: string): ModelConfig | null {
  return MODEL_BY_ID[modelId] ?? null;
}

/**
 * Gets the default model for a provider
 *
 * @param {AIProvider} provider - The provider name
 * @returns {ModelConfig} Default model configuration
 *
 * @example
 * ```typescript
 * const defaultGemini = getDefaultModel("gemini");
 * console.log(defaultGemini.id); // "gemini-2.5-pro-preview-05-06"
 * ```
 */
export function getDefaultModel(provider: AIProvider): ModelConfig {
  return DEFAULT_MODELS[provider];
}

/**
 * Gets all active models for a provider
 *
 * @param {AIProvider} provider - The provider name
 * @returns {ModelConfig[]} Array of active models for the provider
 *
 * @example
 * ```typescript
 * const claudeModels = getProviderModels("claude");
 * // Returns [SONNET, OPUS]
 * ```
 */
export function getProviderModels(provider: AIProvider): ModelConfig[] {
  return ALL_MODELS.filter((m) => m.provider === provider && m.isActive);
}

/**
 * Calculates cost for a request based on token usage
 *
 * @param {string} modelId - The model ID used
 * @param {number} inputTokens - Number of input tokens
 * @param {number} outputTokens - Number of output tokens
 * @returns {number} Total cost in USD
 *
 * @example
 * ```typescript
 * const cost = calculateModelCost("claude-opus-4-5-20251101", 1000, 500);
 * // Returns: 0.0525 USD
 * ```
 */
export function calculateModelCost(
  modelId: string,
  inputTokens: number,
  outputTokens: number
): number {
  const pricing = getModelPricing(modelId);

  if (!pricing) {
    // Fallback to expensive pricing if model not found
    console.warn(`Unknown model "${modelId}", using fallback pricing`);
    return (inputTokens * 0.01 + outputTokens * 0.03) / 1000;
  }

  const inputCost = (inputTokens * pricing.input) / 1000;
  const outputCost = (outputTokens * pricing.output) / 1000;

  return inputCost + outputCost;
}

/**
 * Gets the tier configuration
 *
 * @param {PerformanceTier} tier - The tier name
 * @returns {TierModelChain} Tier configuration with models
 *
 * @example
 * ```typescript
 * const craftConfig = getTierConfig("craft");
 * console.log(craftConfig.implementer.id); // "claude-opus-4-5-20251101"
 * ```
 */
export function getTierConfig(tier: PerformanceTier): TierModelChain {
  return TIER_CONFIGURATION[tier];
}

/**
 * Checks if a tier name is valid
 *
 * @param {string} tier - The tier name to validate
 * @returns {tier is PerformanceTier} True if valid tier
 */
export function isValidTier(tier: string): tier is PerformanceTier {
  return tier === "flow" || tier === "craft";
}

// ============================================================================
// EXPORTS SUMMARY
// ============================================================================

/**
 * Quick reference for imports:
 *
 * Models:
 * - AI_MODELS.GEMINI.FLASH / PRO
 * - AI_MODELS.CLAUDE.SONNET / OPUS
 * - ALL_MODELS (flat array)
 * - MODEL_BY_ID (lookup map)
 * - DEFAULT_MODELS
 *
 * Tiers:
 * - TIER_CONFIGURATION.flow / .craft
 *
 * Helpers:
 * - isValidModel(id)
 * - getModelPricing(id)
 * - getModelConfig(id)
 * - getDefaultModel(provider)
 * - getProviderModels(provider)
 * - calculateModelCost(id, input, output)
 * - getTierConfig(tier)
 * - isValidTier(tier)
 */
