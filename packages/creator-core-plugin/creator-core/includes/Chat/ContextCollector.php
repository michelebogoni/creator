<?php
/**
 * Context Collector
 *
 * @package CreatorCore
 */

namespace CreatorCore\Chat;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Integrations\PluginDetector;

/**
 * Class ContextCollector
 *
 * Collects WordPress context for AI requests
 */
class ContextCollector {

    /**
     * Plugin detector instance
     *
     * @var PluginDetector
     */
    private PluginDetector $plugin_detector;

    /**
     * Cached context
     *
     * @var array|null
     */
    private ?array $cached_context = null;

    /**
     * Constructor
     *
     * @param PluginDetector|null $plugin_detector Plugin detector instance.
     */
    public function __construct( ?PluginDetector $plugin_detector = null ) {
        $this->plugin_detector = $plugin_detector ?? new PluginDetector();
    }

    /**
     * Get full WordPress context
     *
     * @param bool $force_refresh Force refresh cached context.
     * @return array
     */
    public function get_wordpress_context( bool $force_refresh = false ): array {
        if ( $this->cached_context !== null && ! $force_refresh ) {
            return $this->cached_context;
        }

        $cached = get_transient( 'creator_site_context' );
        if ( $cached !== false && ! $force_refresh ) {
            $this->cached_context = $cached;
            return $cached;
        }

        $context = [
            'site_info'        => $this->get_site_info(),
            'theme_info'       => $this->get_theme_info(),
            'active_plugins'   => $this->get_active_plugins_info(),
            'integrations'     => $this->plugin_detector->get_all_integrations(),
            'current_user'     => $this->get_current_user_info(),
            'content_stats'    => $this->get_content_statistics(),
            'capabilities'     => $this->get_available_capabilities(),
            'development_info' => $this->get_development_info(),
            'file_system'      => $this->get_file_system_info(),
            'database_info'    => $this->get_database_summary(),
            'timestamp'        => current_time( 'mysql' ),
        ];

        $this->cached_context = $context;
        set_transient( 'creator_site_context', $context, 5 * MINUTE_IN_SECONDS );

        return $context;
    }

    /**
     * Get site information
     *
     * @return array
     */
    public function get_site_info(): array {
        global $wp_version;

        return [
            'site_title'        => get_bloginfo( 'name' ),
            'site_description'  => get_bloginfo( 'description' ),
            'site_url'          => get_site_url(),
            'home_url'          => get_home_url(),
            'admin_url'         => admin_url(),
            'wordpress_version' => $wp_version,
            'php_version'       => PHP_VERSION,
            'mysql_version'     => $this->get_mysql_version(),
            'multisite'         => is_multisite(),
            'locale'            => get_locale(),
            'timezone'          => wp_timezone_string(),
            'date_format'       => get_option( 'date_format' ),
            'time_format'       => get_option( 'time_format' ),
            'permalink_structure' => get_option( 'permalink_structure' ),
            'uploads_dir'       => wp_upload_dir()['baseurl'],
        ];
    }

    /**
     * Get MySQL version
     *
     * @return string
     */
    private function get_mysql_version(): string {
        global $wpdb;
        return $wpdb->db_version();
    }

    /**
     * Get theme information
     *
     * @return array
     */
    public function get_theme_info(): array {
        $theme = wp_get_theme();
        $parent = $theme->parent();

        $info = [
            'name'        => $theme->get( 'Name' ),
            'version'     => $theme->get( 'Version' ),
            'author'      => $theme->get( 'Author' ),
            'author_uri'  => $theme->get( 'AuthorURI' ),
            'theme_uri'   => $theme->get( 'ThemeURI' ),
            'template'    => $theme->get_template(),
            'stylesheet'  => $theme->get_stylesheet(),
            'is_child'    => $parent !== false,
        ];

        if ( $parent ) {
            $info['parent_theme'] = [
                'name'    => $parent->get( 'Name' ),
                'version' => $parent->get( 'Version' ),
            ];
        }

        // Check for theme features
        $info['supports'] = [
            'title_tag'           => current_theme_supports( 'title-tag' ),
            'post_thumbnails'     => current_theme_supports( 'post-thumbnails' ),
            'custom_logo'         => current_theme_supports( 'custom-logo' ),
            'custom_header'       => current_theme_supports( 'custom-header' ),
            'custom_background'   => current_theme_supports( 'custom-background' ),
            'menus'               => current_theme_supports( 'menus' ),
            'widgets'             => current_theme_supports( 'widgets' ),
            'editor_styles'       => current_theme_supports( 'editor-styles' ),
            'block_editor_styles' => current_theme_supports( 'wp-block-styles' ),
        ];

        return $info;
    }

