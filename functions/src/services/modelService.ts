/**
 * @fileoverview Simple Model Service for Creator AI Proxy
 * @module services/modelService
 *
 * @description
 * Handles AI model calls with automatic fallback.
 * Primary model: User's choice (Gemini or Claude)
 * Fallback: The other model if primary fails
 */

import { GeminiProvider } from "../providers/gemini";
import { ClaudeProvider } from "../providers/claude";
import {
  AIModel,
  ModelRequest,
  ModelResponse,
  MODEL_IDS,
  getFallbackModel,
} from "../types/ModelConfig";
import { Logger } from "../lib/logger";

/**
 * Default system prompt for Creator AI - Orchestration Engine
 *
 * This prompt instructs the AI to follow the 4-step orchestration process:
 * Discovery -> Strategy -> Implementation -> Verification
 *
 * The AI can answer questions, request documentation, create plans, and execute PHP code.
 */
const DEFAULT_SYSTEM_PROMPT = `You are Creator, an AI-powered WordPress development assistant. Your role is to help users build, configure, and manage WordPress websites by generating and executing PHP code directly.

## CORE PRINCIPLES

1. **You can do ANYTHING a WordPress expert can do** - there are no hardcoded limits
2. **You generate PHP code** that gets executed on WordPress when actions are needed
3. **You learn from documentation** - request plugin docs when you need specific API info
4. **You follow a 4-step process**: Discovery -> Strategy -> Implementation -> Verification
5. **For simple questions, just answer them** - don't generate code for informational queries

## RESPONSE FORMAT - CRITICAL

You MUST respond with ONLY a valid JSON object (no markdown, no code blocks, just raw JSON):

{
  "step": "discovery | strategy | implementation | verification",
  "type": "question | plan | execute | verify | complete | error | request_docs",
  "status": "Short status for UI (e.g., 'Analyzing...', 'Executing 1/3...')",
  "message": "Full message for the user",
  "data": {},
  "requires_confirmation": false,
  "continue_automatically": true
}

## RESPONSE TYPES

### type: "complete" - For simple questions or task completion
Use this when the user asks a question that can be answered from context, or when a task is complete.

Example - answering a question:
{
  "step": "discovery",
  "type": "complete",
  "status": "Ready",
  "message": "Il tuo sito utilizza WordPress 6.4.2, con PHP 8.2.0 e MySQL 8.0. Il tema attivo e' flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor flavor 2.1.0.",
  "data": {},
  "requires_confirmation": false,
  "continue_automatically": false
}

### type: "question" - When you need clarification
{
  "step": "discovery",
  "type": "question",
  "status": "Waiting for clarification...",
  "message": "Per creare la landing page ho bisogno di sapere:\\n\\n1. Vuoi usare Elementor o l'editor classico?\\n2. Hai gia' i contenuti o li genero io?",
  "data": {},
  "requires_confirmation": false,
  "continue_automatically": false
}

### type: "plan" - When presenting an action plan
{
  "step": "strategy",
  "type": "plan",
  "status": "Plan ready",
  "message": "Ecco il piano per creare la homepage:",
  "data": {
    "actions": [
      { "index": 1, "description": "Creare pagina base" },
      { "index": 2, "description": "Aggiungere contenuto" }
    ],
    "total_actions": 2
  },
  "requires_confirmation": true,
  "continue_automatically": false
}

### type: "execute" - When executing PHP code
{
  "step": "implementation",
  "type": "execute",
  "status": "Executing...",
  "message": "Sto creando la pagina...",
  "data": {
    "code": "$page_id = wp_insert_post(['post_title' => 'Test', 'post_type' => 'page', 'post_status' => 'publish']); return ['success' => true, 'page_id' => $page_id];",
    "action_index": 1,
    "action_total": 1,
    "action_description": "Creazione pagina"
  },
  "requires_confirmation": false,
  "continue_automatically": true
}

### type: "request_docs" - When you need plugin documentation
{
  "step": "discovery",
  "type": "request_docs",
  "status": "Fetching documentation...",
  "message": "Recupero la documentazione necessaria...",
  "data": {
    "plugins_needed": ["elementor", "woocommerce"],
    "reason": "Per creare la pagina con Elementor",
    "task": "Create a landing page with Hero section, Features grid, and Pricing table using Elementor"
  },
  "requires_confirmation": false,
  "continue_automatically": true
}

IMPORTANT: The "task" field in data MUST contain a clear, complete description of what you understood the user wants to accomplish. This is your interpretation of the full conversation, not just the last message. When you receive the documentation, you will use this task description to proceed.

### type: "error" - When something goes wrong
{
  "step": "implementation",
  "type": "error",
  "status": "Error occurred",
  "message": "Si e' verificato un errore: [descrizione]",
  "data": {
    "error_code": "ERROR_CODE",
    "recoverable": true,
    "suggestion": "Suggerimento per risolvere"
  },
  "requires_confirmation": false,
  "continue_automatically": false
}

## CODE EXECUTION RULES

When generating PHP code (type: "execute"):
1. NO opening '<?php' tags
2. NO dangerous functions (system, exec, shell_exec, passthru, eval, etc.)
3. Must return a result array: return ['success' => true/false, 'data' => ...]
4. Use WordPress functions: wp_insert_post, get_posts, update_option, etc.
5. $wpdb available for database queries
6. $context array contains previous execution results

## LANGUAGE

ALWAYS respond in the SAME LANGUAGE as the user's message:
- Italian message -> respond in Italian
- English message -> respond in English

## KEY DECISION RULES

1. **Simple questions about the site** (versions, plugins, theme) -> type: "complete" with answer
2. **Clarification needed** -> type: "question"
3. **Complex task requiring multiple steps** -> type: "plan" first
4. **Action to perform** -> type: "execute" with PHP code
5. **Need plugin-specific API info** -> type: "request_docs"
6. **Error occurred** -> type: "error"

Return ONLY the JSON object. No explanation, no markdown, just JSON.`;

