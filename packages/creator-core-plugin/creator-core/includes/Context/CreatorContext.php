<?php
/**
 * Creator Context Manager
 *
 * Generates, stores, and manages the comprehensive Creator Context document
 * that is passed to AI at each chat session.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Context;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Chat\ContextCollector;
use CreatorCore\Integrations\PluginDetector;
use CreatorCore\User\UserProfile;

/**
 * Class CreatorContext
 *
 * Manages the Creator Context document lifecycle:
 * - Generation at plugin activation
 * - Storage in database
 * - Retrieval for chat sessions
 * - Auto-refresh when system changes
 */
class CreatorContext {

	/**
	 * Option name for storing context document
	 */
	private const CONTEXT_OPTION = 'creator_context_document';

	/**
	 * Option name for context version/timestamp
	 */
	private const VERSION_OPTION = 'creator_context_version';

	/**
	 * Context collector instance
	 *
	 * @var ContextCollector
	 */
	private ContextCollector $context_collector;

	/**
	 * Plugin detector instance
	 *
	 * @var PluginDetector
	 */
	private PluginDetector $plugin_detector;

	/**
	 * Plugin docs repository instance
	 *
	 * @var PluginDocsRepository
	 */
	private PluginDocsRepository $docs_repository;

	/**
	 * System prompts instance
	 *
	 * @var SystemPrompts
	 */
	private SystemPrompts $system_prompts;

	/**
	 * Constructor
	 *
	 * @param ContextCollector|null     $context_collector Context collector instance.
	 * @param PluginDetector|null       $plugin_detector   Plugin detector instance.
	 * @param PluginDocsRepository|null $docs_repository   Plugin docs repository instance.
	 * @param SystemPrompts|null        $system_prompts    System prompts instance.
	 */
	public function __construct(
		?ContextCollector $context_collector = null,
		?PluginDetector $plugin_detector = null,
		?PluginDocsRepository $docs_repository = null,
		?SystemPrompts $system_prompts = null
	) {
		$this->context_collector = $context_collector ?? new ContextCollector();
		$this->plugin_detector   = $plugin_detector ?? new PluginDetector();
		$this->docs_repository   = $docs_repository ?? new PluginDocsRepository();
		$this->system_prompts    = $system_prompts ?? new SystemPrompts();
	}

	/**
	 * Generate and store the complete Creator Context document
	 *
	 * @param bool $force Force regeneration even if recent.
	 * @return array The generated context document.
	 */
	public function generate( bool $force = false ): array {
		// Check if we have a recent valid context
		if ( ! $force && $this->is_context_valid() ) {
			return $this->get_stored_context();
		}

		$context = [
			'meta'              => $this->generate_meta(),
			'user_profile'      => $this->generate_user_profile(),
			'system_info'       => $this->generate_system_info(),
			'plugins'           => $this->generate_plugins_info(),
			'custom_post_types' => $this->generate_cpt_info(),
			'taxonomies'        => $this->generate_taxonomies_info(),
			'acf_fields'        => $this->generate_acf_info(),
			'integrations'      => $this->generate_integrations_info(),
			'sitemap'           => $this->generate_sitemap(),
			'system_prompts'    => $this->generate_system_prompts(),
			'ai_instructions'   => $this->generate_ai_instructions(),
			'forbidden'         => $this->get_forbidden_functions(),
		];

		// Store the context
		$this->store_context( $context );

		return $context;
	}

	/**
	 * Get the stored context document
	 *
	 * @return array|null
	 */
	public function get_stored_context(): ?array {
		$context = get_option( self::CONTEXT_OPTION );
		return is_array( $context ) ? $context : null;
	}

	/**
	 * Get context for AI chat (formatted for injection)
	 *
	 * @return array Context ready for AI consumption.
	 */
	public function get_context_for_chat(): array {
		$context = $this->get_stored_context();

		// If no stored context, generate it
		if ( ! $context ) {
			$context = $this->generate( true );
		}

		// Check if context is stale
		if ( $this->is_context_stale() ) {
			$context = $this->generate( true );
		}

		return $context;
	}

