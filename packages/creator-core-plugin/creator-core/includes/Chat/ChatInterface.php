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
        $current_user = wp_get_current_user();
        wp_localize_script(
            'creator-chat-interface',
            'creatorChat',
            [
                'restUrl'          => rest_url( 'creator/v1/' ),
                'restNonce'        => wp_create_nonce( 'wp_rest' ),
                'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
                'adminUrl'         => admin_url( 'admin.php' ),
                'settingsUrl'      => admin_url( 'admin.php?page=creator-settings' ),
                'userId'           => get_current_user_id(),
                'userName'         => $current_user->display_name,
                'userAvatar'       => get_avatar_url( $current_user->ID, [ 'size' => 64 ] ),
                'pluginUrl'        => CREATOR_CORE_URL,
                'isDebug'          => defined( 'CREATOR_DEBUG' ) && CREATOR_DEBUG,
                'chatId'           => isset( $_GET['chat'] ) ? absint( $_GET['chat'] ) : null,
                'maxFilesPerMessage' => 3,
                'maxFileSize'      => 10 * 1024 * 1024, // 10MB
                'i18n'             => [
                    'sendMessage'    => __( 'Send', 'creator-core' ),
                    'thinking'       => __( 'Thinking...', 'creator-core' ),
                    'error'          => __( 'An error occurred. Please try again.', 'creator-core' ),
                    'placeholder'    => __( 'Type your message...', 'creator-core' ),
                    'newChat'        => __( 'New Chat', 'creator-core' ),
                    'clearHistory'   => __( 'Clear History', 'creator-core' ),
                    'goToSettings'   => __( 'Go to Settings', 'creator-core' ),
                    'maxFilesError'  => __( 'Maximum 3 files allowed per message.', 'creator-core' ),
                    'fileTooLarge'   => __( 'File too large:', 'creator-core' ),
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
                    <div class="creator-welcome-message">
                        <div class="creator-welcome-icon">
                            <span class="dashicons dashicons-format-chat"></span>
                        </div>
                        <h2><?php esc_html_e( 'Welcome to Creator!', 'creator-core' ); ?></h2>
                        <p><?php esc_html_e( 'I\'m your AI assistant for WordPress development. How can I help you today?', 'creator-core' ); ?></p>
                        <div class="creator-suggestions">
                            <button type="button" class="creator-suggestion"><?php esc_html_e( 'What WordPress version am I running?', 'creator-core' ); ?></button>
                            <button type="button" class="creator-suggestion"><?php esc_html_e( 'List my active plugins', 'creator-core' ); ?></button>
                            <button type="button" class="creator-suggestion"><?php esc_html_e( 'What theme is active?', 'creator-core' ); ?></button>
                        </div>
                    </div>
                </div>

                <!-- Chat Input Area -->
                <div class="creator-input-container">
                    <!-- Attachment Preview Area -->
                    <div id="creator-attachment-preview" class="creator-attachment-preview" style="display: none;">
                        <div class="creator-attachment-list"></div>
                    </div>

                    <form id="creator-chat-form" class="creator-chat-form">
                        <div class="creator-input-wrapper">
                            <!-- File Attachment Button -->
                            <button type="button" id="creator-attach-btn" class="creator-attach-btn" title="<?php esc_attr_e( 'Attach files', 'creator-core' ); ?>">
                                <span class="dashicons dashicons-paperclip"></span>
                            </button>
                            <input type="file" id="creator-file-input" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.json,.php,.js,.css,.html" style="display: none;">

                            <textarea
                                id="creator-message-input"
                                class="creator-message-input"
                                placeholder="<?php esc_attr_e( 'Type your message...', 'creator-core' ); ?>"
                                rows="1"
                            ></textarea>

                            <button type="submit" class="creator-send-btn button button-primary">
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                                <span class="creator-send-text"><?php esc_html_e( 'Send', 'creator-core' ); ?></span>
                            </button>
                        </div>

                        <div class="creator-input-info">
                            <span class="creator-attachment-info" style="display: none;">
                                <span class="dashicons dashicons-paperclip"></span>
                                <span class="creator-attachment-count">0</span> <?php esc_html_e( 'files attached', 'creator-core' ); ?>
                            </span>
                        </div>
                    </form>
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
