<?php
/**
 * Plugin Documentation Repository
 *
 * Centralized repository for plugin documentation.
 * Uses Firebase Firestore as primary storage with local fallback.
 * Implements lazy-loading: docs are fetched and cached on first request.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Context;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Integrations\ProxyClient;

/**
 * Class PluginDocsRepository
 *
 * Manages plugin documentation with:
 * - Firebase Firestore as central repository
 * - Local wp_options cache as fallback
 * - AI-powered documentation research for cache misses
 */
class PluginDocsRepository {

	/**
	 * Local cache option name
	 */
	private const CACHE_OPTION = 'creator_plugin_docs_cache';

	/**
	 * Cache TTL in seconds (30 days)
	 */
	private const CACHE_TTL = 2592000;

	/**
	 * Firestore collection name
	 */
	private const FIRESTORE_COLLECTION = 'plugin_docs_cache';

	/**
	 * Proxy client instance
	 *
	 * @var ProxyClient|null
	 */
	private ?ProxyClient $proxy_client = null;

	/**
	 * Local cache
	 *
	 * @var array|null
	 */
	private ?array $local_cache = null;

	/**
	 * Get documentation for a specific plugin version
	 *
	 * @param string $plugin_slug    Plugin slug.
	 * @param string $plugin_version Plugin version.
	 * @return array Documentation data or empty array.
	 */
	public function get_plugin_docs( string $plugin_slug, string $plugin_version ): array {
		// Normalize inputs
		$plugin_slug    = sanitize_title( $plugin_slug );
		$plugin_version = sanitize_text_field( $plugin_version );

		// Try local cache first
		$cached = $this->get_from_local_cache( $plugin_slug, $plugin_version );
		if ( $cached ) {
			$this->increment_cache_hits( $plugin_slug, $plugin_version );
			return $cached;
		}

		// Try Firestore
		$firestore_data = $this->get_from_firestore( $plugin_slug, $plugin_version );
		if ( $firestore_data ) {
			// Save to local cache
			$this->save_to_local_cache( $plugin_slug, $plugin_version, $firestore_data );
			return $firestore_data;
		}

		// Cache miss - trigger AI research
		$researched = $this->research_plugin_docs( $plugin_slug, $plugin_version );
		if ( $researched ) {
			// Save to both Firestore and local cache
			$this->save_to_firestore( $plugin_slug, $plugin_version, $researched );
			$this->save_to_local_cache( $plugin_slug, $plugin_version, $researched );
			return $researched;
		}

		// Return basic info if research fails
		return $this->get_basic_plugin_info( $plugin_slug, $plugin_version );
	}

	/**
	 * Get documentation from local cache
	 *
	 * @param string $plugin_slug    Plugin slug.
	 * @param string $plugin_version Plugin version.
	 * @return array|null
	 */
	private function get_from_local_cache( string $plugin_slug, string $plugin_version ): ?array {
		$cache = $this->get_local_cache();

		$cache_key = $this->get_cache_key( $plugin_slug, $plugin_version );

		if ( ! isset( $cache[ $cache_key ] ) ) {
			return null;
		}

		$entry = $cache[ $cache_key ];

		// Check if expired
		$cached_at = strtotime( $entry['cached_at'] ?? '0' );
		if ( time() - $cached_at > self::CACHE_TTL ) {
			return null; // Expired
		}

		return $entry['data'] ?? null;
	}

	/**
	 * Save to local cache
	 *
	 * @param string $plugin_slug    Plugin slug.
	 * @param string $plugin_version Plugin version.
	 * @param array  $data           Documentation data.
	 * @return void
	 */
	private function save_to_local_cache( string $plugin_slug, string $plugin_version, array $data ): void {
		$cache     = $this->get_local_cache();
		$cache_key = $this->get_cache_key( $plugin_slug, $plugin_version );

		$cache[ $cache_key ] = [
			'plugin_slug'    => $plugin_slug,
			'plugin_version' => $plugin_version,
			'data'           => $data,
			'cached_at'      => current_time( 'c' ),
			'cache_hits'     => 0,
		];

		$this->local_cache = $cache;
		update_option( self::CACHE_OPTION, $cache, false );
	}

	/**
	 * Get documentation from Firestore
	 *
	 * @param string $plugin_slug    Plugin slug.
	 * @param string $plugin_version Plugin version.
	 * @return array|null
	 */
	private function get_from_firestore( string $plugin_slug, string $plugin_version ): ?array {
		$proxy = $this->get_proxy_client();

		if ( ! $proxy ) {
			return null;
		}

		try {
			$response = $proxy->send_request( 'GET', '/api/plugin-docs/' . $plugin_slug . '/' . $plugin_version );

			if ( $response['success'] && ! empty( $response['data'] ) ) {
				return $response['data'];
			}
		} catch ( \Exception $e ) {
			// Log error but continue
			error_log( 'Creator: Firestore docs fetch failed: ' . $e->getMessage() );
		}

		return null;
	}

