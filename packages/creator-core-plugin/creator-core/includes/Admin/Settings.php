<?php
/**
 * Settings Page
 *
 * @package CreatorCore
 */

namespace CreatorCore\Admin;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Integrations\ProxyClient;
use CreatorCore\Integrations\PluginDetector;
use CreatorCore\Permission\RoleMapper;
use CreatorCore\Backup\SnapshotManager;

/**
 * Class Settings
 *
 * Handles the plugin settings page
 */
class Settings {

    /**
     * Proxy client instance
     *
     * @var ProxyClient
     */
    private ProxyClient $proxy_client;

    /**
     * Plugin detector instance
     *
     * @var PluginDetector
     */
    private PluginDetector $plugin_detector;

    /**
     * Settings groups
     *
     * @var array
     */
    private array $settings_groups = [
        'api'          => 'API Configuration',
        'backup'       => 'Backup Settings',
        'integrations' => 'Integrations',
        'permissions'  => 'User Permissions',
        'advanced'     => 'Advanced',
    ];

    /**
     * Constructor
     *
     * @param ProxyClient    $proxy_client    Proxy client instance.
     * @param PluginDetector $plugin_detector Plugin detector instance.
     */
    public function __construct( ProxyClient $proxy_client, PluginDetector $plugin_detector ) {
        $this->proxy_client    = $proxy_client;
        $this->plugin_detector = $plugin_detector;

        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wp_ajax_creator_validate_license', [ $this, 'ajax_validate_license' ] );
        add_action( 'wp_ajax_creator_clear_cache', [ $this, 'ajax_clear_cache' ] );
        add_action( 'wp_ajax_creator_cleanup_backups', [ $this, 'ajax_cleanup_backups' ] );
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function render(): void {
        // Handle form submission
        if ( isset( $_POST['creator_settings_nonce'] ) ) {
            $this->handle_save();
        }

        $data = [
            'settings'     => $this->get_all_settings(),
            'integrations' => $this->plugin_detector->get_all_integrations(),
            'roles'        => $this->get_roles_settings(),
            'backup_stats' => $this->get_backup_stats(),
            'connection'   => $this->proxy_client->check_connection(),
        ];

        include CREATOR_CORE_PATH . 'templates/settings.php';
    }

    /**
     * Register settings
     *
     * @return void
     */
    public function register_settings(): void {
        // API Settings
        register_setting( 'creator_api_settings', 'creator_license_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting( 'creator_api_settings', 'creator_proxy_url', [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => CREATOR_PROXY_URL,
        ]);

        // Backup Settings
        register_setting( 'creator_backup_settings', 'creator_backup_retention', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 30,
        ]);
        register_setting( 'creator_backup_settings', 'creator_max_backup_size_mb', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 500,
        ]);

        // Permission Settings
        register_setting( 'creator_permission_settings', 'creator_allowed_roles', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_roles' ],
            'default'           => [ 'administrator', 'creator_admin' ],
        ]);

        // Advanced Settings
        register_setting( 'creator_advanced_settings', 'creator_debug_mode', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]);
        register_setting( 'creator_advanced_settings', 'creator_log_level', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'info',
        ]);
        register_setting( 'creator_advanced_settings', 'creator_delete_data_on_uninstall', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => false,
        ]);
    }

    /**
     * Handle settings save
     *
     * @return void
     */
    private function handle_save(): void {
        if ( ! wp_verify_nonce( $_POST['creator_settings_nonce'], 'creator_save_settings' ) ) {
            add_settings_error( 'creator_settings', 'nonce_error', __( 'Security check failed.', 'creator-core' ), 'error' );
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            add_settings_error( 'creator_settings', 'permission_error', __( 'Permission denied.', 'creator-core' ), 'error' );
            return;
        }

        // API Settings
        if ( isset( $_POST['creator_license_key'] ) ) {
            update_option( 'creator_license_key', sanitize_text_field( $_POST['creator_license_key'] ) );
        }
        if ( isset( $_POST['creator_proxy_url'] ) ) {
            update_option( 'creator_proxy_url', esc_url_raw( $_POST['creator_proxy_url'] ) );
        }
        if ( isset( $_POST['creator_openai_api_key'] ) ) {
            update_option( 'creator_openai_api_key', sanitize_text_field( $_POST['creator_openai_api_key'] ) );
        }

        // Backup Settings
        if ( isset( $_POST['creator_backup_retention'] ) ) {
            update_option( 'creator_backup_retention', absint( $_POST['creator_backup_retention'] ) );
        }
        if ( isset( $_POST['creator_max_backup_size_mb'] ) ) {
            update_option( 'creator_max_backup_size_mb', absint( $_POST['creator_max_backup_size_mb'] ) );
        }

        // Permission Settings
        if ( isset( $_POST['creator_allowed_roles'] ) ) {
            $roles = array_map( 'sanitize_text_field', (array) $_POST['creator_allowed_roles'] );
            update_option( 'creator_allowed_roles', $roles );
        }

        // Advanced Settings
        update_option( 'creator_debug_mode', isset( $_POST['creator_debug_mode'] ) );
        if ( isset( $_POST['creator_log_level'] ) ) {
            update_option( 'creator_log_level', sanitize_text_field( $_POST['creator_log_level'] ) );
        }
        update_option( 'creator_delete_data_on_uninstall', isset( $_POST['creator_delete_data_on_uninstall'] ) );

        add_settings_error( 'creator_settings', 'settings_saved', __( 'Settings saved successfully.', 'creator-core' ), 'success' );
    }

    /**
     * Get all settings
     *
     * @return array
     */
    public function get_all_settings(): array {
        return [
            'license_key'               => get_option( 'creator_license_key', '' ),
            'proxy_url'                 => get_option( 'creator_proxy_url', CREATOR_PROXY_URL ),
            'openai_api_key'            => get_option( 'creator_openai_api_key', '' ),
            'backup_retention'          => get_option( 'creator_backup_retention', 30 ),
            'max_backup_size_mb'        => get_option( 'creator_max_backup_size_mb', 500 ),
            'allowed_roles'             => get_option( 'creator_allowed_roles', [ 'administrator', 'creator_admin' ] ),
            'debug_mode'                => get_option( 'creator_debug_mode', false ),
            'log_level'                 => get_option( 'creator_log_level', 'info' ),
            'delete_data_on_uninstall'  => get_option( 'creator_delete_data_on_uninstall', false ),
            'setup_completed'           => get_option( 'creator_setup_completed', false ),
        ];
    }

    /**
     * Get roles settings
     *
     * @return array
     */
    private function get_roles_settings(): array {
        $role_mapper   = new RoleMapper();
        $all_roles     = $role_mapper->get_available_roles();
        $allowed_roles = get_option( 'creator_allowed_roles', [ 'administrator', 'creator_admin' ] );

        $roles = [];
        foreach ( $all_roles as $slug => $role_data ) {
            $roles[ $slug ] = [
                'name'    => $role_data['name'],
                'enabled' => in_array( $slug, $allowed_roles, true ),
            ];
        }

        return $roles;
    }

    /**
     * Get backup statistics
     *
     * @return array
     */
    private function get_backup_stats(): array {
        $snapshot_manager = new SnapshotManager();
        return $snapshot_manager->get_backup_stats();
    }

    /**
     * Sanitize roles array
     *
     * @param mixed $input Input value.
     * @return array
     */
    public function sanitize_roles( $input ): array {
        if ( ! is_array( $input ) ) {
            return [ 'administrator' ];
        }

        return array_map( 'sanitize_text_field', $input );
    }

    /**
     * AJAX: Validate license
     *
     * @return void
     */
    public function ajax_validate_license(): void {
        check_ajax_referer( 'creator_settings_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'creator-core' ) ] );
        }

        $license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( $_POST['license_key'] ) : '';

        if ( empty( $license_key ) ) {
            wp_send_json_error( [ 'message' => __( 'License key is required', 'creator-core' ) ] );
        }

        $result = $this->proxy_client->validate_license( $license_key );

        if ( $result['success'] ) {
            update_option( 'creator_license_key', $license_key );
            wp_send_json_success( [
                'message' => __( 'License validated successfully', 'creator-core' ),
                'data'    => $result,
            ]);
        } else {
            wp_send_json_error( [
                'message' => $result['error'] ?? __( 'License validation failed', 'creator-core' ),
            ]);
        }
    }

    /**
     * AJAX: Clear cache
     *
     * @return void
     */
    public function ajax_clear_cache(): void {
        check_ajax_referer( 'creator_settings_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'creator-core' ) ] );
        }

        // Clear plugin caches
        delete_transient( 'creator_detected_plugins' );
        delete_transient( 'creator_site_context' );
        delete_transient( 'creator_license_status' );

        wp_cache_flush();

        wp_send_json_success( [ 'message' => __( 'Cache cleared successfully', 'creator-core' ) ] );
    }

    /**
     * AJAX: Cleanup backups
     *
     * @return void
     */
    public function ajax_cleanup_backups(): void {
        check_ajax_referer( 'creator_settings_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'creator-core' ) ] );
        }

        $retention = get_option( 'creator_backup_retention', 30 );
        $snapshot_manager = new SnapshotManager();
        $deleted = $snapshot_manager->cleanup_old_snapshots( $retention );

        wp_send_json_success( [
            'message' => sprintf(
                /* translators: %d: Number of snapshots deleted */
                __( 'Cleanup completed. %d snapshots removed.', 'creator-core' ),
                $deleted
            ),
            'deleted' => $deleted,
        ]);
    }

    /**
     * Get log levels
     *
     * @return array
     */
    public function get_log_levels(): array {
        return [
            'debug'   => __( 'Debug', 'creator-core' ),
            'info'    => __( 'Info', 'creator-core' ),
            'warning' => __( 'Warning', 'creator-core' ),
            'error'   => __( 'Error', 'creator-core' ),
        ];
    }
}
