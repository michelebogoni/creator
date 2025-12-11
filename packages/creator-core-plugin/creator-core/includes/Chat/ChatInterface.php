<?php
/**
 * Chat Interface
 *
 * Registra la pagina admin e gli assets per la chat.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Chat;

defined( 'ABSPATH' ) || exit;

/**
 * Class ChatInterface
 *
 * Handles the chat admin page and assets
 */
class ChatInterface {

    /**
     * Page slug
     *
     * @var string
     */
    private string $page_slug = 'creator-chat';

    /**
     * Page hook suffix
     *
     * @var string
     */
    private string $hook_suffix = '';

    /**
     * Initialize the chat interface
     *
     * @return void
     */
    public function init(): void {
        add_action( 'admin_menu', [ $this, 'add_menu_page' ], 5 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Add chat menu page
     *
     * @return void
     */
    public function add_menu_page(): void {
        $this->hook_suffix = add_menu_page(
            __( 'Creator Chat', 'creator-core' ),
            __( 'Creator', 'creator-core' ),
            'manage_options',
            $this->page_slug,
            [ $this, 'render_chat_page' ],
            'dashicons-format-chat',
            30
        );

        // Add Chat as first submenu (same as parent)
        add_submenu_page(
            $this->page_slug,
            __( 'Chat', 'creator-core' ),
            __( 'Chat', 'creator-core' ),
            'manage_options',
            $this->page_slug,
            [ $this, 'render_chat_page' ]
        );
    }

    /**
     * Enqueue chat assets
     *
     * @param string $hook_suffix The current admin page hook suffix.
     * @return void
     */
    public function enqueue_assets( string $hook_suffix ): void {
        // Only load on chat page
        if ( $hook_suffix !== $this->hook_suffix && $hook_suffix !== 'toplevel_page_' . $this->page_slug ) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'creator-chat-interface',
            CREATOR_CORE_URL . 'assets/css/chat-interface.css',
            [],
            CREATOR_CORE_VERSION
        );

        // Enqueue JS
        wp_enqueue_script(
            'creator-chat-interface',
            CREATOR_CORE_URL . 'assets/js/chat-interface.js',
            [ 'jquery', 'wp-api-fetch' ],
            CREATOR_CORE_VERSION,
            true
        );

        // Localize script with data
        wp_localize_script(
            'creator-chat-interface',
            'creatorChat',
            [
                'restUrl'     => rest_url( 'creator/v1/' ),
                'nonce'       => wp_create_nonce( 'wp_rest' ),
                'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                'userId'      => get_current_user_id(),
                'pluginUrl'   => CREATOR_CORE_URL,
                'isDebug'     => CREATOR_DEBUG,
                'i18n'        => [
                    'sendMessage'    => __( 'Send', 'creator-core' ),
                    'thinking'       => __( 'Thinking...', 'creator-core' ),
                    'error'          => __( 'An error occurred. Please try again.', 'creator-core' ),
                    'placeholder'    => __( 'Type your message...', 'creator-core' ),
                    'newChat'        => __( 'New Chat', 'creator-core' ),
                    'clearHistory'   => __( 'Clear History', 'creator-core' ),
                ],
            ]
        );
    }

    /**
     * Render chat page
     *
     * @return void
     */
    public function render_chat_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'creator-core' ) );
        }

        // Check if license is configured
        $license_key = get_option( 'creator_license_key', '' );
        $site_token  = get_option( 'creator_site_token', '' );

        if ( empty( $license_key ) || empty( $site_token ) ) {
            $this->render_setup_notice();
            return;
        }

        $this->render_chat_interface();
    }

    /**
     * Render setup notice when license is not configured
     *
     * @return void
     */
    private function render_setup_notice(): void {
        $settings_url = admin_url( 'admin.php?page=creator-settings' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <div class="notice notice-warning">
                <p>
                    <strong><?php esc_html_e( 'Setup Required', 'creator-core' ); ?></strong>
                </p>
                <p>
                    <?php esc_html_e( 'Please configure your license key to use Creator Chat.', 'creator-core' ); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url( $settings_url ); ?>" class="button button-primary">
                        <?php esc_html_e( 'Go to Settings', 'creator-core' ); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render the chat interface
     *
     * @return void
     */
    private function render_chat_interface(): void {
        ?>
        <div class="wrap creator-chat-wrap">
            <div id="creator-chat-app" class="creator-chat-container">
                <!-- Chat Header -->
                <div class="creator-chat-header">
                    <h1><?php esc_html_e( 'Creator Chat', 'creator-core' ); ?></h1>
                    <div class="creator-chat-actions">
                        <button type="button" id="creator-new-chat" class="button">
                            <?php esc_html_e( 'New Chat', 'creator-core' ); ?>
                        </button>
                    </div>
                </div>

                <!-- Chat Messages Area -->
                <div id="creator-chat-messages" class="creator-chat-messages">
                    <div class="creator-chat-welcome">
                        <p><?php esc_html_e( 'Welcome to Creator! How can I help you today?', 'creator-core' ); ?></p>
                    </div>
                </div>

                <!-- Chat Input Area -->
                <div class="creator-chat-input-area">
                    <form id="creator-chat-form" class="creator-chat-form">
                        <textarea
                            id="creator-chat-input"
                            class="creator-chat-input"
                            placeholder="<?php esc_attr_e( 'Type your message...', 'creator-core' ); ?>"
                            rows="3"
                        ></textarea>
                        <button type="submit" id="creator-send-btn" class="button button-primary">
                            <?php esc_html_e( 'Send', 'creator-core' ); ?>
                        </button>
                    </form>
                </div>

                <!-- Loading Indicator -->
                <div id="creator-chat-loading" class="creator-chat-loading" style="display: none;">
                    <span class="spinner is-active"></span>
                    <span><?php esc_html_e( 'Thinking...', 'creator-core' ); ?></span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get page slug
     *
     * @return string
     */
    public function get_page_slug(): string {
        return $this->page_slug;
    }

    /**
     * Get hook suffix
     *
     * @return string
     */
    public function get_hook_suffix(): string {
        return $this->hook_suffix;
    }
}
