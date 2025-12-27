/**
 * @fileoverview Plugin Documentation Research Service
 * @module services/pluginDocsResearch
 *
 * @description
 * Uses AI to research comprehensive plugin documentation.
 * The AI searches for official documentation and extracts:
 * - API functions with signatures and descriptions
 * - Code examples for common use cases
 * - Best practices and guidelines
 * - Data structures (JSON schemas, meta keys, etc.)
 * - Component/widget types with settings
 *
 * NO HARDCODED FALLBACKS - All documentation is dynamically researched.
 */

import { ModelService, ModelServiceKeys } from "./modelService";
import { Logger } from "../lib/logger";
import { savePluginDocs, getPluginDocs, normalizePluginVersion } from "../lib/firestore";
import {
  ResearchPluginDocsRequest,
  ResearchPluginDocsResponse,
} from "../types/PluginDocs";

/**
 * Comprehensive system prompt for plugin documentation research
 *
 * This prompt instructs the AI to research and provide COMPLETE documentation
 * including code examples, best practices, and data structures.
 */
const RESEARCH_SYSTEM_PROMPT = `You are a WordPress plugin documentation researcher and technical writer.
Your task is to research and provide COMPREHENSIVE documentation for WordPress plugins.

IMPORTANT: You MUST respond ONLY with a valid JSON object (no markdown, no code blocks, just raw JSON).

Response format:
{
  "docs_url": "https://official-docs-url...",
  "functions_url": "https://functions-reference-url...",
  "api_reference": "https://api-reference-url...",
  "description": "Detailed description of what this plugin does and its main purpose",
  "main_functions": [
    "function_name( $param1, $param2 ) - Description of what this function does",
    "another_function( $param ) - Another description"
  ],
  "code_examples": [
    "// Example 1: Basic usage\\n$result = function_name( $param );\\nif ( $result ) {\\n    // Handle success\\n}",
    "// Example 2: Advanced usage\\n// Full working code example..."
  ],
  "best_practices": [
    "Always check return values with is_wp_error()",
    "Use specific hook priorities when needed",
    "Another best practice..."
  ],
  "data_structures": [
    "Meta key '_plugin_data': JSON structure { 'key': 'value', 'nested': { } }",
    "Database table structure: column1 (type), column2 (type)..."
  ],
  "component_types": [
    {
      "name": "component_name",
      "type": "widget|element|block",
      "settings": {
        "setting_name": "type and description",
        "another_setting": "type and description"
      },
      "example": "{ 'elType': 'widget', 'widgetType': 'heading', 'settings': { 'title': 'Hello' } }"
    }
  ],
  "version_notes": ["Important notes about this version"]
}

RULES FOR COMPREHENSIVE DOCUMENTATION:

1. **docs_url**: The main official documentation page
2. **functions_url**: Functions/methods reference page if available
3. **api_reference**: Full API documentation URL

4. **description**:
   - What the plugin does
   - Main use cases
   - How it integrates with WordPress

5. **main_functions**:
   - NO LIMIT on number of functions
   - Include ALL important functions/methods
   - Format: "function_name( $param1, $param2 ) - Description"
   - Include parameter types when known
   - Include return type when known

6. **code_examples**:
   - Provide COMPLETE, WORKING code examples
   - Cover common use cases
   - Include error handling
   - Show integration patterns
   - For page builders: include programmatic content creation examples

7. **best_practices**:
   - Security considerations
   - Performance tips
   - Common pitfalls to avoid
   - Recommended patterns

8. **data_structures**:
   - Database table structures
   - Meta key formats and JSON schemas
   - Post meta fields used
   - Options structure
   - For page builders: COMPLETE JSON structure for pages/elements

9. **component_types** (for page builders like Elementor, Beaver Builder, etc.):
   - ALL available widget/element/block types
   - Their settings with types and allowed values
   - JSON example for programmatic creation

SPECIFIC INSTRUCTIONS FOR PAGE BUILDERS:

For Elementor specifically, document:
- The _elementor_data meta key JSON structure
- Section, Column, Container element types
- ALL widget types (heading, text-editor, image, button, video, icon, etc.)
- Settings for each widget (title, align, size, color, link, etc.)
- Complete JSON example for creating a page programmatically

For WooCommerce specifically, document:
- Product creation functions
- Order management
- Cart operations
- All hooks and filters

For ACF (Advanced Custom Fields) specifically, document:
- Field group registration
- Field types and settings
- get_field(), update_field() with all options
- Repeater and flexible content handling

BE THOROUGH. The AI using this documentation needs to be able to:
1. Understand the plugin's capabilities
2. Write working code without additional research
3. Follow best practices automatically
4. Handle edge cases properly

If official documentation is limited, use your knowledge to provide comprehensive documentation based on the plugin's known behavior and common usage patterns.`;

