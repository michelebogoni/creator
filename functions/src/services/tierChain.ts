/**
 * @fileoverview Tier Chain Service for Creator AI Proxy
 * @module services/tierChain
 *
 * @description
 * Implements the two-tier AI processing chains:
 * - FLOW: Fast, cost-effective (Gemini Flash → Claude Sonnet → Validation)
 * - CRAFT: Full power, maximum quality (Gemini Flash → Gemini Pro → Claude Opus → Validation)
 */

import { GeminiProvider } from "../providers/gemini";
import { ClaudeProvider } from "../providers/claude";
import {
  PerformanceTier,
  TierRequest,
  TierChainResponse,
  ChainStepResult,
  TIER_MODELS,
  TIER_CREDITS,
} from "../types/PerformanceTier";
import { Logger } from "../lib/logger";

/**
 * Provider keys configuration
 */
export interface TierChainKeys {
  gemini: string;
  claude: string;
}

/**
 * Tier Chain Service
 *
 * @class TierChainService
 *
 * @description
 * Orchestrates multi-step AI chains based on performance tier:
 * - FLOW: 2-step chain for speed and cost efficiency
 * - CRAFT: 4-step chain for maximum quality
 */
export class TierChainService {
  private keys: TierChainKeys;
  private logger: Logger;

  constructor(keys: TierChainKeys, logger: Logger) {
    this.keys = keys;
    this.logger = logger.child({ service: "tierChain" });
  }

  /**
   * Execute the appropriate chain based on tier
   */
  async execute(request: TierRequest, tier: PerformanceTier): Promise<TierChainResponse> {
    this.logger.info("Starting tier chain execution", {
      tier,
      prompt_length: request.prompt.length,
      has_context: !!request.context,
    });

    const startTime = Date.now();

    try {
      if (tier === "flow") {
        return await this.executeFlowChain(request, startTime);
      } else {
        return await this.executeCraftChain(request, startTime);
      }
    } catch (error) {
      this.logger.error("Tier chain execution failed", {
        tier,
        error: error instanceof Error ? error.message : "Unknown error",
      });

      return {
        success: false,
        tier,
        content: "",
        steps: [],
        total_tokens: 0,
        total_cost_usd: 0,
        total_latency_ms: Date.now() - startTime,
        credits_used: 0,
        error: error instanceof Error ? error.message : "Chain execution failed",
        error_code: "CHAIN_EXECUTION_FAILED",
      };
    }
  }

  /**
   * FLOW Chain: Fast, cost-effective
   *
   * Step 1: Gemini 2.5 Flash - Quick context analysis
   * Step 2: Claude 4 Sonnet - Strategy + Implementation
   * Step 3: Syntactic Validation (automatic, no AI cost)
   */
  private async executeFlowChain(request: TierRequest, startTime: number): Promise<TierChainResponse> {
    const steps: ChainStepResult[] = [];
    let totalTokens = 0;
    let totalCost = 0;

    // Step 1: Context Analysis with Gemini Flash
    this.logger.debug("FLOW Step 1: Context analysis with Gemini Flash");
    const analyzerResult = await this.runAnalyzer(request, "flow");
    steps.push(analyzerResult);
    totalTokens += analyzerResult.tokens_input + analyzerResult.tokens_output;
    totalCost += analyzerResult.cost_usd;

    if (!analyzerResult.output) {
      return this.buildErrorResponse("flow", steps, totalTokens, totalCost, startTime, "Analyzer step failed");
    }

    // Step 2: Strategy + Implementation with Claude Sonnet
    this.logger.debug("FLOW Step 2: Implementation with Claude Sonnet");
    const implementerResult = await this.runImplementer(request, analyzerResult.output, "flow");
    steps.push(implementerResult);
    totalTokens += implementerResult.tokens_input + implementerResult.tokens_output;
    totalCost += implementerResult.cost_usd;

    if (!implementerResult.output) {
      return this.buildErrorResponse("flow", steps, totalTokens, totalCost, startTime, "Implementer step failed");
    }

    // Step 3: Syntactic Validation (no AI cost)
    const validation = await this.runSyntacticValidation(implementerResult.output);

    this.logger.info("FLOW chain completed", {
      total_tokens: totalTokens,
      total_cost_usd: totalCost,
      total_latency_ms: Date.now() - startTime,
      validation_passed: validation.valid,
    });

    return {
      success: true,
      tier: "flow",
      content: implementerResult.output,
      validation: {
        syntactic: validation,
      },
      steps,
      total_tokens: totalTokens,
      total_cost_usd: totalCost,
      total_latency_ms: Date.now() - startTime,
      credits_used: TIER_CREDITS.flow,
    };
  }