	/**
	 * Get context as formatted string for AI prompt injection
	 *
	 * @return string
	 */
	public function get_context_as_prompt(): string {
		$context = $this->get_context_for_chat();

		if ( empty( $context ) ) {
			return '';
		}

		$prompt = "# CREATOR CONTEXT DOCUMENT\n";
		$prompt .= sprintf( "Generated: %s | Version: %s\n\n", $context['meta']['generated_at'] ?? 'unknown', $context['meta']['version'] ?? '1.0' );

		// User Profile
		$prompt .= "## USER PROFILE\n";
		$prompt .= sprintf( "Competence Level: %s\n", strtoupper( $context['user_profile']['level'] ?? 'intermediate' ) );
		$prompt .= $context['user_profile']['profile_system_prompt'] ?? '';
		$prompt .= "\n\n";

		// System Info
		$prompt .= "## SYSTEM INFORMATION\n";
		$si = $context['system_info'] ?? [];
		$prompt .= sprintf( "WordPress: %s | PHP: %s | MySQL: %s\n", $si['wordpress_version'] ?? '?', $si['php_version'] ?? '?', $si['mysql_version'] ?? '?' );
		$prompt .= sprintf( "Site: %s (%s)\n", $si['site_title'] ?? '', $si['site_url'] ?? '' );
		$prompt .= sprintf( "Theme: %s v%s\n", $si['theme_name'] ?? '', $si['theme_version'] ?? '' );
		$prompt .= "\n";

		// Active Plugins with docs
		$prompt .= "## ACTIVE PLUGINS\n";
		foreach ( $context['plugins'] ?? [] as $plugin ) {
			$prompt .= sprintf( "- **%s** v%s", $plugin['name'] ?? '', $plugin['version'] ?? '' );
			if ( ! empty( $plugin['docs_url'] ) ) {
				$prompt .= sprintf( " | Docs: %s", $plugin['docs_url'] );
			}
			$prompt .= "\n";
			if ( ! empty( $plugin['main_functions'] ) ) {
				$prompt .= sprintf( "  Functions: %s\n", implode( ', ', $plugin['main_functions'] ) );
			}
		}
		$prompt .= "\n";

		// Custom Post Types
		if ( ! empty( $context['custom_post_types'] ) ) {
			$prompt .= "## CUSTOM POST TYPES\n";
			foreach ( $context['custom_post_types'] as $cpt ) {
				$prompt .= sprintf( "- %s (%s): %s\n", $cpt['label'] ?? '', $cpt['name'] ?? '', $cpt['description'] ?? '' );
			}
			$prompt .= "\n";
		}

		// Taxonomies
		if ( ! empty( $context['taxonomies'] ) ) {
			$prompt .= "## CUSTOM TAXONOMIES\n";
			foreach ( $context['taxonomies'] as $tax ) {
				$prompt .= sprintf( "- %s (%s) → %s\n", $tax['label'] ?? '', $tax['name'] ?? '', implode( ', ', $tax['object_types'] ?? [] ) );
			}
			$prompt .= "\n";
		}

		// ACF Fields
		if ( ! empty( $context['acf_fields'] ) ) {
			$prompt .= "## ACF FIELD GROUPS\n";
			foreach ( $context['acf_fields'] as $group ) {
				$prompt .= sprintf( "- **%s**: %d fields\n", $group['title'] ?? '', count( $group['fields'] ?? [] ) );
				foreach ( $group['fields'] ?? [] as $field ) {
					$prompt .= sprintf( "  - %s (%s)\n", $field['name'] ?? '', $field['type'] ?? '' );
				}
			}
			$prompt .= "\n";
		}

		// Sitemap (condensed)
		$prompt .= "## SITE STRUCTURE (Sitemap)\n";
		$pages = array_filter( $context['sitemap'] ?? [], fn( $item ) => ( $item['post_type'] ?? '' ) === 'page' );
		foreach ( array_slice( $pages, 0, 20 ) as $page ) {
			$prompt .= sprintf( "- %s (%s)\n", $page['title'] ?? '', $page['url'] ?? '' );
		}
		if ( count( $pages ) > 20 ) {
			$prompt .= sprintf( "... and %d more pages\n", count( $pages ) - 20 );
		}
		$prompt .= "\n";

		// AI Instructions
		$prompt .= "## AI INSTRUCTIONS BY CATEGORY\n";
		foreach ( $context['ai_instructions'] ?? [] as $category => $functions ) {
			$prompt .= sprintf( "### %s\n%s\n\n", ucfirst( $category ), implode( ', ', $functions ) );
		}

		// Forbidden Functions
		$prompt .= "## FORBIDDEN FUNCTIONS (NEVER USE)\n";
		$prompt .= implode( ', ', $context['forbidden'] ?? [] ) . "\n\n";

		// System Prompts (Phase-specific)
		$prompt .= "## PHASE-SPECIFIC BEHAVIOR\n";
		$sp = $context['system_prompts'] ?? [];
		$prompt .= "### DISCOVERY PHASE\n" . ( $sp['discovery'] ?? '' ) . "\n\n";
		$prompt .= "### PROPOSAL PHASE\n" . ( $sp['proposal'] ?? '' ) . "\n\n";
		$prompt .= "### EXECUTION PHASE\n" . ( $sp['execution'] ?? '' ) . "\n\n";

		return $prompt;
	}