/**
 * Build context-aware system prompt
 *
 * @param basePrompt - Base system prompt
 * @param context - WordPress context from plugin
 * @returns Enhanced system prompt with context
 */
function buildContextAwarePrompt(
  basePrompt: string,
  context?: Record<string, unknown>
): string {
  if (!context || Object.keys(context).length === 0) {
    return basePrompt;
  }

  // Build WordPress environment summary
  // ContextLoader sends: wordpress, theme, plugins, environment
  const wpInfo = context.wordpress as Record<string, unknown> | undefined;
  const envInfo = context.environment as Record<string, unknown> | undefined;
  const themeInfo = context.theme as Record<string, unknown> | undefined;
  const pluginsInfo = context.plugins as Array<Record<string, unknown>> | undefined;

  let envSummary = "\n\n## CURRENT WORDPRESS ENVIRONMENT:";

  if (wpInfo) {
    envSummary += `\n- WordPress Version: ${wpInfo.version || "unknown"}`;
    if (wpInfo.language) envSummary += `\n- Language: ${wpInfo.language}`;
    if (wpInfo.is_multisite) envSummary += `\n- Multisite: Yes`;
    if (wpInfo.site_url) envSummary += `\n- Site URL: ${wpInfo.site_url}`;
  }

  if (envInfo) {
    if (envInfo.php_version) envSummary += `\n- PHP Version: ${envInfo.php_version}`;
    if (envInfo.mysql_version) envSummary += `\n- MySQL Version: ${envInfo.mysql_version}`;
    if (envInfo.memory_limit) envSummary += `\n- Memory Limit: ${envInfo.memory_limit}`;
    if (envInfo.debug_mode) envSummary += `\n- Debug Mode: Enabled`;
  }

  if (themeInfo) {
    envSummary += `\n- Active Theme: ${themeInfo.name || "unknown"}`;
    if (themeInfo.version) envSummary += ` (v${themeInfo.version})`;
    if (themeInfo.is_child && themeInfo.parent) {
      const parentInfo = themeInfo.parent as Record<string, unknown>;
      envSummary += ` - Child of: ${parentInfo.name || "unknown"}`;
    }
  }

  if (pluginsInfo && Array.isArray(pluginsInfo) && pluginsInfo.length > 0) {
    envSummary += "\n- Active Plugins:";
    pluginsInfo.forEach((plugin) => {
      envSummary += `\n  - ${plugin.name}`;
      if (plugin.version) envSummary += ` (v${plugin.version})`;
    });
  }

  envSummary += "\n\nUSE THIS INFORMATION to answer questions about the site. When asked about WordPress version, PHP version, theme, plugins, etc., use the data above to provide accurate answers.";

  // Debug: Log what context was added to the prompt
  console.log("[DEBUG] Context environment summary:", envSummary);

  return basePrompt + envSummary;
}

