<?php
/**
 * Setup Wizard
 *
 * @package CreatorCore
 */

namespace CreatorCore\Admin;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Integrations\PluginDetector;
use CreatorCore\Integrations\ProxyClient;

/**
 * Class SetupWizard
 *
 * Handles the plugin setup wizard
 */
class SetupWizard {

    /**
     * Plugin detector instance
     *
     * @var PluginDetector
     */
    private PluginDetector $plugin_detector;

    /**
     * Wizard steps
     *
     * @var array
     */
    private array $steps = [
        'dependencies' => [
            'name'  => 'Plugin Dependencies',
            'order' => 1,
        ],
        'backup' => [
            'name'  => 'Configure Backup',
            'order' => 2,
        ],
        'license' => [
            'name'  => 'License Activation',
            'order' => 3,
        ],
        'finish' => [
            'name'  => 'Ready to Go',
            'order' => 4,
        ],
    ];

    /**
     * Constructor
     *
     * @param PluginDetector $plugin_detector Plugin detector instance.
     */
    public function __construct( PluginDetector $plugin_detector ) {
        $this->plugin_detector = $plugin_detector;

        add_action( 'wp_ajax_creator_setup_step', [ $this, 'ajax_process_step' ] );
        add_action( 'wp_ajax_creator_install_plugin', [ $this, 'ajax_install_plugin' ] );
        add_action( 'wp_ajax_creator_activate_plugin', [ $this, 'ajax_activate_plugin' ] );
        add_action( 'wp_ajax_creator_skip_setup', [ $this, 'ajax_skip_setup' ] );
        add_action( 'wp_ajax_creator_validate_license', [ $this, 'ajax_validate_license' ] );
    }

    /**
     * Recursively sanitize an array of input data
     *
     * @param mixed $input Input data to sanitize.
     * @return mixed Sanitized data.
     */
    private function sanitize_array( $input ) {
        if ( is_array( $input ) ) {
            return array_map( [ $this, 'sanitize_array' ], $input );
        }
        if ( is_string( $input ) ) {
            return sanitize_text_field( $input );
        }
        if ( is_int( $input ) ) {
            return absint( $input );
        }
        if ( is_bool( $input ) ) {
            return (bool) $input;
        }
        return $input;
    }

    /**
     * Maybe redirect to setup wizard
     * Only handles redirect when user tries to access Creator pages without completing setup
     * Activation redirect is handled separately in creator-core.php
     *
     * @return void
     */
    public function maybe_redirect(): void {
        // Don't redirect on AJAX
        if ( wp_doing_ajax() ) {
            return;
        }

        // Don't redirect if already on setup page (allow navigation between steps)
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'creator-setup' ) {
            return;
        }