	/**
	 * Check if stored context is valid
	 *
	 * @return bool
	 */
	public function is_context_valid(): bool {
		$context = $this->get_stored_context();
		$version = get_option( self::VERSION_OPTION );

		if ( ! $context || ! $version ) {
			return false;
		}

		// Check if version matches current system hash
		$current_hash = $this->get_system_hash();
		return $version === $current_hash;
	}

	/**
	 * Check if context is stale (needs refresh)
	 *
	 * @return bool
	 */
	public function is_context_stale(): bool {
		$context = $this->get_stored_context();

		if ( ! $context ) {
			return true;
		}

		$stored_hash = $context['meta']['system_hash'] ?? '';
		$current_hash = $this->get_system_hash();

		return $stored_hash !== $current_hash;
	}

	/**
	 * Store context document in database
	 *
	 * @param array $context Context document.
	 * @return bool
	 */
	private function store_context( array $context ): bool {
		update_option( self::CONTEXT_OPTION, $context, false );
		update_option( self::VERSION_OPTION, $context['meta']['system_hash'] ?? '', false );
		return true;
	}

	/**
	 * Generate meta information
	 *
	 * @return array
	 */
	private function generate_meta(): array {
		return [
			'version'      => '1.0',
			'generated_at' => current_time( 'c' ),
			'site_url'     => get_site_url(),
			'system_hash'  => $this->get_system_hash(),
		];
	}

	/**
	 * Generate user profile section
	 *
	 * @return array
	 */
	private function generate_user_profile(): array {
		$level = UserProfile::get_level() ?: 'intermediate';

		return [
			'user_id'               => get_current_user_id(),
			'level'                 => $level,
			'profile_system_prompt' => $this->system_prompts->get_profile_prompt( $level ),
			'discovery_rules'       => $this->system_prompts->get_discovery_rules( $level ),
			'proposal_rules'        => $this->system_prompts->get_proposal_rules( $level ),
			'execution_rules'       => $this->system_prompts->get_execution_rules( $level ),
		];
	}

	/**
	 * Generate system information section
	 *
	 * @return array
	 */
	private function generate_system_info(): array {
		global $wp_version, $wpdb;

		$theme = wp_get_theme();

		return [
			'wordpress_version' => $wp_version,
			'php_version'       => PHP_VERSION,
			'mysql_version'     => $wpdb->db_version(),
			'site_title'        => get_bloginfo( 'name' ),
			'site_url'          => get_site_url(),
			'home_url'          => get_home_url(),
			'admin_url'         => admin_url(),
			'locale'            => get_locale(),
			'timezone'          => wp_timezone_string(),
			'multisite'         => is_multisite(),
			'theme_name'        => $theme->get( 'Name' ),
			'theme_version'     => $theme->get( 'Version' ),
			'is_child_theme'    => $theme->parent() !== false,
			'parent_theme'      => $theme->parent() ? $theme->parent()->get( 'Name' ) : null,
			'db_prefix'         => $wpdb->prefix,
			'db_charset'        => $wpdb->charset,
			'memory_limit'      => WP_MEMORY_LIMIT,
			'debug_mode'        => defined( 'WP_DEBUG' ) && WP_DEBUG,
		];
	}