/**
 * Provider keys configuration
 */
export interface ModelServiceKeys {
  gemini: string;
  claude: string;
}

/**
 * Model Service
 *
 * @class ModelService
 *
 * @description
 * Simple service that calls the selected AI model with automatic fallback.
 */
export class ModelService {
  private keys: ModelServiceKeys;
  private logger: Logger;

  constructor(keys: ModelServiceKeys, logger: Logger) {
    this.keys = keys;
    this.logger = logger.child({ service: "modelService" });
  }

  /**
   * Generate content using the selected model with fallback
   */
  async generate(request: ModelRequest): Promise<ModelResponse> {
    const startTime = Date.now();
    const primaryModel = request.model;
    const fallbackModel = getFallbackModel(primaryModel);

    // Debug: Log context received
    this.logger.info("Starting model generation", {
      model: primaryModel,
      prompt_length: request.prompt.length,
      has_context: !!request.context,
      context_keys: request.context ? Object.keys(request.context) : [],
    });

    // Debug: Log detailed context if present
    if (request.context) {
      this.logger.debug("Context received", {
        wordpress: request.context.wordpress ? "present" : "missing",
        theme: request.context.theme ? "present" : "missing",
        plugins: request.context.plugins ? "present" : "missing",
        environment: request.context.environment ? "present" : "missing",
      });
    }

    // Try primary model
    const primaryResult = await this.callModel(primaryModel, request);

    if (primaryResult.success) {
      this.logger.info("Primary model succeeded", {
        model: primaryModel,
        tokens: primaryResult.total_tokens,
        latency_ms: primaryResult.latency_ms,
      });

      return {
        ...primaryResult,
        used_fallback: false,
        latency_ms: Date.now() - startTime,
      };
    }

    // Primary failed, try fallback
    this.logger.warn("Primary model failed, trying fallback", {
      primary: primaryModel,
      fallback: fallbackModel,
      error: primaryResult.error,
    });

    const fallbackResult = await this.callModel(fallbackModel, request);

    if (fallbackResult.success) {
      this.logger.info("Fallback model succeeded", {
        model: fallbackModel,
        tokens: fallbackResult.total_tokens,
        latency_ms: fallbackResult.latency_ms,
      });

      return {
        ...fallbackResult,
        used_fallback: true,
        latency_ms: Date.now() - startTime,
      };
    }

    // Both failed
    this.logger.error("Both models failed", {
      primary: primaryModel,
      fallback: fallbackModel,
      primary_error: primaryResult.error,
      fallback_error: fallbackResult.error,
    });

    return {
      success: false,
      content: "",
      model: primaryModel,
      model_id: MODEL_IDS[primaryModel],
      used_fallback: true,
      tokens_input: 0,
      tokens_output: 0,
      total_tokens: 0,
      cost_usd: 0,
      latency_ms: Date.now() - startTime,
      error: `Both models failed. Primary: ${primaryResult.error}. Fallback: ${fallbackResult.error}`,
      error_code: "ALL_MODELS_FAILED",
    };
  }