  /**
   * CRAFT Chain: Full power, maximum quality
   *
   * Step 1: Gemini 2.5 Flash - Deep context analysis
   * Step 2: Gemini 2.5 Pro - Strategy generation
   * Step 3: Claude 4.5 Opus - Implementation
   * Step 4: Syntactic Validation + Optional AI Validation
   */
  private async executeCraftChain(request: TierRequest, startTime: number): Promise<TierChainResponse> {
    const steps: ChainStepResult[] = [];
    let totalTokens = 0;
    let totalCost = 0;

    // Step 1: Deep Context Analysis with Gemini Flash
    this.logger.debug("CRAFT Step 1: Deep context analysis with Gemini Flash");
    const analyzerResult = await this.runAnalyzer(request, "craft");
    steps.push(analyzerResult);
    totalTokens += analyzerResult.tokens_input + analyzerResult.tokens_output;
    totalCost += analyzerResult.cost_usd;

    if (!analyzerResult.output) {
      return this.buildErrorResponse("craft", steps, totalTokens, totalCost, startTime, "Analyzer step failed");
    }

    // Step 2: Strategy Generation with Gemini Pro
    this.logger.debug("CRAFT Step 2: Strategy generation with Gemini Pro");
    const strategistResult = await this.runStrategist(request, analyzerResult.output);
    steps.push(strategistResult);
    totalTokens += strategistResult.tokens_input + strategistResult.tokens_output;
    totalCost += strategistResult.cost_usd;

    if (!strategistResult.output) {
      return this.buildErrorResponse("craft", steps, totalTokens, totalCost, startTime, "Strategist step failed");
    }

    // Step 3: Implementation with Claude Opus
    this.logger.debug("CRAFT Step 3: Implementation with Claude Opus");
    const implementerResult = await this.runImplementer(request, strategistResult.output, "craft");
    steps.push(implementerResult);
    totalTokens += implementerResult.tokens_input + implementerResult.tokens_output;
    totalCost += implementerResult.cost_usd;

    if (!implementerResult.output) {
      return this.buildErrorResponse("craft", steps, totalTokens, totalCost, startTime, "Implementer step failed");
    }

    // Step 4: Validation
    const syntacticValidation = await this.runSyntacticValidation(implementerResult.output);

    this.logger.info("CRAFT chain completed", {
      total_tokens: totalTokens,
      total_cost_usd: totalCost,
      total_latency_ms: Date.now() - startTime,
      validation_passed: syntacticValidation.valid,
    });

    return {
      success: true,
      tier: "craft",
      content: implementerResult.output,
      strategy: strategistResult.output,
      validation: {
        syntactic: syntacticValidation,
      },
      steps,
      total_tokens: totalTokens,
      total_cost_usd: totalCost,
      total_latency_ms: Date.now() - startTime,
      credits_used: TIER_CREDITS.craft,
    };
  }

  /**
   * Run the analyzer step (Gemini Flash)
   */
  private async runAnalyzer(request: TierRequest, tier: PerformanceTier): Promise<ChainStepResult> {
    const startTime = Date.now();
    const model = TIER_MODELS[tier].analyzer;
    const provider = new GeminiProvider(this.keys.gemini, model);

    const prompt = this.buildAnalyzerPrompt(request, tier);

    try {
      const response = await provider.generate(prompt, {
        temperature: 0.3,
        max_tokens: 2000,
        system_prompt: this.getAnalyzerSystemPrompt(tier),
      });

      return {
        step: "analyzer",
        provider: "gemini",
        model,
        output: response.success ? response.content : "",
        tokens_input: response.tokens_input,
        tokens_output: response.tokens_output,
        cost_usd: response.cost_usd,
        latency_ms: Date.now() - startTime,
      };
    } catch (error) {
      this.logger.error("Analyzer step failed", { error });
      return {
        step: "analyzer",
        provider: "gemini",
        model,
        output: "",
        tokens_input: 0,
        tokens_output: 0,
        cost_usd: 0,
        latency_ms: Date.now() - startTime,
      };
    }
  }

