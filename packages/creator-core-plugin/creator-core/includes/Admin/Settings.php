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
    }

    /**
     * Add settings submenu page
     *
     * The main menu is registered by ChatInterface.
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

        // Register site token setting
        register_setting(
            $this->option_group,
            'creator_site_token',
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

        // Add site token field (read-only, generated after validation)
        add_settings_field(
            'creator_site_token',
            __( 'Site Token', 'creator-core' ),
            [ $this, 'render_site_token_field' ],
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
     * Render license key field
     *
     * @return void
     */
    public function render_license_key_field(): void {
        $license_key = get_option( 'creator_license_key', '' );
        ?>
        <input
            type="text"
            id="creator_license_key"
            name="creator_license_key"
            value="<?php echo esc_attr( $license_key ); ?>"
            class="regular-text"
            placeholder="XXXX-XXXX-XXXX-XXXX"
        />
        <p class="description">
            <?php esc_html_e( 'Your license key was provided when you purchased Creator.', 'creator-core' ); ?>
        </p>
        <?php
    }

    /**
     * Render site token field (read-only)
     *
     * @return void
     */
    public function render_site_token_field(): void {
        $site_token = get_option( 'creator_site_token', '' );

        if ( empty( $site_token ) ) {
            echo '<p class="description">' . esc_html__( 'Site token will be generated after license validation.', 'creator-core' ) . '</p>';
            return;
        }
        ?>
        <input
            type="text"
            id="creator_site_token"
            value="<?php echo esc_attr( $site_token ); ?>"
            class="regular-text"
            readonly
        />
        <p class="description">
            <?php esc_html_e( 'This token identifies your site with the Creator proxy service.', 'creator-core' ); ?>
        </p>
        <?php
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
     * @return array{valid: bool, message: string, site_token?: string}
     */
    public function validate_license( string $license_key ): array {
        if ( empty( $license_key ) ) {
            return [
                'valid'   => false,
                'message' => __( 'License key is required.', 'creator-core' ),
            ];
        }

        // Use ProxyClient to validate
        $proxy = new \CreatorCore\Proxy\ProxyClient();
        $result = $proxy->validate_license( $license_key );

        if ( $result['valid'] && ! empty( $result['site_token'] ) ) {
            update_option( 'creator_site_token', $result['site_token'] );
        }

        return $result;
    }
}
