/**
 * @fileoverview Plugin Documentation Cache API
 * @module api/plugin-docs/pluginDocs
 *
 * @description
 * API endpoints for the centralized plugin documentation repository.
 * Provides caching and retrieval of plugin documentation across all Creator users.
 */

import * as functions from "firebase-functions";
import * as logger from "../../lib/logger";
import {
  getPluginDocs,
  savePluginDocs,
  incrementPluginDocsCacheHits,
  getPluginDocsStats,
  getPluginDocsAllVersions,
} from "../../lib/firestore";
import { SavePluginDocsRequest, PluginDocsResponse } from "../../types/PluginDocs";

/**
 * GET /api/plugin-docs/:plugin_slug/:version
 *
 * Retrieves plugin documentation from the cache.
 * Increments cache hit counter on successful retrieval.
 *
 * @example
 * ```
 * GET /api/plugin-docs/advanced-custom-fields/6.2.5
 * ```
 */
export const getPluginDocsApi = functions
  .region("us-central1")
  .https.onRequest(async (req, res) => {
    // Set CORS headers
    res.set("Access-Control-Allow-Origin", "*");
    res.set("Access-Control-Allow-Methods", "GET, OPTIONS");
    res.set("Access-Control-Allow-Headers", "Content-Type, Authorization");

    if (req.method === "OPTIONS") {
      res.status(204).send("");
      return;
    }

    if (req.method !== "GET") {
      res.status(405).json({ success: false, error: "Method not allowed" });
      return;
    }

    try {
      // Extract plugin_slug and version from path
      // Path format: /plugin-docs/:plugin_slug/:version
      const pathParts = req.path.split("/").filter(Boolean);
      const pluginSlug = pathParts[0];
      const pluginVersion = pathParts[1];

      if (!pluginSlug || !pluginVersion) {
        res.status(400).json({
          success: false,
          error: "Missing plugin_slug or version in path",
        });
        return;
      }

      logger.info("Getting plugin docs", { pluginSlug, pluginVersion });

      // Get from cache
      const docs = await getPluginDocs(pluginSlug, pluginVersion);

      if (!docs) {
        // Cache miss
        res.status(404).json({
          success: false,
          cached: false,
          data: null,
          error: "Plugin documentation not found in cache",
        } as PluginDocsResponse);
        return;
      }

      // Increment cache hits (fire and forget)
      incrementPluginDocsCacheHits(pluginSlug, pluginVersion).catch((err) => {
        logger.warn("Failed to increment cache hits", { error: err.message });
      });

      res.status(200).json({
        success: true,
        cached: true,
        source: docs.source,
        data: docs,
      } as PluginDocsResponse);
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : "Unknown error";
      logger.error("Error getting plugin docs", { error: errorMessage });

      res.status(500).json({
        success: false,
        error: "Internal server error",
      });
    }
  });

/**
 * POST /api/plugin-docs
 *
 * Saves plugin documentation to the cache.
 * Used when a Creator instance researches new plugin documentation.
 *
 * @example
 * ```
 * POST /api/plugin-docs
 * {
 *   "plugin_slug": "advanced-custom-fields",
 *   "plugin_version": "6.2.5",
 *   "data": {
 *     "docs_url": "https://www.advancedcustomfields.com/resources/",
 *     "main_functions": ["get_field()", "update_field()"],
 *     "api_reference": "https://www.advancedcustomfields.com/resources/#functions",
 *     "version_notes": ["6.2.5: Compatible with WordPress 6.7"]
 *   }
 * }
 * ```
 */