  /**
   * Run the strategist step (Gemini Pro) - CRAFT only
   */
  private async runStrategist(request: TierRequest, contextAnalysis: string): Promise<ChainStepResult> {
    const startTime = Date.now();
    const model = TIER_MODELS.craft.strategist;
    const provider = new GeminiProvider(this.keys.gemini, model);

    const prompt = this.buildStrategistPrompt(request, contextAnalysis);

    try {
      const response = await provider.generate(prompt, {
        temperature: 0.5,
        max_tokens: 4000,
        system_prompt: this.getStrategistSystemPrompt(),
      });

      return {
        step: "strategist",
        provider: "gemini",
        model,
        output: response.success ? response.content : "",
        tokens_input: response.tokens_input,
        tokens_output: response.tokens_output,
        cost_usd: response.cost_usd,
        latency_ms: Date.now() - startTime,
      };
    } catch (error) {
      this.logger.error("Strategist step failed", { error });
      return {
        step: "strategist",
        provider: "gemini",
        model,
        output: "",
        tokens_input: 0,
        tokens_output: 0,
        cost_usd: 0,
        latency_ms: Date.now() - startTime,
      };
    }
  }

  /**
   * Run the implementer step (Claude)
   */
  private async runImplementer(
    request: TierRequest,
    strategyOrContext: string,
    tier: PerformanceTier
  ): Promise<ChainStepResult> {
    const startTime = Date.now();
    const model = TIER_MODELS[tier].implementer;
    const provider = new ClaudeProvider(this.keys.claude, model);

    const prompt = this.buildImplementerPrompt(request, strategyOrContext, tier);

    try {
      const response = await provider.generate(prompt, {
        temperature: 0.7,
        max_tokens: 8000,
        system_prompt: request.system_prompt || this.getImplementerSystemPrompt(tier),
      });

      return {
        step: "implementer",
        provider: "claude",
        model,
        output: response.success ? response.content : "",
        tokens_input: response.tokens_input,
        tokens_output: response.tokens_output,
        cost_usd: response.cost_usd,
        latency_ms: Date.now() - startTime,
      };
    } catch (error) {
      this.logger.error("Implementer step failed", { error });
      return {
        step: "implementer",
        provider: "claude",
        model,
        output: "",
        tokens_input: 0,
        tokens_output: 0,
        cost_usd: 0,
        latency_ms: Date.now() - startTime,
      };
    }
  }

  /**
   * Run syntactic validation (no AI cost)
   */
  private async runSyntacticValidation(content: string): Promise<{ valid: boolean; errors?: string[] }> {
    const errors: string[] = [];

    // Try to extract and validate JSON
    try {
      const jsonMatch = content.match(/```(?:json)?\s*([\s\S]*?)\s*```/);
      if (jsonMatch) {
        JSON.parse(jsonMatch[1]);
      } else if (content.trim().startsWith("{")) {
        JSON.parse(content);
      }
    } catch {
      // JSON parsing failed, but that's okay if it's not JSON content
    }

    // Check for common PHP syntax issues
    if (content.includes("<?php")) {
      // Basic PHP syntax checks
      const phpContent = content.match(/<\?php[\s\S]*?\?>/g) || [];
      for (const block of phpContent) {
        if ((block.match(/\{/g) || []).length !== (block.match(/\}/g) || []).length) {
          errors.push("Potential unbalanced braces in PHP code");
        }
        if ((block.match(/\(/g) || []).length !== (block.match(/\)/g) || []).length) {
          errors.push("Potential unbalanced parentheses in PHP code");
        }
      }
    }

    // Check for common JavaScript syntax issues
    if (content.includes("function") || content.includes("const") || content.includes("let")) {
      const jsBlocks = content.match(/```(?:javascript|js)?\s*([\s\S]*?)\s*```/g) || [];
      for (const block of jsBlocks) {
        if ((block.match(/\{/g) || []).length !== (block.match(/\}/g) || []).length) {
          errors.push("Potential unbalanced braces in JavaScript code");
        }
      }
    }

    return {
      valid: errors.length === 0,
      errors: errors.length > 0 ? errors : undefined,
    };
  }

  /**
   * Build analyzer prompt
   */
  private buildAnalyzerPrompt(request: TierRequest, tier: PerformanceTier): string {
    const contextSummary = request.context ? JSON.stringify(request.context, null, 2) : "No context provided";

    if (tier === "flow") {
      return `
Analyze this WordPress-related request quickly and extract key information.

## Site Context
${contextSummary}

## User Request
${request.prompt}

## Your Task
Provide a brief analysis containing:
1. Main intent (what the user wants to achieve)
2. Key entities mentioned (plugins, themes, pages, etc.)
3. Technical requirements
4. Potential challenges or considerations

Keep your response concise and actionable.
`;
    }

    // CRAFT - more thorough analysis
    return `
Perform a deep analysis of this WordPress request for a complex implementation.

## Site Context
${contextSummary}

## User Request
${request.prompt}

## Your Task
Provide a comprehensive analysis:

1. **Intent Analysis**
   - Primary goal
   - Secondary goals
   - Implicit requirements

2. **Technical Scope**
   - WordPress components involved (themes, plugins, CPT, etc.)
   - Database considerations
   - Frontend/backend separation

3. **Dependencies**
   - Required plugins
   - Theme compatibility
   - PHP/JavaScript requirements

4. **Risk Assessment**
   - Potential conflicts
   - Performance implications
   - Security considerations

5. **Implementation Complexity**
   - Estimated steps
   - Critical path items
   - Optional enhancements
`;
  }