	/**
	 * Generate plugins information with documentation
	 *
	 * @return array
	 */
	private function generate_plugins_info(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', [] );
		$result         = [];

		foreach ( $active_plugins as $plugin_path ) {
			if ( ! isset( $all_plugins[ $plugin_path ] ) ) {
				continue;
			}

			$plugin = $all_plugins[ $plugin_path ];
			$slug   = dirname( $plugin_path );
			$version = $plugin['Version'];

			// Get documentation from repository
			$docs = $this->docs_repository->get_plugin_docs( $slug, $version );

			$result[] = [
				'name'           => $plugin['Name'],
				'slug'           => $slug,
				'version'        => $version,
				'author'         => $plugin['Author'],
				'plugin_uri'     => $plugin['PluginURI'],
				'docs_url'       => $docs['docs_url'] ?? null,
				'main_functions' => $docs['main_functions'] ?? [],
				'api_reference'  => $docs['api_reference'] ?? null,
				'version_notes'  => $docs['version_notes'] ?? [],
			];
		}

		return $result;
	}

	/**
	 * Generate custom post types information
	 *
	 * @return array
	 */
	private function generate_cpt_info(): array {
		$cpts = get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' );
		$result = [];

		foreach ( $cpts as $cpt ) {
			$result[] = [
				'name'          => $cpt->name,
				'label'         => $cpt->label,
				'description'   => $cpt->description,
				'public'        => $cpt->public,
				'hierarchical'  => $cpt->hierarchical,
				'has_archive'   => $cpt->has_archive,
				'supports'      => get_all_post_type_supports( $cpt->name ),
				'taxonomies'    => get_object_taxonomies( $cpt->name ),
				'rewrite'       => $cpt->rewrite,
				'menu_icon'     => $cpt->menu_icon,
			];
		}

		return $result;
	}

	/**
	 * Generate taxonomies information
	 *
	 * @return array
	 */
	private function generate_taxonomies_info(): array {
		$taxonomies = get_taxonomies( [ 'public' => true, '_builtin' => false ], 'objects' );
		$result = [];

		foreach ( $taxonomies as $tax ) {
			$result[] = [
				'name'         => $tax->name,
				'label'        => $tax->label,
				'description'  => $tax->description,
				'hierarchical' => $tax->hierarchical,
				'object_types' => $tax->object_type,
				'rewrite'      => $tax->rewrite,
			];
		}

		return $result;
	}

	/**
	 * Generate ACF field groups information
	 *
	 * @return array|null
	 */
	private function generate_acf_info(): ?array {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return null;
		}

		$groups = acf_get_field_groups();
		$result = [];

		foreach ( $groups as $group ) {
			$fields = acf_get_fields( $group['key'] );
			$field_data = [];

			if ( $fields ) {
				foreach ( $fields as $field ) {
					$field_data[] = [
						'key'          => $field['key'],
						'name'         => $field['name'],
						'label'        => $field['label'],
						'type'         => $field['type'],
						'required'     => $field['required'] ?? false,
						'instructions' => $field['instructions'] ?? '',
					];
				}
			}

			$result[] = [
				'key'      => $group['key'],
				'title'    => $group['title'],
				'location' => $this->simplify_acf_location( $group['location'] ?? [] ),
				'active'   => $group['active'] ?? true,
				'fields'   => $field_data,
			];
		}