    /**
     * Get active plugins information
     *
     * @return array
     */
    public function get_active_plugins_info(): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins    = get_plugins();
        $active_plugins = get_option( 'active_plugins', [] );
        $result         = [];

        foreach ( $active_plugins as $plugin_path ) {
            if ( isset( $all_plugins[ $plugin_path ] ) ) {
                $plugin = $all_plugins[ $plugin_path ];
                $result[] = [
                    'name'        => $plugin['Name'],
                    'version'     => $plugin['Version'],
                    'author'      => $plugin['Author'],
                    'plugin_uri'  => $plugin['PluginURI'],
                    'description' => $plugin['Description'],
                    'text_domain' => $plugin['TextDomain'],
                ];
            }
        }

        return $result;
    }

    /**
     * Get current user information
     *
     * @return array
     */
    public function get_current_user_info(): array {
        $user = wp_get_current_user();

        if ( ! $user->exists() ) {
            return [
                'logged_in' => false,
            ];
        }

        return [
            'logged_in'    => true,
            'id'           => $user->ID,
            'login'        => $user->user_login,
            'email'        => $user->user_email,
            'display_name' => $user->display_name,
            'roles'        => $user->roles,
            'capabilities' => $this->get_user_creator_capabilities( $user ),
            'locale'       => get_user_locale( $user->ID ),
        ];
    }

    /**
     * Get user's Creator-specific capabilities
     *
     * @param \WP_User $user User object.
     * @return array
     */
    private function get_user_creator_capabilities( \WP_User $user ): array {
        $creator_caps = [
            'use_creator',
            'manage_creator_chats',
            'view_creator_audit',
            'manage_creator_backups',
            'manage_creator_settings',
        ];

        $result = [];

        foreach ( $creator_caps as $cap ) {
            $result[ $cap ] = $user->has_cap( $cap );
        }

        // Add relevant WordPress capabilities
        $wp_caps = [
            'edit_posts',
            'edit_pages',
            'publish_posts',
            'publish_pages',
            'edit_theme_options',
            'manage_options',
            'upload_files',
        ];

        foreach ( $wp_caps as $cap ) {
            $result[ $cap ] = $user->has_cap( $cap );
        }

        return $result;
    }

    /**
     * Get content statistics
     *
     * @return array
     */
    public function get_content_statistics(): array {
        $stats = [
            'posts'      => wp_count_posts( 'post' ),
            'pages'      => wp_count_posts( 'page' ),
            'media'      => wp_count_attachments(),
            'comments'   => wp_count_comments(),
            'users'      => count_users(),
            'categories' => wp_count_terms( 'category' ),
            'tags'       => wp_count_terms( 'post_tag' ),
        ];

        // Get custom post types
        $custom_post_types = get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' );
        $stats['custom_post_types'] = [];

        foreach ( $custom_post_types as $cpt ) {
            $stats['custom_post_types'][ $cpt->name ] = [
                'label' => $cpt->label,
                'count' => wp_count_posts( $cpt->name ),
            ];
        }

        // Get registered menus
        $stats['menus'] = [
            'registered' => get_registered_nav_menus(),
            'assigned'   => get_nav_menu_locations(),
        ];

        // Get sidebar/widget areas
        global $wp_registered_sidebars;
        $stats['sidebars'] = array_keys( $wp_registered_sidebars ?? [] );

        return $stats;
    }

    /**
     * Get available capabilities based on integrations
     *
     * @return array
     */
    public function get_available_capabilities(): array {
        $integrations = $this->plugin_detector->get_all_integrations();
        $capabilities = [ 'wordpress_core' ];

        foreach ( $integrations as $key => $status ) {
            if ( $status['active'] && $status['compatible'] ) {
                $capabilities[] = $key;
            }
        }

        return $capabilities;
    }

    /**
     * Get context summary for AI prompt
     *
     * @return string
     */
    public function get_context_summary(): string {
        $context = $this->get_wordpress_context();

        $summary = sprintf(
            "WordPress Site: %s (%s)\n",
            $context['site_info']['site_title'],
            $context['site_info']['site_url']
        );

        $summary .= sprintf(
            "WordPress %s, PHP %s\n",
            $context['site_info']['wordpress_version'],
            $context['site_info']['php_version']
        );

        $summary .= sprintf(
            "Theme: %s v%s\n",
            $context['theme_info']['name'],
            $context['theme_info']['version']
        );

        $summary .= "Active Integrations: ";
        $active = [];
        foreach ( $context['integrations'] as $key => $status ) {
            if ( $status['active'] ) {
                $active[] = $status['name'];
            }
        }
        $summary .= implode( ', ', $active ) . "\n";

        $summary .= sprintf(
            "User: %s (%s)\n",
            $context['current_user']['display_name'] ?? 'Guest',
            implode( ', ', $context['current_user']['roles'] ?? [] )
        );

        return $summary;
    }

    /**
     * Get specific post context
     *
     * @param int $post_id Post ID.
     * @return array
     */
    public function get_post_context( int $post_id ): array {
        $post = get_post( $post_id );

        if ( ! $post ) {
            return [];
        }

        $context = [
            'id'           => $post->ID,
            'title'        => $post->post_title,
            'type'         => $post->post_type,
            'status'       => $post->post_status,
            'author'       => get_the_author_meta( 'display_name', $post->post_author ),
            'date'         => $post->post_date,
            'modified'     => $post->post_modified,
            'excerpt'      => $post->post_excerpt,
            'permalink'    => get_permalink( $post_id ),
            'edit_link'    => get_edit_post_link( $post_id, 'raw' ),
            'template'     => get_page_template_slug( $post_id ),
            'featured_image' => get_the_post_thumbnail_url( $post_id, 'full' ),
        ];

        // Get taxonomies
        $taxonomies = get_object_taxonomies( $post->post_type );
        $context['taxonomies'] = [];

        foreach ( $taxonomies as $taxonomy ) {
            $terms = wp_get_post_terms( $post_id, $taxonomy );
            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                $context['taxonomies'][ $taxonomy ] = wp_list_pluck( $terms, 'name' );
            }
        }

        // Check Elementor
        if ( class_exists( '\Elementor\Plugin' ) ) {
            $document = \Elementor\Plugin::$instance->documents->get( $post_id );
            if ( $document ) {
                $context['elementor'] = [
                    'built_with' => $document->is_built_with_elementor(),
                    'edit_url'   => $document->get_edit_url(),
                ];
            }
        }

        return $context;
    }

    /**
     * Clear cached context
     *
     * @return void
     */
    public function clear_cache(): void {
        $this->cached_context = null;
        delete_transient( 'creator_site_context' );
    }

    /**
     * Get development environment information
     *
     * @return array
     */
    public function get_development_info(): array {
        return [
            'debug_mode'     => defined( 'WP_DEBUG' ) && WP_DEBUG,
            'debug_log'      => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
            'debug_display'  => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
            'script_debug'   => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
            'savequeries'    => defined( 'SAVEQUERIES' ) && SAVEQUERIES,
            'memory_limit'   => WP_MEMORY_LIMIT,
            'max_memory'     => defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : '256M',
            'php_memory'     => ini_get( 'memory_limit' ),
            'max_upload'     => wp_max_upload_size(),
            'max_post_size'  => ini_get( 'post_max_size' ),
            'max_exec_time'  => ini_get( 'max_execution_time' ),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'is_ssl'         => is_ssl(),
            'is_multisite'   => is_multisite(),
            'creator_capabilities' => [
                'file_operations'   => current_user_can( 'manage_options' ),
                'plugin_creation'   => current_user_can( 'install_plugins' ),
                'code_analysis'     => current_user_can( 'manage_options' ),
                'database_access'   => current_user_can( 'manage_options' ),
            ],
        ];
    }

    /**
     * Get file system information
     *
     * @return array
     */
    public function get_file_system_info(): array {
        $upload_dir = wp_upload_dir();

        return [
            'paths' => [
                'abspath'      => ABSPATH,
                'wp_content'   => WP_CONTENT_DIR,
                'plugins'      => WP_PLUGIN_DIR,
                'themes'       => get_theme_root(),
                'uploads'      => $upload_dir['basedir'],
                'active_theme' => get_stylesheet_directory(),
            ],
            'permissions' => [
                'wp_content_writable' => wp_is_writable( WP_CONTENT_DIR ),
                'plugins_writable'    => wp_is_writable( WP_PLUGIN_DIR ),
                'uploads_writable'    => wp_is_writable( $upload_dir['basedir'] ),
                'themes_writable'     => wp_is_writable( get_theme_root() ),
            ],
            'disk_space' => [
                'free'  => function_exists( 'disk_free_space' ) ? size_format( @disk_free_space( ABSPATH ) ) : 'Unknown',
                'total' => function_exists( 'disk_total_space' ) ? size_format( @disk_total_space( ABSPATH ) ) : 'Unknown',
            ],
        ];
    }

    /**
     * Get database summary information
     *
     * @return array
     */
    public function get_database_summary(): array {
        global $wpdb;

        return [
            'prefix'        => $wpdb->prefix,
            'charset'       => $wpdb->charset,
            'collate'       => $wpdb->collate,
            'mysql_version' => $wpdb->db_version(),
            'table_count'   => count( $wpdb->get_results( 'SHOW TABLES', ARRAY_N ) ),
        ];
    }

    /**
     * Get extended context summary for AI prompt
     *
     * @return string
     */
    public function get_extended_context_summary(): string {
        $context = $this->get_wordpress_context();

        $summary = "=== WordPress Development Environment ===\n\n";

        $summary .= sprintf(
            "Site: %s (%s)\n",
            $context['site_info']['site_title'],
            $context['site_info']['site_url']
        );

        $summary .= sprintf(
            "WordPress %s | PHP %s | MySQL %s\n",
            $context['site_info']['wordpress_version'],
            $context['site_info']['php_version'],
            $context['database_info']['mysql_version'] ?? 'Unknown'
        );

        $summary .= sprintf(
            "Theme: %s v%s\n\n",
            $context['theme_info']['name'],
            $context['theme_info']['version']
        );

        $summary .= "=== Development Capabilities ===\n";
        $dev_info = $context['development_info'];

        $summary .= sprintf( "Debug Mode: %s\n", $dev_info['debug_mode'] ? 'Enabled' : 'Disabled' );
        $summary .= sprintf( "Memory Limit: %s\n", $dev_info['memory_limit'] );

        $summary .= "\nCreator can:\n";
        if ( $dev_info['creator_capabilities']['file_operations'] ) {
            $summary .= "- Read, write, and manage files\n";
        }
        if ( $dev_info['creator_capabilities']['plugin_creation'] ) {
            $summary .= "- Create and install WordPress plugins\n";
        }
        if ( $dev_info['creator_capabilities']['code_analysis'] ) {
            $summary .= "- Analyze code for errors and security issues\n";
        }
        if ( $dev_info['creator_capabilities']['database_access'] ) {
            $summary .= "- Query and manage the database\n";
        }

        $summary .= "\n=== File System ===\n";
        $fs = $context['file_system'];
        $summary .= sprintf( "Plugins: %s (%s)\n",
            $fs['paths']['plugins'],
            $fs['permissions']['plugins_writable'] ? 'writable' : 'read-only'
        );
        $summary .= sprintf( "Themes: %s (%s)\n",
            $fs['paths']['themes'],
            $fs['permissions']['themes_writable'] ? 'writable' : 'read-only'
        );

        $summary .= "\n=== Active Integrations ===\n";
        $active = [];
        foreach ( $context['integrations'] as $key => $status ) {
            if ( $status['active'] ) {
                $active[] = $status['name'];
            }
        }
        $summary .= implode( ', ', $active ) . "\n";

        $summary .= sprintf(
            "\nUser: %s (%s)\n",
            $context['current_user']['display_name'] ?? 'Guest',
            implode( ', ', $context['current_user']['roles'] ?? [] )
        );

        return $summary;
    }
}