  /**
   * Build strategist prompt (CRAFT only)
   */
  private buildStrategistPrompt(request: TierRequest, contextAnalysis: string): string {
    return `
Based on the following context analysis, create a detailed implementation strategy.

## Context Analysis
${contextAnalysis}

## Original Request
${request.prompt}

## Your Task
Create a comprehensive implementation strategy:

1. **Architecture Overview**
   - High-level approach
   - Component breakdown
   - Data flow

2. **Implementation Steps**
   - Ordered list of specific actions
   - For each step: what to do, why, and how

3. **Code Structure**
   - Files to create/modify
   - Functions/classes needed
   - Hooks to use

4. **Integration Points**
   - How components connect
   - API endpoints if needed
   - Event handlers

5. **Testing Strategy**
   - Key test cases
   - Edge cases to handle

6. **Rollback Plan**
   - How to undo changes if needed
`;
  }

  /**
   * Build implementer prompt
   */
  private buildImplementerPrompt(request: TierRequest, strategyOrContext: string, tier: PerformanceTier): string {
    if (tier === "flow") {
      return `
Implement the following WordPress request based on the context analysis.

## Context Analysis
${strategyOrContext}

## Original Request
${request.prompt}

## Instructions
- Provide working code or clear step-by-step instructions
- Include all necessary code snippets
- Explain where to place each piece of code
- Consider user skill level in explanations

Respond with a JSON object:
{
  "intent": "action_type or conversation",
  "confidence": 0.0-1.0,
  "actions": [{"type": "action_name", "params": {...}, "status": "pending|ready"}],
  "message": "Your response to the user"
}
`;
    }

    // CRAFT - follow detailed strategy
    return `
Implement the following WordPress request based on the detailed strategy provided.

## Implementation Strategy
${strategyOrContext}

## Original Request
${request.prompt}

## Instructions
- Follow the strategy precisely
- Provide production-ready code
- Include comprehensive error handling
- Add inline documentation for complex logic
- Consider performance and security best practices

Respond with a JSON object:
{
  "intent": "action_type or conversation",
  "confidence": 0.0-1.0,
  "actions": [{"type": "action_name", "params": {...}, "status": "pending|ready"}],
  "message": "Your response to the user"
}
`;
  }

  /**
   * Get analyzer system prompt
   */
  private getAnalyzerSystemPrompt(tier: PerformanceTier): string {
    if (tier === "flow") {
      return "You are a WordPress expert assistant. Analyze requests quickly and extract actionable information.";
    }
    return "You are a senior WordPress architect. Perform thorough analysis of complex WordPress requirements.";
  }

  /**
   * Get strategist system prompt
   */
  private getStrategistSystemPrompt(): string {
    return `You are a senior WordPress solutions architect. Create detailed, production-ready implementation strategies.
Focus on:
- Clean architecture
- WordPress best practices
- Maintainability
- Performance
- Security`;
  }

  /**
   * Get implementer system prompt
   */
  private getImplementerSystemPrompt(tier: PerformanceTier): string {
    const basePrompt = `You are Creator, an expert WordPress AI assistant. You help users build and modify WordPress sites.

Always respond in valid JSON format with:
- intent: the type of action or "conversation"
- confidence: 0.0-1.0 score
- actions: array of actions to perform
- message: your response to the user in their language`;

    if (tier === "craft") {
      return `${basePrompt}

You are operating in CRAFT mode (maximum quality). Provide:
- Production-ready, well-documented code
- Comprehensive error handling
- Performance optimizations
- Security best practices
- Detailed explanations for complex logic`;
    }

    return `${basePrompt}

You are operating in FLOW mode (balanced speed/quality). Provide:
- Working code with essential documentation
- Standard error handling
- Clear, concise explanations`;
  }

  /**
   * Build error response
   */
  private buildErrorResponse(
    tier: PerformanceTier,
    steps: ChainStepResult[],
    totalTokens: number,
    totalCost: number,
    startTime: number,
    error: string
  ): TierChainResponse {
    return {
      success: false,
      tier,
      content: "",
      steps,
      total_tokens: totalTokens,
      total_cost_usd: totalCost,
      total_latency_ms: Date.now() - startTime,
      credits_used: 0,
      error,
      error_code: "CHAIN_STEP_FAILED",
    };
  }
}