		return $result;
	}

	/**
	 * Simplify ACF location rules
	 *
	 * @param array $location ACF location rules.
	 * @return array
	 */
	private function simplify_acf_location( array $location ): array {
		$simplified = [];

		foreach ( $location as $group ) {
			$rules = [];
			foreach ( $group as $rule ) {
				$rules[] = sprintf(
					'%s %s %s',
					$rule['param'] ?? '',
					$rule['operator'] ?? '',
					$rule['value'] ?? ''
				);
			}
			$simplified[] = implode( ' AND ', $rules );
		}

		return $simplified;
	}

	/**
	 * Generate integrations information
	 *
	 * @return array
	 */
	private function generate_integrations_info(): array {
		$integrations = $this->plugin_detector->get_all_integrations();
		$result = [];

		foreach ( $integrations as $key => $status ) {
			if ( $status['active'] ) {
				$result[ $key ] = [
					'name'       => $status['name'],
					'active'     => true,
					'compatible' => $status['compatible'],
					'version'    => $status['version'] ?? null,
				];
			}
		}

		return $result;
	}

	/**
	 * Generate sitemap
	 *
	 * @return array
	 */
	private function generate_sitemap(): array {
		return $this->context_collector->get_sitemap( 100 );
	}

	/**
	 * Generate system prompts for all phases
	 *
	 * @return array
	 */
	private function generate_system_prompts(): array {
		$level = UserProfile::get_level() ?: 'intermediate';

		return [
			'universal'  => $this->system_prompts->get_universal_rules(),
			'discovery'  => $this->system_prompts->get_discovery_rules( $level ),
			'proposal'   => $this->system_prompts->get_proposal_rules( $level ),
			'execution'  => $this->system_prompts->get_execution_rules( $level ),
		];
	}

	/**
	 * Generate AI instructions by category
	 *
	 * @return array
	 */
	private function generate_ai_instructions(): array {
		return [
			'wordpress' => [
				'wp_insert_post()',
				'wp_update_post()',
				'wp_delete_post()',
				'get_post()',
				'get_posts()',
				'WP_Query',
				'register_post_type()',
				'register_taxonomy()',
				'add_action()',
				'add_filter()',
				'get_option()',
				'update_option()',
				'add_shortcode()',
				'wp_enqueue_script()',
				'wp_enqueue_style()',
			],
			'woocommerce' => [
				'wc_get_product()',
				'wc_create_product()',
				'WC()->cart',
				'WC()->session',
				'wc_get_orders()',
				'wc_create_order()',
				'wc_add_notice()',
				'wc_price()',
				'wc_get_template()',
			],
			'acf' => [
				'get_field()',
				'update_field()',
				'get_field_object()',
				'acf_add_local_field_group()',
				'acf_add_local_field()',
				'acf_get_field_groups()',
				'have_rows()',
				'the_row()',
				'get_sub_field()',
			],
			'elementor' => [
				'\Elementor\Plugin::instance()',
				'\Elementor\Controls_Manager',
				'\Elementor\Widget_Base',
				'elementor_get_option()',
				'\Elementor\Core\Documents_Manager',
			],
			'database' => [
				'$wpdb->get_results()',
				'$wpdb->get_row()',
				'$wpdb->get_var()',
				'$wpdb->insert()',
				'$wpdb->update()',
				'$wpdb->delete()',
				'$wpdb->prepare()',
				'$wpdb->query()',
			],
		];
	}

	/**
	 * Get forbidden functions list
	 *
	 * @return array
	 */
	private function get_forbidden_functions(): array {
		return [
			// System execution
			'exec()',
			'shell_exec()',
			'system()',
			'passthru()',
			'popen()',
			'proc_open()',
			'pcntl_exec()',
			// Dangerous eval
			'eval()',
			'assert()',
			'create_function()',
			'preg_replace() with /e modifier',
			// File system dangerous
			'unlink() on system files',
			'rmdir() on system dirs',
			'file_put_contents() on system files',
			// Database dangerous
			'DROP TABLE',
			'DROP DATABASE',
			'TRUNCATE TABLE',
			'DELETE without WHERE',
			// WordPress dangerous
			'wp_delete_user() without confirmation',
			'wp_remote_get() to untrusted URLs',
			'unserialize() on user input',
			// Output dangerous
			'header() without exit',
			'die() without proper cleanup',
		];
	}

	/**
	 * Get system hash for change detection
	 *
	 * @return string
	 */
	private function get_system_hash(): string {
		global $wp_version;

		$hash_data = [
			'wp_version'     => $wp_version,
			'php_version'    => PHP_VERSION,
			'active_plugins' => get_option( 'active_plugins', [] ),
			'theme'          => get_stylesheet(),
			'user_level'     => UserProfile::get_level(),
		];

		return md5( serialize( $hash_data ) );
	}

	/**
	 * Force refresh context
	 *
	 * @return array
	 */
	public function refresh(): array {
		// Clear any cached context
		delete_transient( 'creator_site_context' );

		return $this->generate( true );
	}

	/**
	 * Get context generation timestamp
	 *
	 * @return string|null
	 */
	public function get_generated_at(): ?string {
		$context = $this->get_stored_context();
		return $context['meta']['generated_at'] ?? null;
	}

	/**
	 * Delete stored context
	 *
	 * @return bool
	 */
	public function delete(): bool {
		delete_option( self::CONTEXT_OPTION );
		delete_option( self::VERSION_OPTION );
		return true;
	}
}
