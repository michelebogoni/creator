<?php
/**
 * Plugin Loader
 *
 * Bootstrap minimo che carica solo i componenti esistenti.
 *
 * @package CreatorCore
 */

namespace CreatorCore;

defined( 'ABSPATH' ) || exit;

/**
 * Class Loader
 *
 * Main plugin loader - initializes all components
 */
class Loader {

    /**
     * Admin dashboard instance
     *
     * @var Admin\Dashboard|null
     */
    private ?Admin\Dashboard $dashboard = null;

    /**
     * Dashboard API instance
     *
     * @var Admin\DashboardAPI|null
     */
    private ?Admin\DashboardAPI $dashboard_api = null;

    /**
     * Chat interface instance
     *
     * @var Chat\ChatInterface|null
     */
    private ?Chat\ChatInterface $chat_interface = null;

    /**
     * Chat controller instance
     *
     * @var Chat\ChatController|null
     */
    private ?Chat\ChatController $chat_controller = null;

    /**
     * Debug controller instance
     *
     * @var Debug\DebugController|null
     */
    private ?Debug\DebugController $debug_controller = null;

    /**
     * Run the plugin
     *
     * @return void
     */
    public function run(): void {
        // Always register REST API endpoints (permission is checked per-request)
        $this->init_rest_api();

        // Always register license-related hooks (they need to fire even during options.php processing)
        // These hooks handle their own permission checks internally
        $this->init_license_hooks();

        // Only load admin UI components in admin context
        if ( ! is_admin() ) {
            return;
        }

        // Check user capability for admin UI
        // Note: current_user_can() may not work reliably during plugins_loaded,
        // but it's fine here because we only skip UI components, not hooks
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Initialize admin components
        $this->init_admin();
        $this->init_chat();
    }

    /**
     * Initialize license-related hooks
     *
     * These hooks must be registered early and unconditionally because:
     * - update_option hooks fire during options.php processing
     * - current_user_can() isn't reliable during plugins_loaded
     * - Permission checks are done inside the callbacks
     *
     * @return void
     */
    private function init_license_hooks(): void {
        // Register hook to auto-verify license when license key is saved
        add_action( 'update_option_creator_license_key', [ $this, 'on_license_key_updated' ], 10, 2 );
    }

    /**
     * Handle license key update - auto-verify when saved
     *
     * This is a wrapper that delegates to Dashboard if available,
     * or handles verification directly if Dashboard isn't loaded yet.
     *
     * @param mixed $old_value Old option value.
     * @param mixed $new_value New option value.
     * @return void
     */
    public function on_license_key_updated( $old_value, $new_value ): void {
        error_log( '[Creator Hook] on_license_key_updated called - old: ' . ( $old_value ? 'SET' : 'EMPTY' ) . ', new: ' . ( $new_value ? 'SET' : 'EMPTY' ) );

        // Permission check - only process for users who can manage options
        if ( ! current_user_can( 'manage_options' ) ) {
            error_log( '[Creator Hook] Permission denied - current_user_can returned false' );
            return;
        }

        if ( empty( $new_value ) ) {
            error_log( '[Creator Hook] New value empty, clearing options' );
            delete_option( 'creator_site_token' );
            delete_option( 'creator_license_status' );
            return;
        }

        // Clear old token/status when key changes
        if ( $old_value !== $new_value ) {
            error_log( '[Creator Hook] Key changed, clearing old token/status' );
            delete_option( 'creator_site_token' );
            delete_option( 'creator_license_status' );
        }

        // Auto-verify the new license key
        error_log( '[Creator Hook] Calling verify_license_key' );
        $this->verify_license_key( $new_value );
    }

    /**
     * Verify a license key with the proxy
     *
     * @param string $license_key The license key to verify.
     * @return array Result with 'success' and 'message' keys.
     */
    private function verify_license_key( string $license_key ): array {
        error_log( '[Creator License] Starting verification for key: ' . substr( $license_key, 0, 15 ) . '...' );

        $proxy  = new Proxy\ProxyClient();
        $result = $proxy->validate_license( $license_key );

        error_log( '[Creator License] Proxy result: ' . wp_json_encode( $result ) );

        if ( $result['valid'] ) {
            // Save the JWT site_token from Firebase.
            if ( ! empty( $result['site_token'] ) ) {
                update_option( 'creator_site_token', $result['site_token'] );
                error_log( '[Creator License] site_token saved successfully (length: ' . strlen( $result['site_token'] ) . ')' );
            } else {
                error_log( '[Creator License] WARNING: site_token is empty in proxy result!' );
            }

            // Save status for display.
            $status_data = [
                'valid'        => true,
                'status'       => __( 'License Valid', 'creator-core' ),
                'expires_at'   => $result['expires_at'] ?? '',
                'plan'         => $result['plan'] ?? 'Standard',
                'tokens_used'  => $result['tokens_used'] ?? 0,
                'tokens_limit' => $result['tokens_limit'] ?? 50000,
                'reset_date'   => $result['reset_date'] ?? '',
                'checked_at'   => current_time( 'mysql' ),
            ];
            update_option( 'creator_license_status', $status_data );
            error_log( '[Creator License] license_status saved: ' . wp_json_encode( $status_data ) );

            return [
                'success' => true,
                'message' => __( 'License verified successfully.', 'creator-core' ),
            ];
        } else {
            error_log( '[Creator License] Validation failed: ' . ( $result['message'] ?? 'Unknown error' ) );
            $status_data = [
                'valid'      => false,
                'status'     => $result['message'] ?? __( 'License Invalid', 'creator-core' ),
                'checked_at' => current_time( 'mysql' ),
            ];
            update_option( 'creator_license_status', $status_data );

            return [
                'success' => false,
                'message' => $result['message'] ?? __( 'License validation failed.', 'creator-core' ),
            ];
        }
    }

    /**
     * Initialize admin components
     *
     * @return void
     */
    private function init_admin(): void {
        $this->dashboard = new Admin\Dashboard();
        $this->dashboard->init();
    }

    /**
     * Initialize chat interface
     *
     * @return void
     */
    private function init_chat(): void {
        $this->chat_interface = new Chat\ChatInterface();
        $this->chat_interface->init();
    }

    /**
     * Initialize REST API endpoints
     *
     * @return void
     */
    private function init_rest_api(): void {
        $this->chat_controller  = new Chat\ChatController();
        $this->debug_controller = new Debug\DebugController();
        $this->dashboard_api    = new Admin\DashboardAPI();

        add_action( 'rest_api_init', [ $this->chat_controller, 'register_routes' ] );
        add_action( 'rest_api_init', [ $this->debug_controller, 'register_routes' ] );
        add_action( 'rest_api_init', [ $this->dashboard_api, 'register_routes' ] );
    }

    /**
     * Get dashboard instance
     *
     * @return Admin\Dashboard|null
     */
    public function get_dashboard(): ?Admin\Dashboard {
        return $this->dashboard;
    }

    /**
     * Get dashboard API instance
     *
     * @return Admin\DashboardAPI|null
     */
    public function get_dashboard_api(): ?Admin\DashboardAPI {
        return $this->dashboard_api;
    }

    /**
     * Get chat interface instance
     *
     * @return Chat\ChatInterface|null
     */
    public function get_chat_interface(): ?Chat\ChatInterface {
        return $this->chat_interface;
    }

    /**
     * Get chat controller instance
     *
     * @return Chat\ChatController|null
     */
    public function get_chat_controller(): ?Chat\ChatController {
        return $this->chat_controller;
    }
}
