<?php
/**
 * Context Loader
 *
 * Raccoglie informazioni sul sito WordPress (versione, tema, plugin attivi).
 *
 * @package CreatorCore
 */

namespace CreatorCore\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Class ContextLoader
 *
 * Gathers WordPress environment information for AI context
 */
class ContextLoader {

    /**
     * Get the full WordPress context
     *
     * @return array The context data.
     */
    public function get_context(): array {
        return [
            'wordpress'   => $this->get_wordpress_info(),
            'theme'       => $this->get_theme_info(),
            'plugins'     => $this->get_plugins_info(),
            'environment' => $this->get_environment_info(),
        ];
    }

    /**
     * Get WordPress core information
     *
     * @return array
     */
    private function get_wordpress_info(): array {
        global $wp_version;

        return [
            'version'      => $wp_version,
            'site_url'     => get_site_url(),
            'home_url'     => get_home_url(),
            'is_multisite' => is_multisite(),
            'language'     => get_locale(),
            'timezone'     => wp_timezone_string(),
        ];
    }

    /**
     * Get active theme information
     *
     * @return array
     */
    private function get_theme_info(): array {
        $theme = wp_get_theme();

        $info = [
            'name'        => $theme->get( 'Name' ),
            'version'     => $theme->get( 'Version' ),
            'author'      => $theme->get( 'Author' ),
            'template'    => $theme->get_template(),
            'stylesheet'  => $theme->get_stylesheet(),
            'is_child'    => $theme->parent() !== false,
        ];

        // Add parent theme info if child theme
        if ( $info['is_child'] ) {
            $parent = $theme->parent();
            if ( $parent ) {
                $info['parent'] = [
                    'name'    => $parent->get( 'Name' ),
                    'version' => $parent->get( 'Version' ),
                ];
            }
        }

        return $info;
    }

    /**
     * Get active plugins information
     *
     * @return array
     */
    private function get_plugins_info(): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins    = get_plugins();
        $active_plugins = get_option( 'active_plugins', [] );
        $plugins_info   = [];

        foreach ( $active_plugins as $plugin_file ) {
            if ( isset( $all_plugins[ $plugin_file ] ) ) {
                $plugin = $all_plugins[ $plugin_file ];
                $plugins_info[] = [
                    'name'        => $plugin['Name'],
                    'version'     => $plugin['Version'],
                    'author'      => $plugin['Author'],
                    'plugin_file' => $plugin_file,
                ];
            }
        }

        return $plugins_info;
    }

    /**
     * Get environment information
     *
     * @return array
     */
    private function get_environment_info(): array {
        return [
            'php_version'     => PHP_VERSION,
            'mysql_version'   => $this->get_mysql_version(),
            'server_software' => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown',
            'memory_limit'    => ini_get( 'memory_limit' ),
            'max_exec_time'   => ini_get( 'max_execution_time' ),
            'upload_max'      => ini_get( 'upload_max_filesize' ),
            'post_max'        => ini_get( 'post_max_size' ),
            'debug_mode'      => defined( 'WP_DEBUG' ) && WP_DEBUG,
            'ssl'             => is_ssl(),
        ];
    }

    /**
     * Get MySQL version
     *
     * @return string
     */
    private function get_mysql_version(): string {
        global $wpdb;

        $version = $wpdb->get_var( 'SELECT VERSION()' );

        return $version ?: 'Unknown';
    }

    /**
     * Get a summary context string for the AI
     *
     * @return string
     */
    public function get_context_summary(): string {
        $context = $this->get_context();

        $summary = sprintf(
            "WordPress %s | PHP %s | Theme: %s %s",
            $context['wordpress']['version'],
            $context['environment']['php_version'],
            $context['theme']['name'],
            $context['theme']['version']
        );

        $plugin_count = count( $context['plugins'] );
        $summary .= sprintf( " | %d active plugins", $plugin_count );

        if ( $context['wordpress']['is_multisite'] ) {
            $summary .= ' | Multisite';
        }

        return $summary;
    }

    /**
     * Get specific plugin context
     *
     * @param string $plugin_slug The plugin slug to search for.
     * @return array|null Plugin info or null if not found.
     */
    public function get_plugin_context( string $plugin_slug ): ?array {
        $plugins = $this->get_plugins_info();

        foreach ( $plugins as $plugin ) {
            if ( strpos( strtolower( $plugin['plugin_file'] ), strtolower( $plugin_slug ) ) !== false ) {
                return $plugin;
            }
        }

        return null;
    }

    /**
     * Check if a specific plugin is active
     *
     * @param string $plugin_slug The plugin slug to check.
     * @return bool
     */
    public function is_plugin_active( string $plugin_slug ): bool {
        return $this->get_plugin_context( $plugin_slug ) !== null;
    }
}
