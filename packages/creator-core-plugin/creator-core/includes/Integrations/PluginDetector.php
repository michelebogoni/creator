<?php
/**
 * Plugin Detector
 *
 * @package CreatorCore
 */

namespace CreatorCore\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class PluginDetector
 *
 * Detects installed and active plugins for integration
 */
class PluginDetector {

    /**
     * Supported integrations
     *
     * @var array
     */
    private array $supported_integrations = [
        'elementor' => [
            'name'        => 'Elementor',
            'slug'        => 'elementor/elementor.php',
            'class'       => 'Elementor\Plugin',
            'required'    => true,
            'min_version' => '3.0.0',
            'features'    => [ 'page_builder', 'widgets', 'templates' ],
        ],
        'elementor_pro' => [
            'name'        => 'Elementor Pro',
            'slug'        => 'elementor-pro/elementor-pro.php',
            'class'       => 'ElementorPro\Plugin',
            'required'    => false,
            'min_version' => '3.0.0',
            'features'    => [ 'theme_builder', 'forms', 'popup' ],
        ],
        'wpcode' => [
            'name'        => 'WP Code',
            'slug'        => 'insert-headers-and-footers/ihaf.php',
            'function'    => 'wpcode',
            'required'    => true,
            'min_version' => '2.0.0',
            'features'    => [ 'code_snippets', 'header_footer' ],
        ],
        'acf' => [
            'name'        => 'Advanced Custom Fields',
            'slug'        => 'advanced-custom-fields/acf.php',
            'class'       => 'ACF',
            'required'    => false,
            'min_version' => '5.0.0',
            'features'    => [ 'custom_fields', 'field_groups' ],
        ],
        'acf_pro' => [
            'name'        => 'Advanced Custom Fields PRO',
            'slug'        => 'advanced-custom-fields-pro/acf.php',
            'class'       => 'ACF',
            'function'    => 'acf_get_setting',
            'required'    => false,
            'min_version' => '5.0.0',
            'features'    => [ 'custom_fields', 'field_groups', 'options_page', 'repeater', 'flexible_content' ],
        ],
        'rank_math' => [
            'name'        => 'Rank Math SEO',
            'slug'        => 'seo-by-rank-math/rank-math.php',
            'function'    => 'rank_math',
            'required'    => false,
            'min_version' => '1.0.0',
            'features'    => [ 'seo', 'schema', 'sitemap' ],
        ],
        'woocommerce' => [
            'name'        => 'WooCommerce',
            'slug'        => 'woocommerce/woocommerce.php',
            'class'       => 'WooCommerce',
            'required'    => false,
            'min_version' => '5.0.0',
            'features'    => [ 'products', 'orders', 'customers', 'coupons' ],
        ],
        'litespeed' => [
            'name'        => 'LiteSpeed Cache',
            'slug'        => 'litespeed-cache/litespeed-cache.php',
            'constant'    => 'LSCWP_V',
            'required'    => false,
            'min_version' => '4.0.0',
            'features'    => [ 'cache', 'optimization', 'cdn' ],
        ],
    ];

    /**
     * Cached detection results
     *
     * @var array|null
     */
    private ?array $cached_results = null;

    /**
     * Get all integrations status
     *
     * @param bool $force_refresh Force refresh detection.
     * @return array
     */
    public function get_all_integrations( bool $force_refresh = false ): array {
        if ( $this->cached_results !== null && ! $force_refresh ) {
            return $this->cached_results;
        }

        $cached = get_transient( 'creator_detected_plugins' );
        if ( $cached !== false && ! $force_refresh ) {
            $this->cached_results = $cached;
            return $cached;
        }

        $results = [];

        foreach ( $this->supported_integrations as $key => $integration ) {
            $results[ $key ] = $this->detect_integration( $key );
        }

        $this->cached_results = $results;
        set_transient( 'creator_detected_plugins', $results, HOUR_IN_SECONDS );

        return $results;
    }

    /**
     * Detect a specific integration
     *
     * @param string $integration_key Integration key.
     * @return array
     */
    public function detect_integration( string $integration_key ): array {
        if ( ! isset( $this->supported_integrations[ $integration_key ] ) ) {
            return [
                'key'       => $integration_key,
                'installed' => false,
                'active'    => false,
                'error'     => 'Unknown integration',
            ];
        }

        $integration = $this->supported_integrations[ $integration_key ];
        $result = [
            'key'          => $integration_key,
            'name'         => $integration['name'],
            'required'     => $integration['required'],
            'installed'    => false,
            'active'       => false,
            'version'      => null,
            'min_version'  => $integration['min_version'],
            'compatible'   => false,
            'features'     => $integration['features'],
        ];

        // Check if plugin file exists
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $slug        = $integration['slug'];

        if ( isset( $all_plugins[ $slug ] ) ) {
            $result['installed'] = true;
            $result['version']   = $all_plugins[ $slug ]['Version'];
        }

        // Check if active
        $result['active'] = $this->is_integration_active( $integration );

        // Check version compatibility
        if ( $result['version'] ) {
            $result['compatible'] = version_compare(
                $result['version'],
                $integration['min_version'],
                '>='
            );
        }

        return $result;
    }