	/**
	 * Save to Firestore
	 *
	 * @param string $plugin_slug    Plugin slug.
	 * @param string $plugin_version Plugin version.
	 * @param array  $data           Documentation data.
	 * @return bool
	 */
	private function save_to_firestore( string $plugin_slug, string $plugin_version, array $data ): bool {
		$proxy = $this->get_proxy_client();

		if ( ! $proxy ) {
			return false;
		}

		try {
			$response = $proxy->send_request( 'POST', '/api/plugin-docs', [
				'plugin_slug'    => $plugin_slug,
				'plugin_version' => $plugin_version,
				'data'           => $data,
				'cached_at'      => current_time( 'c' ),
				'cached_by'      => get_current_user_id(),
			]);

			return $response['success'] ?? false;
		} catch ( \Exception $e ) {
			error_log( 'Creator: Firestore docs save failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Research plugin documentation using AI
	 *
	 * @param string $plugin_slug    Plugin slug.
	 * @param string $plugin_version Plugin version.
	 * @return array|null
	 */
	private function research_plugin_docs( string $plugin_slug, string $plugin_version ): ?array {
		$proxy = $this->get_proxy_client();

		if ( ! $proxy ) {
			return null;
		}

		// Prepare research prompt
		$prompt = sprintf(
			'Research official documentation for WordPress plugin "%s" version %s. ' .
			'Return JSON with these fields: ' .
			'docs_url (official documentation URL), ' .
			'main_functions (array of main PHP functions the plugin provides), ' .
			'api_reference (API documentation URL if available), ' .
			'version_notes (array of notable features in this version). ' .
			'Search: WordPress.org, GitHub, official plugin website. ' .
			'Return ONLY valid JSON, no other text.',
			$plugin_slug,
			$plugin_version
		);

		try {
			$response = $proxy->send_to_ai( $prompt, 'ANALYSIS', [
				'model'       => 'gemini', // Use Gemini for research tasks
				'max_tokens'  => 1024,
				'temperature' => 0.3, // Low temperature for factual response
			]);

			if ( $response['success'] && ! empty( $response['content'] ) ) {
				$parsed = $this->parse_research_response( $response['content'] );
				if ( $parsed ) {
					$parsed['researched_at'] = current_time( 'c' );
					$parsed['source']        = 'ai_research';
					return $parsed;
				}
			}
		} catch ( \Exception $e ) {
			error_log( 'Creator: Plugin docs research failed: ' . $e->getMessage() );
		}

		return null;
	}

	/**
	 * Parse AI research response
	 *
	 * @param string $response AI response content.
	 * @return array|null
	 */
	private function parse_research_response( string $response ): ?array {
		// Try to extract JSON from response
		$response = trim( $response );

		// Remove markdown code blocks if present
		if ( preg_match( '/```(?:json)?\s*([\s\S]*?)\s*```/', $response, $matches ) ) {
			$response = trim( $matches[1] );
		}

		$data = json_decode( $response, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
			return null;
		}

		// Validate required fields
		$required = [ 'docs_url', 'main_functions' ];
		foreach ( $required as $field ) {
			if ( ! isset( $data[ $field ] ) ) {
				return null;
			}
		}

		// Normalize main_functions to array
		if ( is_string( $data['main_functions'] ) ) {
			$data['main_functions'] = array_map( 'trim', explode( ',', $data['main_functions'] ) );
		}

		return [
			'docs_url'       => sanitize_url( $data['docs_url'] ?? '' ),
			'main_functions' => array_map( 'sanitize_text_field', $data['main_functions'] ?? [] ),
			'api_reference'  => sanitize_url( $data['api_reference'] ?? '' ),
			'version_notes'  => array_map( 'sanitize_text_field', $data['version_notes'] ?? [] ),
		];
	}

	/**
	 * Get basic plugin info as fallback
	 *
	 * @param string $plugin_slug    Plugin slug.
	 * @param string $plugin_version Plugin version.
	 * @return array
	 */
	private function get_basic_plugin_info( string $plugin_slug, string $plugin_version ): array {
		return [
			'docs_url'       => sprintf( 'https://wordpress.org/plugins/%s/', $plugin_slug ),
			'main_functions' => [],
			'api_reference'  => '',
			'version_notes'  => [],
			'source'         => 'fallback',
		];
	}

	/**
	 * Get local cache
	 *
	 * @return array
	 */
	private function get_local_cache(): array {
		if ( $this->local_cache === null ) {
			$this->local_cache = get_option( self::CACHE_OPTION, [] );
		}

		return is_array( $this->local_cache ) ? $this->local_cache : [];
	}

	/**
	 * Get cache key
	 *
	 * @param string $plugin_slug    Plugin slug.
	 * @param string $plugin_version Plugin version.
	 * @return string
	 */
	private function get_cache_key( string $plugin_slug, string $plugin_version ): string {
		return $plugin_slug . ':' . $plugin_version;
	}

	/**
	 * Increment cache hits counter
	 *
	 * @param string $plugin_slug    Plugin slug.
	 * @param string $plugin_version Plugin version.
	 * @return void
	 */
	private function increment_cache_hits( string $plugin_slug, string $plugin_version ): void {
		$cache     = $this->get_local_cache();
		$cache_key = $this->get_cache_key( $plugin_slug, $plugin_version );

		if ( isset( $cache[ $cache_key ] ) ) {
			$cache[ $cache_key ]['cache_hits'] = ( $cache[ $cache_key ]['cache_hits'] ?? 0 ) + 1;
			$this->local_cache                 = $cache;

			// Don't save every hit - save periodically
			if ( $cache[ $cache_key ]['cache_hits'] % 10 === 0 ) {
				update_option( self::CACHE_OPTION, $cache, false );
			}
		}
	}

	/**
	 * Get proxy client instance
	 *
	 * @return ProxyClient|null
	 */
	private function get_proxy_client(): ?ProxyClient {
		if ( $this->proxy_client === null ) {
			if ( class_exists( '\CreatorCore\Integrations\ProxyClient' ) ) {
				$this->proxy_client = new ProxyClient();
			}
		}

		return $this->proxy_client;
	}

	/**
	 * Clear local cache
	 *
	 * @return bool
	 */
	public function clear_local_cache(): bool {
		$this->local_cache = [];
		return delete_option( self::CACHE_OPTION );
	}

	/**
	 * Get cache statistics
	 *
	 * @return array
	 */
	public function get_cache_stats(): array {
		$cache = $this->get_local_cache();

		$total_entries = count( $cache );
		$total_hits    = 0;
		$oldest        = null;
		$newest        = null;

		foreach ( $cache as $entry ) {
			$total_hits += $entry['cache_hits'] ?? 0;

			$cached_at = $entry['cached_at'] ?? null;
			if ( $cached_at ) {
				if ( $oldest === null || $cached_at < $oldest ) {
					$oldest = $cached_at;
				}
				if ( $newest === null || $cached_at > $newest ) {
					$newest = $cached_at;
				}
			}
		}

		return [
			'total_entries' => $total_entries,
			'total_hits'    => $total_hits,
			'oldest_entry'  => $oldest,
			'newest_entry'  => $newest,
			'cache_size_kb' => round( strlen( serialize( $cache ) ) / 1024, 2 ),
		];
	}

	/**
	 * Get all cached plugins
	 *
	 * @return array
	 */
	public function get_cached_plugins(): array {
		$cache   = $this->get_local_cache();
		$plugins = [];

		foreach ( $cache as $key => $entry ) {
			$plugins[] = [
				'slug'       => $entry['plugin_slug'] ?? '',
				'version'    => $entry['plugin_version'] ?? '',
				'cached_at'  => $entry['cached_at'] ?? '',
				'cache_hits' => $entry['cache_hits'] ?? 0,
				'source'     => $entry['data']['source'] ?? 'unknown',
			];
		}

		return $plugins;
	}

	/**
	 * Prefetch documentation for multiple plugins
	 *
	 * @param array $plugins Array of ['slug' => string, 'version' => string].
	 * @return int Number of plugins fetched.
	 */
	public function prefetch_plugins( array $plugins ): int {
		$fetched = 0;

		foreach ( $plugins as $plugin ) {
			if ( empty( $plugin['slug'] ) || empty( $plugin['version'] ) ) {
				continue;
			}

			// Check if already cached
			$cached = $this->get_from_local_cache( $plugin['slug'], $plugin['version'] );
			if ( $cached ) {
				continue;
			}

			// Fetch and cache
			$docs = $this->get_plugin_docs( $plugin['slug'], $plugin['version'] );
			if ( ! empty( $docs ) && $docs['source'] !== 'fallback' ) {
				$fetched++;
			}
		}

		return $fetched;
	}
}
