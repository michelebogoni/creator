/**
 * @fileoverview Plugin Documentation types for Creator AI Proxy
 * @module types/PluginDocs
 */

import { Timestamp } from "firebase-admin/firestore";

/**
 * Plugin documentation cache entry
 */
export interface PluginDocsEntry {
  /** Plugin slug (e.g., "advanced-custom-fields") */
  plugin_slug: string;

  /** Plugin version (e.g., "6.2.5") */
  plugin_version: string;

  /** Official documentation URL */
  docs_url: string;

  /** Main functions provided by the plugin */
  main_functions: string[];

  /** API reference URL if available */
  api_reference?: string;

  /** Version-specific notes */
  version_notes?: string[];

  /** When this entry was cached */
  cached_at: Timestamp;

  /** User ID who cached this entry */
  cached_by?: string;

  /** Number of cache hits */
  cache_hits: number;

  /** Source of the documentation (ai_research, manual, fallback) */
  source: "ai_research" | "manual" | "fallback";

  /** Last verification timestamp */
  last_verified?: Timestamp;
}

/**
 * Data for creating a new plugin docs entry
 */
export interface CreatePluginDocsData {
  plugin_slug: string;
  plugin_version: string;
  docs_url: string;
  main_functions: string[];
  api_reference?: string;
  version_notes?: string[];
  cached_by?: string;
  source?: "ai_research" | "manual" | "fallback";
}

/**
 * Plugin docs repository statistics
 */
export interface PluginDocsStats {
  /** Total number of cached entries */
  total_entries: number;

  /** Total cache hits across all entries */
  total_cache_hits: number;

  /** Cache hit rate percentage */
  cache_hit_rate: number;

  /** Number of AI research operations performed */
  ai_research_count: number;

  /** Most requested plugins */
  most_requested: Array<{
    plugin_slug: string;
    request_count: number;
    versions_cached: number;
  }>;

  /** Last updated timestamp */
  last_updated: Timestamp;
}

/**
 * Request body for getting plugin docs
 */
export interface GetPluginDocsRequest {
  plugin_slug: string;
  plugin_version: string;
}

/**
 * Request body for saving plugin docs
 */
export interface SavePluginDocsRequest {
  plugin_slug: string;
  plugin_version: string;
  data: {
    docs_url: string;
    main_functions: string[];
    api_reference?: string;
    version_notes?: string[];
  };
  cached_by?: string;
}

/**
 * Response for plugin docs operations
 */
export interface PluginDocsResponse {
  success: boolean;
  data?: PluginDocsEntry | null;
  cached: boolean;
  source?: string;
  error?: string;
}
