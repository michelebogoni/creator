/**
 * @fileoverview Creator AI Proxy - Firebase Cloud Functions Entry Point
 * @module index
 *
 * @description
 * This is the main entry point for all Cloud Functions.
 * All functions are exported from this file and deployed to Firebase.
 *
 * @version 3.0.0-MVP
 * @author Creator AI Team
 */

// ==================== AUTH ENDPOINTS ====================

/**
 * POST /api/auth/validate-license
 *
 * Validates a license key and returns JWT token for authenticated access.
 *
 * @see {@link module:api/auth/validateLicense}
 */
export { validateLicense } from "./api/auth/validateLicense";

// ==================== AI ENDPOINTS ====================

/**
 * POST /api/ai/route-request
 *
 * Routes AI generation requests to the optimal provider with fallback.
 *
 * @see {@link module:api/ai/routeRequest}
 */
export { routeRequest } from "./api/ai/routeRequest";

// ==================== PLUGIN DOCS REPOSITORY ====================

/**
 * GET /api/plugin-docs/:plugin_slug/:version
 *
 * Retrieves plugin documentation from the centralized cache.
 *
 * @see {@link module:api/plugin-docs/pluginDocs}
 */
export { getPluginDocsApi } from "./api/plugin-docs/pluginDocs";

/**
 * POST /api/plugin-docs
 *
 * Saves plugin documentation to the centralized cache.
 *
 * @see {@link module:api/plugin-docs/pluginDocs}
 */
export { savePluginDocsApi } from "./api/plugin-docs/pluginDocs";

/**
 * GET /api/plugin-docs/stats
 *
 * Returns statistics about the plugin docs repository.
 *
 * @see {@link module:api/plugin-docs/pluginDocs}
 */
export { getPluginDocsStatsApi } from "./api/plugin-docs/pluginDocs";

/**
 * GET /api/plugin-docs/all/:plugin_slug
 *
 * Returns all cached versions for a specific plugin.
 *
 * @see {@link module:api/plugin-docs/pluginDocs}
 */
export { getPluginDocsAllVersionsApi } from "./api/plugin-docs/pluginDocs";

/**
 * POST /api/plugin-docs/research
 *
 * Researches plugin documentation using AI when not found in cache.
 * Uses Gemini/Claude to find official docs and main functions.
 *
 * @see {@link module:api/plugin-docs/pluginDocs}
 */
export { researchPluginDocsApi } from "./api/plugin-docs/pluginDocs";

/**
 * POST /api/plugin-docs/sync
 *
 * Returns plugin docs for syncing to WordPress local cache.
 * Used by Creator plugin to maintain a local fallback.
 *
 * @see {@link module:api/plugin-docs/pluginDocs}
 */
export { syncPluginDocsApi } from "./api/plugin-docs/pluginDocs";