/**
 * Plugin Documentation Research Service
 *
 * Provides comprehensive AI-powered documentation research for any WordPress plugin.
 * No hardcoded fallbacks - all documentation is dynamically generated.
 */
export class PluginDocsResearchService {
  private modelService: ModelService;
  private logger: Logger;

  constructor(keys: ModelServiceKeys, logger: Logger) {
    this.modelService = new ModelService(keys, logger);
    this.logger = logger.child({ service: "pluginDocsResearch" });
  }

  /**
   * Research plugin documentation using AI
   *
   * @param request Research request with plugin details
   * @returns Research response with comprehensive documentation
   */
  async research(
    request: ResearchPluginDocsRequest
  ): Promise<ResearchPluginDocsResponse> {
    const { plugin_slug, plugin_name, plugin_uri } = request;

    // Normalize version to X.Y format
    const plugin_version = normalizePluginVersion(request.plugin_version);

    this.logger.info("Starting comprehensive plugin docs research", {
      plugin_slug,
      rawVersion: request.plugin_version,
      normalizedVersion: plugin_version,
    });

    // Check cache first (using normalized version)
    const cached = await getPluginDocs(plugin_slug, plugin_version);
    if (cached && this.isDocumentationComplete(cached)) {
      this.logger.info("Complete plugin docs found in cache", { plugin_slug });
      return {
        success: true,
        data: {
          docs_url: cached.docs_url,
          functions_url: cached.functions_url,
          main_functions: cached.main_functions,
          api_reference: cached.api_reference,
          version_notes: cached.version_notes,
          description: cached.description,
          code_examples: cached.code_examples,
          best_practices: cached.best_practices,
          data_structures: cached.data_structures,
          component_types: cached.component_types,
        },
      };
    }

    // Build comprehensive research prompt
    const prompt = this.buildResearchPrompt(
      plugin_slug,
      plugin_version,
      plugin_name,
      plugin_uri
    );

    try {
      // Call AI to research - use Claude for better code generation
      const response = await this.modelService.generate({
        model: "claude",
        prompt,
        system_prompt: RESEARCH_SYSTEM_PROMPT,
        temperature: 0.2, // Lower temperature for factual responses
        max_tokens: 16000, // Allow large responses for comprehensive docs
      });

      if (!response.success) {
        this.logger.error("AI research failed", {
          plugin_slug,
          error: response.error,
        });
        return {
          success: false,
          error: response.error || "AI research failed",
        };
      }

      // Parse AI response
      const parsed = this.parseResearchResponse(response.content);
      if (!parsed) {
        this.logger.error("Failed to parse AI response", {
          plugin_slug,
          content: response.content.substring(0, 1000),
        });
        return {
          success: false,
          error: "Failed to parse AI research response",
        };
      }

      // Save comprehensive docs to cache
      await savePluginDocs({
        plugin_slug,
        plugin_version,
        docs_url: parsed.docs_url,
        functions_url: parsed.functions_url,
        main_functions: parsed.main_functions,
        api_reference: parsed.api_reference,
        version_notes: parsed.version_notes,
        description: parsed.description,
        code_examples: parsed.code_examples,
        best_practices: parsed.best_practices,
        data_structures: parsed.data_structures,
        component_types: parsed.component_types,
        source: "ai_research",
      });

      this.logger.info("Comprehensive plugin docs researched and cached", {
        plugin_slug,
        plugin_version,
        docs_url: parsed.docs_url,
        functions_count: parsed.main_functions.length,
        examples_count: parsed.code_examples?.length || 0,
        practices_count: parsed.best_practices?.length || 0,
        structures_count: parsed.data_structures?.length || 0,
        components_count: parsed.component_types?.length || 0,
      });

      return {
        success: true,
        data: parsed,
        research_meta: {
          ai_provider: response.model as "gemini" | "claude",
          model_id: response.model_id,
          tokens_used: response.total_tokens,
          cost_usd: response.cost_usd,
        },
      };
    } catch (error) {
      const errorMessage =
        error instanceof Error ? error.message : "Unknown error";
      this.logger.error("Research failed with exception", {
        plugin_slug,
        error: errorMessage,
      });
      return {
        success: false,
        error: errorMessage,
      };
    }
  }