export const savePluginDocsApi = functions
  .region("us-central1")
  .https.onRequest(async (req, res) => {
    // Set CORS headers
    res.set("Access-Control-Allow-Origin", "*");
    res.set("Access-Control-Allow-Methods", "POST, OPTIONS");
    res.set("Access-Control-Allow-Headers", "Content-Type, Authorization");

    if (req.method === "OPTIONS") {
      res.status(204).send("");
      return;
    }

    if (req.method !== "POST") {
      res.status(405).json({ success: false, error: "Method not allowed" });
      return;
    }

    try {
      const body = req.body as SavePluginDocsRequest;

      // Validate required fields
      if (!body.plugin_slug || !body.plugin_version || !body.data) {
        res.status(400).json({
          success: false,
          error: "Missing required fields: plugin_slug, plugin_version, data",
        });
        return;
      }

      if (!body.data.docs_url || !body.data.main_functions) {
        res.status(400).json({
          success: false,
          error: "Missing required data fields: docs_url, main_functions",
        });
        return;
      }

      logger.info("Saving plugin docs", {
        pluginSlug: body.plugin_slug,
        pluginVersion: body.plugin_version,
      });

      // Check if already exists
      const existing = await getPluginDocs(body.plugin_slug, body.plugin_version);
      if (existing) {
        // Already cached - just return success
        res.status(200).json({
          success: true,
          cached: true,
          source: existing.source,
          data: existing,
          message: "Documentation already cached",
        });
        return;
      }

      // Save to cache
      const entry = await savePluginDocs({
        plugin_slug: body.plugin_slug,
        plugin_version: body.plugin_version,
        docs_url: body.data.docs_url,
        main_functions: body.data.main_functions,
        api_reference: body.data.api_reference,
        version_notes: body.data.version_notes,
        cached_by: body.cached_by,
        source: "ai_research",
      });

      logger.info("Plugin docs saved successfully", {
        pluginSlug: body.plugin_slug,
        pluginVersion: body.plugin_version,
      });

      res.status(201).json({
        success: true,
        cached: true,
        source: entry.source,
        data: entry,
      } as PluginDocsResponse);
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : "Unknown error";
      logger.error("Error saving plugin docs", { error: errorMessage });

      res.status(500).json({
        success: false,
        error: "Internal server error",
      });
    }
  });

/**
 * GET /api/plugin-docs/stats
 *
 * Returns statistics about the plugin docs repository.
 *
 * @example
 * ```
 * GET /api/plugin-docs/stats
 * ```
 */
export const getPluginDocsStatsApi = functions
  .region("us-central1")
  .https.onRequest(async (req, res) => {
    // Set CORS headers
    res.set("Access-Control-Allow-Origin", "*");
    res.set("Access-Control-Allow-Methods", "GET, OPTIONS");
    res.set("Access-Control-Allow-Headers", "Content-Type, Authorization");

    if (req.method === "OPTIONS") {
      res.status(204).send("");
      return;
    }

    if (req.method !== "GET") {
      res.status(405).json({ success: false, error: "Method not allowed" });
      return;
    }

    try {
      const stats = await getPluginDocsStats();

      res.status(200).json({
        success: true,
        data: stats,
      });
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : "Unknown error";
      logger.error("Error getting plugin docs stats", { error: errorMessage });

      res.status(500).json({
        success: false,
        error: "Internal server error",
      });
    }
  });

/**
 * GET /api/plugin-docs/all/:plugin_slug
 *
 * Returns all cached versions for a specific plugin.
 *
 * @example
 * ```
 * GET /api/plugin-docs/all/advanced-custom-fields
 * ```
 */
export const getPluginDocsAllVersionsApi = functions
  .region("us-central1")
  .https.onRequest(async (req, res) => {
    // Set CORS headers
    res.set("Access-Control-Allow-Origin", "*");
    res.set("Access-Control-Allow-Methods", "GET, OPTIONS");
    res.set("Access-Control-Allow-Headers", "Content-Type, Authorization");

    if (req.method === "OPTIONS") {
      res.status(204).send("");
      return;
    }

    if (req.method !== "GET") {
      res.status(405).json({ success: false, error: "Method not allowed" });
      return;
    }

    try {
      // Extract plugin_slug from path
      const pathParts = req.path.split("/").filter(Boolean);
      const pluginSlug = pathParts[0];

      if (!pluginSlug) {
        res.status(400).json({
          success: false,
          error: "Missing plugin_slug in path",
        });
        return;
      }

      const versions = await getPluginDocsAllVersions(pluginSlug);

      res.status(200).json({
        success: true,
        data: {
          plugin_slug: pluginSlug,
          versions_count: versions.length,
          versions: versions,
        },
      });
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : "Unknown error";
      logger.error("Error getting all plugin versions", { error: errorMessage });

      res.status(500).json({
        success: false,
        error: "Internal server error",
      });
    }
  });