    /**
     * Check if integration is active
     *
     * @param array $integration Integration config.
     * @return bool
     */
    private function is_integration_active( array $integration ): bool {
        // Check by class
        if ( isset( $integration['class'] ) && class_exists( $integration['class'] ) ) {
            return true;
        }

        // Check by function
        if ( isset( $integration['function'] ) && function_exists( $integration['function'] ) ) {
            return true;
        }

        // Check by constant
        if ( isset( $integration['constant'] ) && defined( $integration['constant'] ) ) {
            return true;
        }

        // Fallback to is_plugin_active
        if ( isset( $integration['slug'] ) ) {
            return is_plugin_active( $integration['slug'] );
        }

        return false;
    }

    /**
     * Check if specific integration is available
     *
     * @param string $integration_key Integration key.
     * @return bool
     */
    public function is_available( string $integration_key ): bool {
        $status = $this->detect_integration( $integration_key );
        return $status['active'] && $status['compatible'];
    }

    /**
     * Get required plugins status
     *
     * @return array
     */
    public function get_required_plugins(): array {
        $required = [];

        foreach ( $this->supported_integrations as $key => $integration ) {
            if ( $integration['required'] ) {
                $required[ $key ] = $this->detect_integration( $key );
            }
        }

        return $required;
    }

    /**
     * Get optional plugins status
     *
     * @return array
     */
    public function get_optional_plugins(): array {
        $optional = [];

        foreach ( $this->supported_integrations as $key => $integration ) {
            if ( ! $integration['required'] ) {
                $optional[ $key ] = $this->detect_integration( $key );
            }
        }

        return $optional;
    }

    /**
     * Check if all required plugins are active
     *
     * @return array
     */
    public function check_requirements(): array {
        $required = $this->get_required_plugins();
        $missing  = [];
        $inactive = [];

        foreach ( $required as $key => $status ) {
            if ( ! $status['installed'] ) {
                $missing[] = $status['name'];
            } elseif ( ! $status['active'] ) {
                $inactive[] = $status['name'];
            } elseif ( ! $status['compatible'] ) {
                $missing[] = sprintf(
                    '%s (requires v%s+, found v%s)',
                    $status['name'],
                    $status['min_version'],
                    $status['version']
                );
            }
        }

        return [
            'met'      => empty( $missing ) && empty( $inactive ),
            'missing'  => $missing,
            'inactive' => $inactive,
        ];
    }

    /**
     * Get plugin installation URL
     *
     * @param string $integration_key Integration key.
     * @return string|null
     */
    public function get_install_url( string $integration_key ): ?string {
        if ( ! isset( $this->supported_integrations[ $integration_key ] ) ) {
            return null;
        }

        $slug_parts = explode( '/', $this->supported_integrations[ $integration_key ]['slug'] );
        $plugin_slug = $slug_parts[0];

        return admin_url( 'plugin-install.php?s=' . urlencode( $plugin_slug ) . '&tab=search&type=term' );
    }

    /**
     * Get plugin activation URL
     *
     * @param string $integration_key Integration key.
     * @return string|null
     */
    public function get_activation_url( string $integration_key ): ?string {
        if ( ! isset( $this->supported_integrations[ $integration_key ] ) ) {
            return null;
        }

        $slug = $this->supported_integrations[ $integration_key ]['slug'];

        return wp_nonce_url(
            admin_url( 'plugins.php?action=activate&plugin=' . urlencode( $slug ) ),
            'activate-plugin_' . $slug
        );
    }

    /**
     * Get available features based on active integrations
     *
     * @return array
     */
    public function get_available_features(): array {
        $features = [];

        foreach ( $this->get_all_integrations() as $key => $status ) {
            if ( $status['active'] && $status['compatible'] ) {
                $features = array_merge( $features, $status['features'] );
            }
        }

        return array_unique( $features );
    }

    /**
     * Get integration info
     *
     * @param string $integration_key Integration key.
     * @return array|null
     */
    public function get_integration_info( string $integration_key ): ?array {
        return $this->supported_integrations[ $integration_key ] ?? null;
    }

    /**
     * Clear detection cache
     *
     * @return void
     */
    public function clear_cache(): void {
        $this->cached_results = null;
        delete_transient( 'creator_detected_plugins' );
    }
}
