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
use CreatorCore\User\UserProfile;

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
     * Step 1: Safety Warning (must accept responsibility)
     * Step 2: System Overview (info + optional plugin suggestions)
     * Step 3: Backup Configuration
     * Step 4: License Activation
     * Step 5: Profile Selection
     * Step 6: Finish
     *
     * @var array
     */
    private array $steps = [
        'safety' => [
            'name'  => 'Safety Notice',
            'order' => 1,
        ],
        'overview' => [
            'name'  => 'System Overview',
            'order' => 2,
        ],
        'backup' => [
            'name'  => 'Configure Backup',
            'order' => 3,
        ],
        'license' => [
            'name'  => 'License Activation',
            'order' => 4,
        ],
        'profile' => [
            'name'  => 'Your Profile',
            'order' => 5,
        ],
        'finish' => [
            'name'  => 'Ready to Go',
            'order' => 6,
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
        add_action( 'wp_ajax_creator_wizard_validate_license', [ $this, 'ajax_validate_license' ] );
        add_action( 'wp_ajax_creator_save_user_profile', [ $this, 'ajax_save_user_profile' ] );
        add_action( 'wp_ajax_creator_accept_safety', [ $this, 'ajax_accept_safety' ] );
        add_action( 'wp_ajax_creator_dismiss_plugin_suggestion', [ $this, 'ajax_dismiss_plugin_suggestion' ] );
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
        $current_step = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : 'safety';

        if ( ! isset( $this->steps[ $current_step ] ) ) {
            $current_step = 'safety';
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
            case 'safety':
                return $this->get_safety_data();

            case 'overview':
                return $this->get_overview_data();

            case 'backup':
                return $this->get_backup_data();

            case 'license':
                return $this->get_license_data();

            case 'profile':
                return $this->get_profile_data();

            case 'finish':
                return $this->get_finish_data();

            default:
                return [];
        }
    }

    /**
     * Get safety step data
     *
     * @return array
     */
    private function get_safety_data(): array {
        return [
            'already_accepted' => (bool) get_option( 'creator_user_accepted_responsibility', false ),
            'accepted_at'      => get_option( 'creator_user_accepted_responsibility_at', null ),
        ];
    }

    /**
     * Get system overview step data
     *
     * @return array
     */
    private function get_overview_data(): array {
        global $wp_version, $wpdb;

        // Get all active plugins
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins    = get_plugins();
        $active_plugins = get_option( 'active_plugins', [] );
        $plugins_list   = [];

        foreach ( $active_plugins as $plugin_path ) {
            if ( isset( $all_plugins[ $plugin_path ] ) ) {
                $plugin = $all_plugins[ $plugin_path ];
                $plugins_list[] = [
                    'name'    => $plugin['Name'],
                    'version' => $plugin['Version'],
                    'slug'    => dirname( $plugin_path ),
                ];
            }
        }

        // Get CPTs
        $cpts = get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' );
        $cpt_count = count( $cpts );

        // Get taxonomies
        $taxonomies = get_taxonomies( [ 'public' => true, '_builtin' => false ], 'objects' );
        $tax_count = count( $taxonomies );

        // Get users
        $users = count_users();

        // Get theme info
        $theme = wp_get_theme();

        // Get database size
        $db_size = $this->get_database_size();

        // Get suggested plugins (not installed/active)
        $suggested = $this->plugin_detector->get_suggested_plugins();
        $dismissed = get_option( 'creator_dismissed_plugin_suggestions', [] );

        // Filter out dismissed suggestions
        foreach ( $dismissed as $slug ) {
            unset( $suggested[ $slug ] );
        }

        return [
            'system' => [
                'wordpress_version' => $wp_version,
                'php_version'       => PHP_VERSION,
                'mysql_version'     => $wpdb->db_version(),
                'db_size'           => $db_size,
            ],
            'plugins' => [
                'count' => count( $active_plugins ),
                'list'  => $plugins_list,
            ],
            'theme' => [
                'name'        => $theme->get( 'Name' ),
                'version'     => $theme->get( 'Version' ),
                'is_child'    => $theme->parent() !== false,
                'parent_name' => $theme->parent() ? $theme->parent()->get( 'Name' ) : null,
            ],
            'content' => [
                'cpt_count'      => $cpt_count,
                'taxonomy_count' => $tax_count,
                'users_total'    => $users['total_users'],
                'users_by_role'  => $users['avail_roles'],
            ],
            'suggested_plugins' => $suggested,
            'integrations'      => $this->plugin_detector->get_all_integrations(),
            'features'          => $this->plugin_detector->get_available_features(),
        ];
    }

    /**
     * Get database size in MB
     *
     * @return string
     */
    private function get_database_size(): string {
        global $wpdb;

        $db_name = DB_NAME;
        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(data_length + index_length) / 1024 / 1024
                 FROM information_schema.tables
                 WHERE table_schema = %s",
                $db_name
            )
        );

        return $result ? number_format( (float) $result, 1 ) . ' MB' : 'Unknown';
    }

    /**
     * Get dependencies step data (kept for backward compatibility)
     *
     * @return array
     * @deprecated Use get_overview_data() instead
     */
    private function get_dependencies_data(): array {
        return $this->get_overview_data();
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
     * Get profile step data
     *
     * @return array
     */
    private function get_profile_data(): array {
        return [
            'current_level' => UserProfile::get_level(),
            'levels'        => UserProfile::get_levels_info(),
            'is_set'        => UserProfile::is_level_set(),
        ];
    }

    /**
     * Get finish step data
     *
     * @return array
     */
    private function get_finish_data(): array {
        // Mark setup as complete when finish step is displayed
        // This ensures the user can navigate away without being redirected back
        update_option( 'creator_setup_completed', true );

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

            case 'profile':
                $this->process_profile_step( $data );
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
     * Process profile step
     *
     * @param array $data Form data.
     * @return void
     */
    private function process_profile_step( array $data ): void {
        $level = isset( $data['user_level'] ) ? sanitize_key( $data['user_level'] ) : '';

        if ( empty( $level ) ) {
            wp_send_json_error( [ 'message' => __( 'Please select your competency level', 'creator-core' ) ] );
        }

        if ( ! in_array( $level, UserProfile::get_valid_levels(), true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid competency level', 'creator-core' ) ] );
        }

        $saved = UserProfile::set_level( $level );

        if ( $saved ) {
            wp_send_json_success( [
                'message'  => __( 'Profile saved', 'creator-core' ),
                'next_url' => $this->get_next_step_url( 'profile' ),
            ]);
        } else {
            wp_send_json_error( [ 'message' => __( 'Failed to save profile', 'creator-core' ) ] );
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

    /**
     * AJAX: Save user profile (competency level and default AI model)
     *
     * @return void
     */
    public function ajax_save_user_profile(): void {
        check_ajax_referer( 'creator_setup_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'creator-core' ) ] );
        }

        $level = isset( $_POST['user_level'] ) ? sanitize_key( wp_unslash( $_POST['user_level'] ) ) : '';
        $model = isset( $_POST['default_model'] ) ? sanitize_key( wp_unslash( $_POST['default_model'] ) ) : '';

        if ( empty( $level ) ) {
            wp_send_json_error( [ 'message' => __( 'Please select your competency level', 'creator-core' ) ] );
        }

        if ( ! in_array( $level, UserProfile::get_valid_levels(), true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid competency level', 'creator-core' ) ] );
        }

        // Validate model if provided
        if ( ! empty( $model ) && ! in_array( $model, UserProfile::get_valid_models(), true ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid AI model', 'creator-core' ) ] );
        }

        // Save competency level
        $level_saved = UserProfile::set_level( $level );

        // Save default model if provided
        $model_saved = true;
        if ( ! empty( $model ) ) {
            $model_saved = UserProfile::set_default_model( $model );
        }

        if ( $level_saved && $model_saved ) {
            $levels_info = UserProfile::get_levels_info();
            $models_info = UserProfile::get_models_info();

            $response = [
                'message' => sprintf(
                    /* translators: %s: Level label */
                    __( 'Profile set to: %s', 'creator-core' ),
                    $levels_info[ $level ]['label']
                ),
                'level'   => $level,
                'label'   => $levels_info[ $level ]['label'],
            ];

            if ( ! empty( $model ) ) {
                $response['model']       = $model;
                $response['model_label'] = $models_info[ $model ]['label'];
            }

            wp_send_json_success( $response );
        } else {
            wp_send_json_error( [ 'message' => __( 'Failed to save profile', 'creator-core' ) ] );
        }
    }

    /**
     * AJAX: Accept safety responsibility
     *
     * User must explicitly accept responsibility before using Creator.
     * This is logged with timestamp for audit purposes.
     *
     * @return void
     */
    public function ajax_accept_safety(): void {
        check_ajax_referer( 'creator_setup_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'creator-core' ) ] );
        }

        $accepted = isset( $_POST['accepted'] ) && $_POST['accepted'] === 'true';

        if ( ! $accepted ) {
            wp_send_json_error( [
                'message' => __( 'You must accept the responsibility notice to use Creator', 'creator-core' ),
            ]);
        }

        // Save acceptance with timestamp and user info
        $user_id = get_current_user_id();
        $user = get_userdata( $user_id );

        update_option( 'creator_user_accepted_responsibility', true );
        update_option( 'creator_user_accepted_responsibility_at', current_time( 'mysql' ) );
        update_option( 'creator_user_accepted_responsibility_by', [
            'user_id'  => $user_id,
            'username' => $user ? $user->user_login : 'unknown',
            'ip'       => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ),
        ]);

        // Log to audit trail if available
        do_action( 'creator_audit_log', 'safety_accepted', [
            'user_id'  => $user_id,
            'accepted' => true,
        ]);

        wp_send_json_success( [
            'message'  => __( 'Responsibility accepted', 'creator-core' ),
            'next_url' => $this->get_next_step_url( 'safety' ),
        ]);
    }

    /**
     * AJAX: Dismiss plugin suggestion
     *
     * User can dismiss suggested plugins permanently.
     *
     * @return void
     */
    public function ajax_dismiss_plugin_suggestion(): void {
        check_ajax_referer( 'creator_setup_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'creator-core' ) ] );
        }

        $plugin_slug = isset( $_POST['plugin'] ) ? sanitize_key( $_POST['plugin'] ) : '';

        if ( empty( $plugin_slug ) ) {
            wp_send_json_error( [ 'message' => __( 'Plugin slug required', 'creator-core' ) ] );
        }

        // Get current dismissed list
        $dismissed = get_option( 'creator_dismissed_plugin_suggestions', [] );

        // Add to dismissed list if not already there
        if ( ! in_array( $plugin_slug, $dismissed, true ) ) {
            $dismissed[] = $plugin_slug;
            update_option( 'creator_dismissed_plugin_suggestions', $dismissed );
        }

        wp_send_json_success( [
            'message' => __( 'Plugin suggestion dismissed', 'creator-core' ),
        ]);
    }
}
