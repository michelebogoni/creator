/**
 * @fileoverview Performance Tier type definitions for Creator AI Proxy
 * @module types/PerformanceTier
 *
 * @description
 * Defines the performance tiers for AI processing:
 * - FLOW: Fast, cost-effective chain for iterative work
 * - CRAFT: Full-power chain for complex, quality-critical tasks
 */

/**
 * Performance tier identifiers
 *
 * @description
 * - flow: High performance, balanced cost/quality (Gemini Flash → Claude 4 Sonnet)
 * - craft: Full power, maximum quality (Gemini Flash → Gemini Pro → Claude Opus)
 */
export type PerformanceTier = "flow" | "craft";

/**
 * Credit costs per tier
 */
export const TIER_CREDITS: Record<PerformanceTier, number> = {
  flow: 0.5,
  craft: 2.0,
};

/**
 * AI Validation cost (optional, CRAFT only)
 */
export const AI_VALIDATION_CREDIT_COST = 0.5;

/**
 * Tier configuration with metadata
 */
export interface TierConfig {
  /** Tier identifier */
  id: PerformanceTier;

  /** Display name */
  name: string;

  /** Icon for UI */
  icon: string;

  /** Credit cost per request */
  credits: number;

  /** Expected response time range in seconds */
  responseTime: {
    min: number;
    max: number;
  };

  /** Quality score percentage */
  qualityScore: number;

  /** Best use cases */
  bestFor: string[];

  /** Chain description */
  chain: string;
}

/**
 * Tier configurations
 */
export const TIER_CONFIGS: Record<PerformanceTier, TierConfig> = {
  flow: {
    id: "flow",
    name: "Flow Mode",
    icon: "✍🏼",
    credits: 0.5,
    responseTime: { min: 20, max: 30 },
    qualityScore: 85,
    bestFor: [
      "Iterative content editing",
      "CSS modifications",
      "Quick snippets",
      "Configuration changes",
      "Standard templates",
    ],
    chain: "Gemini 2.5 Flash → Claude 4 Sonnet → Validation",
  },
  craft: {
    id: "craft",
    name: "Craft Mode",
    icon: "⚙️",
    credits: 2.0,
    responseTime: { min: 45, max: 60 },
    qualityScore: 95,
    bestFor: [
      "Complex CPT + ACF + Elementor operations",
      "Site architecture planning",
      "Custom Elementor templates",
      "Multi-plugin integrations",
      "Critical code generation",
    ],
    chain: "Gemini 2.5 Flash → Gemini 2.5 Pro → Claude 4.5 Opus → Validation",
  },
};

/**
 * Models used in each tier
 */
export const TIER_MODELS = {
  flow: {
    analyzer: "gemini-2.5-flash",
    implementer: "claude-sonnet-4-20250514",
  },
  craft: {
    analyzer: "gemini-2.5-flash",
    strategist: "gemini-2.5-pro",
    implementer: "claude-opus-4-5-20251101",
    validator: "claude-opus-4-5-20251101",
  },
} as const;

/**
 * Request with performance tier
 */
export interface TierRequest {
  /** User ID */
  user_id: string;

  /** Available credits */
  credits_available: number;

  /** Selected performance tier (optional, auto-select if not provided) */
  performance_tier?: PerformanceTier;

  /** Task complexity assessment */
  task_complexity?: "simple" | "moderate" | "complex";

  /** Whether request involves Elementor template generation */
  is_elementor_template?: boolean;

  /** The actual prompt */
  prompt: string;

  /** Site context from WordPress */
  context?: Record<string, unknown>;

  /** System prompt override */
  system_prompt?: string;

  /** Chat ID for session tracking */
  chat_id?: string;
}

/**
 * Chain step result
 */
export interface ChainStepResult {
  /** Step name */
  step: string;

  /** Provider used */
  provider: string;

  /** Model used */
  model: string;

  /** Step output */
  output: string;

  /** Tokens used */
  tokens_input: number;
  tokens_output: number;

  /** Cost for this step */
  cost_usd: number;

  /** Latency for this step */
  latency_ms: number;
}

/**
 * Tier chain response
 */
export interface TierChainResponse {
  /** Overall success */
  success: boolean;

  /** Tier used */
  tier: PerformanceTier;

  /** Final implementation/response */
  content: string;

  /** Strategy (CRAFT only) */
  strategy?: string;

  /** Validation result */
  validation?: {
    syntactic: {
      valid: boolean;
      errors?: string[];
    };
    ai?: {
      valid: boolean;
      feedback?: string;
      cost_usd: number;
    };
  };

  /** Individual chain step results */
  steps: ChainStepResult[];

  /** Total tokens used across all steps */
  total_tokens: number;

  /** Total cost in USD */
  total_cost_usd: number;

  /** Total latency in ms */
  total_latency_ms: number;

  /** Credits consumed */
  credits_used: number;

  /** Error if failed */
  error?: string;
  error_code?: string;
}

/**
 * Checks if a string is a valid performance tier
 */
export function isValidTier(tier: string): tier is PerformanceTier {
  return tier === "flow" || tier === "craft";
}

/**
 * Gets the default tier based on user preferences or system default
 */
export function getDefaultTier(): PerformanceTier {
  return "flow";
}

/**
 * Determines optimal tier based on request characteristics
 */
export function determineOptimalTier(request: TierRequest): PerformanceTier {
  // If user specified a tier and has enough credits, use it
  if (request.performance_tier && isValidTier(request.performance_tier)) {
    const requiredCredits = TIER_CREDITS[request.performance_tier];
    if (request.credits_available >= requiredCredits) {
      return request.performance_tier;
    }
    // Fall through to auto-select if insufficient credits
  }

  // Auto-select based on task characteristics

  // Elementor templates benefit from full power chain
  if (request.is_elementor_template && request.credits_available >= TIER_CREDITS.craft) {
    return "craft";
  }

  // Complex tasks need the full chain
  if (request.task_complexity === "complex" && request.credits_available >= TIER_CREDITS.craft) {
    return "craft";
  }

  // Default to flow for cost efficiency
  return "flow";
}
