<?php
/**
 * Admin Settings Page
 *
 * Gestisce la pagina settings con license key.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 *
 * Admin settings page for Creator plugin
 */
class Settings {

    /**
     * Settings page slug
     *
     * @var string
     */
    private string $page_slug = 'creator-settings';

    /**
     * Option group name
     *
     * @var string
     */
    private string $option_group = 'creator_settings';

    /**
     * Initialize the settings page
     *
     * @return void
     */
    public function init(): void {
        add_action( 'admin_menu', [ $this, 'add_menu_page' ], 15 );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_init', [ $this, 'maybe_generate_site_token' ] );
        add_action( 'update_option_creator_license_key', [ $this, 'on_license_key_updated' ], 10, 2 );
        add_action( 'wp_ajax_creator_verify_license', [ $this, 'ajax_verify_license' ] );
    }

    /**
     * Check if site token needs validation
     *
     * Shows admin notice if license key exists but token is missing.
     *
     * @return void
     */
    public function maybe_generate_site_token(): void {
        $license_key = get_option( 'creator_license_key', '' );
        $site_token  = get_option( 'creator_site_token', '' );

        // Show notice if license exists but no token (needs verification)
        if ( ! empty( $license_key ) && empty( $site_token ) ) {
            add_action( 'admin_notices', [ $this, 'show_verify_license_notice' ] );
        }
    }

    /**
     * Show admin notice to verify license
     *
     * @return void
     */
    public function show_verify_license_notice(): void {
        $settings_url = admin_url( 'admin.php?page=creator-settings' );
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong><?php esc_html_e( 'Creator:', 'creator-core' ); ?></strong>
                <?php esc_html_e( 'Please verify your license to enable AI features.', 'creator-core' ); ?>
                <a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Go to Settings', 'creator-core' ); ?></a>
            </p>
        </div>
        <?php
    }

    /**
     * Handle license key update - clear old site token
     *
     * @param mixed $old_value Old option value.
     * @param mixed $new_value New option value.
     * @return void
     */
    public function on_license_key_updated( $old_value, $new_value ): void {
        if ( empty( $new_value ) ) {
            delete_option( 'creator_site_token' );
            delete_option( 'creator_license_status' );
            return;
        }

        // Clear old token when license key changes - user must verify to get new JWT
        if ( $old_value !== $new_value ) {
            delete_option( 'creator_site_token' );
            delete_option( 'creator_license_status' );
        }
    }

