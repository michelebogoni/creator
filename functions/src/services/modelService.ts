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

AVAILABLE ACTION TYPES:

1. Content Management:
- "create_page": Create a WordPress page
  params: { "title": "string", "content": "HTML", "status": "draft|publish", "use_elementor": true, "elementor_data": "[...]" }
- "create_post": Create a WordPress post
  params: { "title": "string", "content": "HTML", "status": "draft|publish", "category": [ids] }
- "update_post" / "update_page": Update existing content
  params: { "post_id": number, "title": "string", "content": "HTML", "status": "string" }
- "delete_post": Delete a post/page
  params: { "post_id": number, "force": boolean }

2. Elementor:
- "add_elementor_widget": Add widget to an existing Elementor page
  params: { "post_id": number, "widget_type": "string", "settings": {...} }

3. Plugins:
- "create_plugin": Create a custom plugin
  params: { "name": "string", "slug": "string", "description": "string", "code": "PHP code", "activate": true }
- "activate_plugin": Activate a plugin
  params: { "plugin_slug": "string" }
- "deactivate_plugin": Deactivate a plugin
  params: { "plugin_slug": "string" }

4. Files:
- "read_file": Read a file
  params: { "file_path": "string" }
- "write_file": Write/create a file
  params: { "file_path": "string", "content": "string" }

5. Database:
- "db_query": Execute SELECT query
  params: { "query": "SELECT...", "limit": 100 }
- "db_insert": Insert row
  params: { "table": "string", "data": {...} }
- "db_update": Update rows
  params: { "table": "string", "data": {...}, "where": {...} }

6. Settings:
- "update_option": Update WordPress option
  params: { "option_name": "string", "option_value": "any" }
- "update_meta": Update post meta
  params: { "object_id": number, "meta_key": "string", "meta_value": "any" }

7. Analysis:
- "analyze_code": Analyze a code file
  params: { "file_path": "string" }
- "analyze_plugin": Analyze a plugin
  params: { "plugin_slug": "string" }

ELEMENTOR PAGE CREATION:
When asked to create an Elementor page, use "create_page" with:
- use_elementor: true
- elementor_data: A JSON STRING containing Elementor widget structure

Elementor data structure example:
[
  {
    "id": "unique_id",
    "elType": "section",
    "settings": { "structure": "20" },
    "elements": [
      {
        "id": "column_id",
        "elType": "column",
        "settings": { "_column_size": 100 },
        "elements": [
          {
            "id": "widget_id",
            "elType": "widget",
            "widgetType": "heading",
            "settings": {
              "title": "My Heading",
              "align": "center",
              "title_color": "#1F2F46"
            }
          }
        ]
      }
    ]
  }
]

IMPORTANT RULES:
1. ALWAYS respond in the user's language
2. Generate COMPLETE, WORKING content - don't just describe what you would do
3. For Elementor pages, generate the FULL elementor_data with ALL sections requested
4. Use realistic placeholder content (Lorem ipsum is OK but real-looking content is better)
5. Include proper styling in Elementor settings (colors, fonts, spacing)
6. IDs must be unique strings (use random alphanumeric like "abc123", "xyz789")

Widget types for Elementor: heading, text-editor, image, button, icon-box, image-box, testimonial, form, google_maps, spacer, divider, icon-list`;


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