  /**
   * Check if cached documentation is comprehensive
   * Returns false if key fields are missing, triggering re-research
   */
  private isDocumentationComplete(cached: {
    code_examples?: string[];
    best_practices?: string[];
    description?: string;
  }): boolean {
    // Documentation is considered complete if it has code examples and best practices
    const hasExamples = cached.code_examples && cached.code_examples.length > 0;
    const hasPractices = cached.best_practices && cached.best_practices.length > 0;
    const hasDescription = cached.description && cached.description.length > 50;

    return Boolean(hasExamples && hasPractices && hasDescription);
  }

  /**
   * Build comprehensive research prompt for a plugin
   */
  private buildResearchPrompt(
    slug: string,
    version: string,
    name?: string,
    uri?: string
  ): string {
    let prompt = `Research COMPREHENSIVE documentation for this WordPress plugin:\n\n`;
    prompt += `Plugin Slug: ${slug}\n`;
    prompt += `Version: ${version}\n`;

    if (name) {
      prompt += `Plugin Name: ${name}\n`;
    }

    if (uri) {
      prompt += `Plugin URI: ${uri}\n`;
    }

    prompt += `\n## REQUIRED INFORMATION:\n`;
    prompt += `1. Official documentation URLs\n`;
    prompt += `2. ALL main functions/methods with signatures and descriptions\n`;
    prompt += `3. WORKING code examples for common use cases\n`;
    prompt += `4. Best practices and security guidelines\n`;
    prompt += `5. Data structures (meta keys, JSON formats, database tables)\n`;

    // Add plugin-specific requirements
    if (slug.includes("elementor")) {
      prompt += `\n## ELEMENTOR-SPECIFIC REQUIREMENTS:\n`;
      prompt += `- Complete _elementor_data JSON structure\n`;
      prompt += `- ALL widget types with their settings\n`;
      prompt += `- Section/Column/Container structure\n`;
      prompt += `- Code example for programmatically creating a complete page\n`;
      prompt += `- Widget settings schema for: heading, text-editor, image, button, video, icon, spacer, divider, google_maps, icon-box, image-box, star-rating, testimonial, tabs, accordion, toggle, social-icons, progress, counter, alert, html, shortcode, menu-anchor, sidebar, read-more, post-title, post-excerpt, post-content, post-featured-image, archive-title, archive-posts, site-logo, site-title, nav-menu, search-form\n`;
    } else if (slug.includes("woocommerce")) {
      prompt += `\n## WOOCOMMERCE-SPECIFIC REQUIREMENTS:\n`;
      prompt += `- Product creation and management functions\n`;
      prompt += `- Order CRUD operations\n`;
      prompt += `- Cart and checkout hooks\n`;
      prompt += `- Payment gateway integration\n`;
    } else if (slug.includes("acf") || slug.includes("advanced-custom-fields")) {
      prompt += `\n## ACF-SPECIFIC REQUIREMENTS:\n`;
      prompt += `- Field group registration with all options\n`;
      prompt += `- ALL field types with settings\n`;
      prompt += `- Repeater and flexible content handling\n`;
      prompt += `- Location rules configuration\n`;
    }

    prompt += `\nProvide the MOST COMPREHENSIVE documentation possible.`;
    prompt += `\nThe AI using this will need to write working code without additional research.`;

    return prompt;
  }

  /**
   * Parse the AI research response
   */
  private parseResearchResponse(
    content: string
  ): ResearchPluginDocsResponse["data"] | null {
    try {
      // Try to extract JSON from the response
      let jsonStr = content.trim();

      // Remove markdown code blocks if present
      if (jsonStr.startsWith("```")) {
        const match = jsonStr.match(/```(?:json)?\s*([\s\S]*?)\s*```/);
        if (match) {
          jsonStr = match[1];
        }
      }

      const parsed = JSON.parse(jsonStr);

      // Validate required fields
      if (!parsed.docs_url || !Array.isArray(parsed.main_functions)) {
        return null;
      }

      return {
        docs_url: parsed.docs_url,
        functions_url: parsed.functions_url,
        main_functions: parsed.main_functions, // No limit
        api_reference: parsed.api_reference,
        version_notes: parsed.version_notes,
        description: parsed.description,
        code_examples: parsed.code_examples,
        best_practices: parsed.best_practices,
        data_structures: parsed.data_structures,
        component_types: parsed.component_types,
      };
    } catch {
      return null;
    }
  }
}

// NO HARDCODED FALLBACKS - All documentation is dynamically researched