  /**
   * Call a specific model
   */
  private async callModel(
    model: AIModel,
    request: ModelRequest
  ): Promise<ModelResponse> {
    const startTime = Date.now();
    const modelId = MODEL_IDS[model];

    // Use default system prompt if none provided
    const baseSystemPrompt = request.system_prompt || DEFAULT_SYSTEM_PROMPT;

    // Enhance system prompt with WordPress context if available
    const systemPrompt = buildContextAwarePrompt(baseSystemPrompt, request.context);

    // Also enhance user prompt with context summary for better visibility
    const userPrompt = this.buildUserPromptWithContext(request.prompt, request.context);

    try {
      let response;

      if (model === "gemini") {
        const provider = new GeminiProvider(this.keys.gemini, modelId);
        response = await provider.generate(userPrompt, {
          temperature: request.temperature ?? 0.7,
          max_tokens: request.max_tokens ?? 8000,
          system_prompt: systemPrompt,
          files: request.files,
          conversation_history: request.conversation_history,
        });
      } else {
        const provider = new ClaudeProvider(this.keys.claude, modelId);
        response = await provider.generate(userPrompt, {
          temperature: request.temperature ?? 0.7,
          max_tokens: request.max_tokens ?? 8000,
          system_prompt: systemPrompt,
          files: request.files,
          conversation_history: request.conversation_history,
        });
      }

      if (response.success) {
        return {
          success: true,
          content: response.content,
          model,
          model_id: modelId,
          used_fallback: false,
          tokens_input: response.tokens_input,
          tokens_output: response.tokens_output,
          total_tokens: response.total_tokens,
          cost_usd: response.cost_usd,
          latency_ms: Date.now() - startTime,
        };
      }

      return {
        success: false,
        content: "",
        model,
        model_id: modelId,
        used_fallback: false,
        tokens_input: 0,
        tokens_output: 0,
        total_tokens: 0,
        cost_usd: 0,
        latency_ms: Date.now() - startTime,
        error: response.error || "Unknown error",
        error_code: response.error_code || "UNKNOWN_ERROR",
      };
    } catch (error) {
      this.logger.error("Model call failed", {
        model,
        error: error instanceof Error ? error.message : "Unknown error",
      });

      return {
        success: false,
        content: "",
        model,
        model_id: modelId,
        used_fallback: false,
        tokens_input: 0,
        tokens_output: 0,
        total_tokens: 0,
        cost_usd: 0,
        latency_ms: Date.now() - startTime,
        error: error instanceof Error ? error.message : "Unknown error",
        error_code: "PROVIDER_ERROR",
      };
    }
  }

  /**
   * Build user prompt with context summary prepended
   *
   * This ensures the AI definitely sees the context information
   * even if there are issues with the system prompt.
   *
   * @param prompt - Original user prompt
   * @param context - WordPress context
   * @returns Enhanced prompt with context
   */
  private buildUserPromptWithContext(
    prompt: string,
    context?: Record<string, unknown>
  ): string {
    if (!context || Object.keys(context).length === 0) {
      return prompt;
    }

    const wpInfo = context.wordpress as Record<string, unknown> | undefined;
    const envInfo = context.environment as Record<string, unknown> | undefined;
    const themeInfo = context.theme as Record<string, unknown> | undefined;
    const pluginsInfo = context.plugins as Array<Record<string, unknown>> | undefined;

    // Build a compact context header
    let contextHeader = "[SITE INFO: ";
    const parts: string[] = [];

    if (wpInfo?.version) {
      parts.push(`WP ${wpInfo.version}`);
    }
    if (envInfo?.php_version) {
      parts.push(`PHP ${envInfo.php_version}`);
    }
    if (themeInfo?.name) {
      parts.push(`Theme: ${themeInfo.name}`);
    }
    if (pluginsInfo && Array.isArray(pluginsInfo)) {
      parts.push(`${pluginsInfo.length} plugins`);
    }

    if (parts.length === 0) {
      return prompt;
    }

    contextHeader += parts.join(" | ") + "]\n\n";

    this.logger.debug("User prompt enhanced with context header", {
      context_header: contextHeader,
    });

    return contextHeader + prompt;
  }
}
