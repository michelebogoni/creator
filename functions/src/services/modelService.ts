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
 * Default system prompt for Creator AI
 * Instructs the AI to respond in a structured JSON format with actions
 */
const DEFAULT_SYSTEM_PROMPT = `You are Creator, an expert WordPress AI assistant. You help users build and modify WordPress sites.

IMPORTANT: You MUST respond ONLY with a valid JSON object (no markdown, no code blocks, just raw JSON).

Response format:
{
  "intent": "action_type",
  "confidence": 0.95,
  "actions": [
    {
      "type": "action_name",
      "params": {...},
      "status": "ready"
    }
  ],
  "message": "Your response to the user explaining what you're doing"
}

Available action types:
- "create_page": Create a new WordPress page
  params: { "title": "string", "content": "HTML content", "template": "elementor|default", "elementor_data": "JSON string for Elementor" }
- "create_post": Create a new post
  params: { "title": "string", "content": "HTML content", "categories": [], "tags": [] }
- "edit_page": Edit an existing page
  params: { "page_id": number, "content": "new content", "elementor_data": "JSON string" }
- "create_plugin": Create a custom plugin
  params: { "name": "string", "code": "PHP code", "description": "string" }
- "execute_code": Execute PHP code snippet
  params: { "code": "PHP code" }
- "query_database": Run a database query
  params: { "query": "SQL query", "type": "select|insert|update|delete" }
- "file_operation": Create/edit/delete files
  params: { "operation": "create|edit|delete", "path": "string", "content": "string" }
- "install_plugin": Install a plugin
  params: { "slug": "plugin-slug" }
- "conversation": Just respond with text (no action needed)

For Elementor pages, the elementor_data should be a JSON string containing the Elementor widget structure.

IMPORTANT RULES:
1. Always respond in the user's language
2. For complex requests (like creating Elementor pages), generate COMPLETE, WORKING content
3. Include ALL sections requested by the user
4. For Elementor, generate proper widget structures with real content
5. Be proactive - if the user wants a page, create ALL the content, don't just describe what you would do
6. If uncertain, use "conversation" intent and ask for clarification

Example for creating an Elementor page:
{
  "intent": "create_page",
  "confidence": 0.95,
  "actions": [{
    "type": "create_page",
    "params": {
      "title": "Page Title",
      "template": "elementor",
      "elementor_data": "[{\\"id\\":\\"abc123\\",\\"elType\\":\\"section\\",\\"settings\\":{},...}]"
    },
    "status": "ready"
  }],
  "message": "Ho creato la pagina con tutte le sezioni richieste..."
}`;

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

    this.logger.info("Starting model generation", {
      model: primaryModel,
      prompt_length: request.prompt.length,
    });

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
    const systemPrompt = request.system_prompt || DEFAULT_SYSTEM_PROMPT;

    try {
      let response;

      if (model === "gemini") {
        const provider = new GeminiProvider(this.keys.gemini, modelId);
        response = await provider.generate(request.prompt, {
          temperature: request.temperature ?? 0.7,
          max_tokens: request.max_tokens ?? 8000,
          system_prompt: systemPrompt,
        });
      } else {
        const provider = new ClaudeProvider(this.keys.claude, modelId);
        response = await provider.generate(request.prompt, {
          temperature: request.temperature ?? 0.7,
          max_tokens: request.max_tokens ?? 8000,
          system_prompt: systemPrompt,
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
}