        // Force redirect to setup if not completed and user tries to access other Creator pages
        if ( ! get_option( 'creator_setup_completed' ) && current_user_can( 'manage_options' ) ) {
            if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'creator-' ) === 0 && $_GET['page'] !== 'creator-setup' ) {
                wp_safe_redirect( admin_url( 'admin.php?page=creator-setup' ) );
                exit;
            }
        }
    }

    /**
     * Render setup wizard
     *
     * @return void
     */
    public function render(): void {
        $current_step = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : 'dependencies';

        if ( ! isset( $this->steps[ $current_step ] ) ) {
            $current_step = 'dependencies';
        }

        $data = [
            'current_step' => $current_step,
            'steps'        => $this->steps,
            'step_data'    => $this->get_step_data( $current_step ),
        ];

        include CREATOR_CORE_PATH . 'templates/setup-wizard.php';
    }

    /**
     * Get data for current step
     *
     * @param string $step Step key.
     * @return array
     */
    private function get_step_data( string $step ): array {
        switch ( $step ) {
            case 'dependencies':
                return $this->get_dependencies_data();

            case 'backup':
                return $this->get_backup_data();

            case 'license':
                return $this->get_license_data();

            case 'finish':
                return $this->get_finish_data();

            default:
                return [];
        }
    }

    /**
     * Get dependencies step data
     *
     * @return array
     */
    private function get_dependencies_data(): array {
        return [
            'required' => $this->plugin_detector->get_required_plugins(),
            'optional' => $this->plugin_detector->get_optional_plugins(),
            'requirements_met' => $this->plugin_detector->check_requirements()['met'],
        ];
    }

    /**
     * Get backup step data
     *
     * @return array
     */
    private function get_backup_data(): array {
        $upload_dir = wp_upload_dir();
        $backup_path = $upload_dir['basedir'] . '/creator-backups';

        return [
            'backup_path'      => $backup_path,
            'path_exists'      => file_exists( $backup_path ),
            'path_writable'    => is_writable( $upload_dir['basedir'] ),
            'retention_days'   => get_option( 'creator_backup_retention', 30 ),
            'max_size_mb'      => get_option( 'creator_max_backup_size_mb', 500 ),
        ];
    }

    /**
     * Get license step data
     *
     * @return array
     */
    private function get_license_data(): array {
        $license_status = get_transient( 'creator_license_status' );

        return [
            'license_key'    => get_option( 'creator_license_key', '' ),
            'is_validated'   => $license_status && ! empty( $license_status['success'] ),
            'license_status' => $license_status,
        ];
    }

    /**
     * Get finish step data
     *
     * @return array
     */
    private function get_finish_data(): array {
        return [
            'integrations' => $this->plugin_detector->get_all_integrations(),
            'features'     => $this->plugin_detector->get_available_features(),
            'dashboard_url' => admin_url( 'admin.php?page=creator-dashboard' ),
            'chat_url'     => admin_url( 'admin.php?page=creator-chat' ),
            'settings_url' => admin_url( 'admin.php?page=creator-settings' ),
        ];
    }

    /**
     * Get next step URL
     *
     * @param string $current_step Current step.
     * @return string
     */
    public function get_next_step_url( string $current_step ): string {
        $step_keys = array_keys( $this->steps );
        $current_index = array_search( $current_step, $step_keys, true );

        if ( $current_index === false || $current_index >= count( $step_keys ) - 1 ) {
            return admin_url( 'admin.php?page=creator-dashboard' );
        }

        $next_step = $step_keys[ $current_index + 1 ];
        return admin_url( 'admin.php?page=creator-setup&step=' . $next_step );
    }

    /**
     * Get previous step URL
     *
     * @param string $current_step Current step.
     * @return string|null
     */
    public function get_previous_step_url( string $current_step ): ?string {
        $step_keys = array_keys( $this->steps );
        $current_index = array_search( $current_step, $step_keys, true );

        if ( $current_index === false || $current_index <= 0 ) {
            return null;
        }

        $prev_step = $step_keys[ $current_index - 1 ];
        return admin_url( 'admin.php?page=creator-setup&step=' . $prev_step );
    }

    /**
     * AJAX: Process step
     *
     * @return void
     */
    public function ajax_process_step(): void {
        check_ajax_referer( 'creator_setup_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'creator-core' ) ] );
        }

        $step = isset( $_POST['step'] ) ? sanitize_key( $_POST['step'] ) : '';
        $data = isset( $_POST['data'] ) ? $this->sanitize_array( wp_unslash( $_POST['data'] ) ) : [];

        switch ( $step ) {
            case 'backup':
                $this->process_backup_step( $data );
                break;

            case 'license':
                $this->process_license_step( $data );
                break;

            case 'finish':
                $this->complete_setup();
                break;

            default:
                wp_send_json_success( [ 'next_url' => $this->get_next_step_url( $step ) ] );
        }
    }

    /**
     * Process backup step
     *
     * @param array $data Form data.
     * @return void
     */
    private function process_backup_step( array $data ): void {
        if ( isset( $data['retention_days'] ) ) {
            update_option( 'creator_backup_retention', absint( $data['retention_days'] ) );
        }

        if ( isset( $data['max_size_mb'] ) ) {
            update_option( 'creator_max_backup_size_mb', absint( $data['max_size_mb'] ) );
        }

        // Ensure backup directory exists
        $upload_dir = wp_upload_dir();
        $backup_path = $upload_dir['basedir'] . '/creator-backups';

        if ( ! file_exists( $backup_path ) ) {
            wp_mkdir_p( $backup_path );
            file_put_contents( $backup_path . '/.htaccess', "Order deny,allow\nDeny from all" );
            file_put_contents( $backup_path . '/index.php', '<?php // Silence is golden.' );
        }

        update_option( 'creator_backup_path', $backup_path );

        wp_send_json_success( [
            'message'  => __( 'Backup settings saved', 'creator-core' ),
            'next_url' => $this->get_next_step_url( 'backup' ),
        ]);
    }

    /**
     * Process license step
     *
     * @param array $data Form data.
     * @return void
     */
    private function process_license_step( array $data ): void {
        $license_key = isset( $data['license_key'] ) ? sanitize_text_field( $data['license_key'] ) : '';

        if ( empty( $license_key ) ) {
            wp_send_json_error( [ 'message' => __( 'License key is required', 'creator-core' ) ] );
        }

        $proxy_client = new ProxyClient();
        $result = $proxy_client->validate_license( $license_key );

        if ( $result['success'] ) {
            update_option( 'creator_license_key', $license_key );
            wp_send_json_success( [
                'message'  => __( 'License validated', 'creator-core' ),
                'next_url' => $this->get_next_step_url( 'license' ),
            ]);
        } else {
            wp_send_json_error( [
                'message' => $result['error'] ?? __( 'License validation failed', 'creator-core' ),
            ]);
        }
    }

    /**
     * Complete setup
     *
     * @return void
     */
    private function complete_setup(): void {
        update_option( 'creator_setup_completed', true );

        wp_send_json_success( [
            'message'  => __( 'Setup completed!', 'creator-core' ),
            'next_url' => admin_url( 'admin.php?page=creator-dashboard' ),
        ]);
    }

    /**
     * AJAX: Install plugin
     *
     * @return void
     */
    public function ajax_install_plugin(): void {
        check_ajax_referer( 'creator_setup_nonce', 'nonce' );

        if ( ! current_user_can( 'install_plugins' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'creator-core' ) ] );
        }

        $plugin_slug = isset( $_POST['plugin'] ) ? sanitize_text_field( $_POST['plugin'] ) : '';

        if ( empty( $plugin_slug ) ) {
            wp_send_json_error( [ 'message' => __( 'Plugin slug required', 'creator-core' ) ] );
        }

        // Include required files
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';

        // Get plugin info
        $api = plugins_api( 'plugin_information', [
            'slug'   => $plugin_slug,
            'fields' => [ 'sections' => false ],
        ]);

        if ( is_wp_error( $api ) ) {
            wp_send_json_error( [ 'message' => $api->get_error_message() ] );
        }

        // Install plugin
        $skin = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader( $skin );
        $result = $upgrader->install( $api->download_link );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        if ( $result === false ) {
            wp_send_json_error( [ 'message' => __( 'Installation failed', 'creator-core' ) ] );
        }

        wp_send_json_success( [
            'message' => __( 'Plugin installed successfully', 'creator-core' ),
        ]);
    }

    /**
     * AJAX: Activate plugin
     *
     * @return void
     */
    public function ajax_activate_plugin(): void {
        check_ajax_referer( 'creator_setup_nonce', 'nonce' );

        if ( ! current_user_can( 'activate_plugins' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'creator-core' ) ] );
        }

        $plugin_file = isset( $_POST['plugin'] ) ? sanitize_text_field( $_POST['plugin'] ) : '';

        if ( empty( $plugin_file ) ) {
            wp_send_json_error( [ 'message' => __( 'Plugin file required', 'creator-core' ) ] );
        }

        $result = activate_plugin( $plugin_file );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        // Clear detection cache
        $this->plugin_detector->clear_cache();

        wp_send_json_success( [
            'message' => __( 'Plugin activated successfully', 'creator-core' ),
        ]);
    }

    /**
     * AJAX: Skip setup
     *
     * @return void
     */
    public function ajax_skip_setup(): void {
        check_ajax_referer( 'creator_setup_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'creator-core' ) ] );
        }

        update_option( 'creator_setup_completed', true );

        wp_send_json_success( [
            'message'      => __( 'Setup skipped', 'creator-core' ),
            'redirect_url' => admin_url( 'admin.php?page=creator-dashboard' ),
        ]);
    }

    /**
     * AJAX: Validate license
     *
     * @return void
     */
    public function ajax_validate_license(): void {
        check_ajax_referer( 'creator_setup_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'creator-core' ) ] );
        }

        $license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';

        if ( empty( $license_key ) ) {
            wp_send_json_error( [ 'message' => __( 'License key is required', 'creator-core' ) ] );
        }

        $proxy_client = new ProxyClient();
        $result = $proxy_client->validate_license( $license_key );

        if ( $result['success'] ) {
            update_option( 'creator_license_key', $license_key );
            wp_send_json_success( [
                'message' => __( 'License validated successfully', 'creator-core' ),
                'license' => $result,
            ]);
        } else {
            wp_send_json_error( [
                'message' => $result['error'] ?? __( 'License validation failed', 'creator-core' ),
            ]);
        }
    }
}