    /**
     * Add settings submenu page
     *
     * @return void
     */
    public function add_menu_page(): void {
        add_submenu_page(
            'creator-chat',
            __( 'Settings', 'creator-core' ),
            __( 'Settings', 'creator-core' ),
            'manage_options',
            $this->page_slug,
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Register settings
     *
     * @return void
     */
    public function register_settings(): void {
        // Register license key setting
        register_setting(
            $this->option_group,
            'creator_license_key',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ]
        );

        // Add settings section
        add_settings_section(
            'creator_license_section',
            __( 'License Configuration', 'creator-core' ),
            [ $this, 'render_license_section' ],
            $this->page_slug
        );

        // Add license key field
        add_settings_field(
            'creator_license_key',
            __( 'License Key', 'creator-core' ),
            [ $this, 'render_license_key_field' ],
            $this->page_slug,
            'creator_license_section'
        );
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Check if settings were saved
        if ( isset( $_GET['settings-updated'] ) ) {
            add_settings_error(
                'creator_messages',
                'creator_message',
                __( 'Settings saved.', 'creator-core' ),
                'updated'
            );
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <?php settings_errors( 'creator_messages' ); ?>

            <form action="options.php" method="post">
                <?php
                settings_fields( $this->option_group );
                do_settings_sections( $this->page_slug );
                submit_button( __( 'Save Settings', 'creator-core' ) );
                ?>
            </form>

            <hr>

            <h2><?php esc_html_e( 'System Information', 'creator-core' ); ?></h2>
            <?php $this->render_system_info(); ?>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#creator-verify-license').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $status = $('#creator-license-status');

                $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Verifying...', 'creator-core' ) ); ?>');
                $status.html('').removeClass('notice-success notice-error notice-warning');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'creator_verify_license',
                        nonce: '<?php echo wp_create_nonce( 'creator_verify_license' ); ?>'
                    },
                    success: function(response) {
                        $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Verify License', 'creator-core' ) ); ?>');

                        if (response.success) {
                            var data = response.data;
                            var statusClass = data.valid ? 'notice-success' : 'notice-error';
                            var html = '<p><strong>' + data.status + '</strong></p>';

                            if (data.expires_at) {
                                html += '<p>' + '<?php echo esc_js( __( 'Expires:', 'creator-core' ) ); ?>' + ' ' + data.expires_at + '</p>';
                            }
                            if (data.plan) {
                                html += '<p>' + '<?php echo esc_js( __( 'Plan:', 'creator-core' ) ); ?>' + ' ' + data.plan + '</p>';
                            }

                            $status.addClass('notice ' + statusClass).html(html).show();
                        } else {
                            $status.addClass('notice notice-error').html('<p>' + response.data.message + '</p>').show();
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).text('<?php echo esc_js( __( 'Verify License', 'creator-core' ) ); ?>');
                        $status.addClass('notice notice-error').html('<p><?php echo esc_js( __( 'Connection error. Please try again.', 'creator-core' ) ); ?></p>').show();
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render license section description
     *
     * @return void
     */
    public function render_license_section(): void {
        echo '<p>' . esc_html__( 'Enter your Creator license key to enable AI features.', 'creator-core' ) . '</p>';
    }

    /**
     * Render license key field with verify button
     *
     * @return void
     */
    public function render_license_key_field(): void {
        $license_key    = get_option( 'creator_license_key', '' );
        $license_status = get_option( 'creator_license_status', [] );
        ?>
        <div class="creator-license-field">
            <input
                type="text"
                id="creator_license_key"
                name="creator_license_key"
                value="<?php echo esc_attr( $license_key ); ?>"
                class="regular-text"
                placeholder="CREATOR-XXXX-XXXX-XXXX"
            />

            <?php if ( ! empty( $license_key ) ) : ?>
                <button type="button" id="creator-verify-license" class="button button-secondary">
                    <?php esc_html_e( 'Verify License', 'creator-core' ); ?>
                </button>
            <?php endif; ?>
        </div>

        <div id="creator-license-status" class="notice" style="display: <?php echo ! empty( $license_status ) ? 'block' : 'none'; ?>; margin: 10px 0;">
            <?php if ( ! empty( $license_status ) ) : ?>
                <p><strong><?php echo esc_html( $license_status['status'] ?? '' ); ?></strong></p>
                <?php if ( ! empty( $license_status['expires_at'] ) ) : ?>
                    <p><?php esc_html_e( 'Expires:', 'creator-core' ); ?> <?php echo esc_html( $license_status['expires_at'] ); ?></p>
                <?php endif; ?>
                <?php if ( ! empty( $license_status['plan'] ) ) : ?>
                    <p><?php esc_html_e( 'Plan:', 'creator-core' ); ?> <?php echo esc_html( $license_status['plan'] ); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <p class="description">
            <?php esc_html_e( 'Your license key was provided when you purchased Creator.', 'creator-core' ); ?>
        </p>
        <?php
    }

    /**
     * AJAX handler for license verification
     *
     * @return void
     */
    public function ajax_verify_license(): void {
        check_ajax_referer( 'creator_verify_license', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'creator-core' ) ] );
        }

        $license_key = get_option( 'creator_license_key', '' );

        if ( empty( $license_key ) ) {
            wp_send_json_error( [ 'message' => __( 'No license key configured.', 'creator-core' ) ] );
        }

        // Validate with proxy
        $result = $this->validate_license( $license_key );

        if ( $result['valid'] ) {
            // Save the JWT site_token from Firebase
            if ( ! empty( $result['site_token'] ) ) {
                update_option( 'creator_site_token', $result['site_token'] );
            }

            // Save status for display
            $status_data = [
                'valid'      => true,
                'status'     => __( 'License Valid', 'creator-core' ),
                'expires_at' => $result['expires_at'] ?? '',
                'plan'       => $result['plan'] ?? 'Standard',
                'checked_at' => current_time( 'mysql' ),
            ];
            update_option( 'creator_license_status', $status_data );

            wp_send_json_success( $status_data );
        } else {
            $status_data = [
                'valid'      => false,
                'status'     => $result['message'] ?? __( 'License Invalid', 'creator-core' ),
                'checked_at' => current_time( 'mysql' ),
            ];
            update_option( 'creator_license_status', $status_data );

            wp_send_json_error( [ 'message' => $result['message'] ?? __( 'License validation failed.', 'creator-core' ) ] );
        }
    }

    /**
     * Render system information
     *
     * @return void
     */
    private function render_system_info(): void {
        global $wp_version;

        $info = [
            __( 'WordPress Version', 'creator-core' ) => $wp_version,
            __( 'PHP Version', 'creator-core' )       => PHP_VERSION,
            __( 'Plugin Version', 'creator-core' )    => CREATOR_CORE_VERSION,
            __( 'Proxy URL', 'creator-core' )         => CREATOR_PROXY_URL,
            __( 'Debug Mode', 'creator-core' )        => CREATOR_DEBUG ? __( 'Enabled', 'creator-core' ) : __( 'Disabled', 'creator-core' ),
        ];
        ?>
        <table class="widefat striped">
            <tbody>
                <?php foreach ( $info as $label => $value ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $label ); ?></strong></td>
                        <td><?php echo esc_html( $value ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Get the page slug
     *
     * @return string
     */
    public function get_page_slug(): string {
        return $this->page_slug;
    }

    /**
     * Validate license key with proxy
     *
     * @param string $license_key The license key to validate.
     * @return array{valid: bool, message: string, expires_at?: string, plan?: string}
     */
    public function validate_license( string $license_key ): array {
        if ( empty( $license_key ) ) {
            return [
                'valid'   => false,
                'message' => __( 'License key is required.', 'creator-core' ),
            ];
        }

        // Use ProxyClient to validate
        $proxy  = new \CreatorCore\Proxy\ProxyClient();
        $result = $proxy->validate_license( $license_key );

        return $result;
    }
}
