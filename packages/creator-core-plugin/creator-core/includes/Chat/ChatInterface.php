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
        add_action( 'admin_menu', [ $this, 'add_menu_page' ], 15 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Add chat menu page as submenu of Dashboard
     *
     * @return void
     */
    public function add_menu_page(): void {
        // Add Chat as submenu under Creator Dashboard.
        $this->hook_suffix = add_submenu_page(
            'creator-dashboard',
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

        // Enqueue Debug Panel JS
        wp_enqueue_script(
            'creator-debug-panel',
            CREATOR_CORE_URL . 'assets/js/debug-panel.js',
            [ 'jquery', 'creator-chat-interface' ],
            CREATOR_CORE_VERSION,
            true
        );

        // Get chat ID from URL.
        $chat_id = isset( $_GET['chat'] ) ? absint( $_GET['chat'] ) : null;

        // Load conversation history if chat ID is present.
        $conversation_history = [];
        if ( $chat_id ) {
            $conversation_history = $this->get_conversation_history( $chat_id );
        }

        // Localize script with data.
        $current_user = wp_get_current_user();
        wp_localize_script(
            'creator-chat-interface',
            'creatorChat',
            [
                'restUrl'             => rest_url( 'creator/v1/' ),
                'restNonce'           => wp_create_nonce( 'wp_rest' ),
                'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
                'adminUrl'            => admin_url( 'admin.php' ),
                'dashboardUrl'        => admin_url( 'admin.php?page=creator-dashboard' ),
                'settingsUrl'         => admin_url( 'admin.php?page=creator-dashboard' ),
                'userId'              => get_current_user_id(),
                'userName'            => $current_user->display_name,
                'userAvatar'          => get_avatar_url( $current_user->ID, [ 'size' => 64 ] ),
                'pluginUrl'           => CREATOR_CORE_URL,
                'isDebug'             => defined( 'CREATOR_DEBUG' ) && CREATOR_DEBUG,
                'chatId'              => $chat_id,
                'conversationHistory' => $conversation_history,
                'maxFilesPerMessage'  => 3,
                'maxFileSize'         => 10 * 1024 * 1024, // 10MB
                'i18n'                => [
                    'sendMessage'    => __( 'Send', 'creator-core' ),
                    'thinking'       => __( 'Thinking...', 'creator-core' ),
                    'error'          => __( 'An error occurred. Please try again.', 'creator-core' ),
                    'placeholder'    => __( 'Type your message...', 'creator-core' ),
                    'newChat'        => __( 'New Chat', 'creator-core' ),
                    'clearHistory'   => __( 'Clear History', 'creator-core' ),
                    'goToSettings'   => __( 'Go to Dashboard', 'creator-core' ),
                    'maxFilesError'  => __( 'Maximum 3 files allowed per message.', 'creator-core' ),
                    'fileTooLarge'   => __( 'File too large:', 'creator-core' ),
                ],
            ]
        );
    }

    /**
     * Get conversation history for a chat
     *
     * @param int $chat_id The chat ID.
     * @return array Array of messages.
     */
    private function get_conversation_history( int $chat_id ): array {
        global $wpdb;

        $user_id        = get_current_user_id();
        $chats_table    = $wpdb->prefix . 'creator_chats';
        $messages_table = $wpdb->prefix . 'creator_messages';

        // Verify ownership.
        $chat = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$chats_table} WHERE id = %d AND user_id = %d AND status = 'active'",
                $chat_id,
                $user_id
            )
        );

        if ( ! $chat ) {
            return [];
        }

        // Get messages.
        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT role, content, created_at FROM {$messages_table}
                WHERE chat_id = %d
                ORDER BY created_at ASC",
                $chat_id
            ),
            ARRAY_A
        );

        if ( ! $messages ) {
            return [];
        }

        // Format messages for frontend.
        return array_map(
            function ( $msg ) {
                return [
                    'role'       => $msg['role'],
                    'content'    => $msg['content'],
                    'created_at' => $msg['created_at'],
                ];
            },
            $messages
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
        $dashboard_url = admin_url( 'admin.php?page=creator-dashboard' );
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
                    <a href="<?php echo esc_url( $dashboard_url ); ?>" class="button button-primary">
                        <?php esc_html_e( 'Go to Dashboard', 'creator-core' ); ?>
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
                        <button type="button" id="creator-debug-btn" class="button" title="<?php esc_attr_e( 'Debug Logs', 'creator-core' ); ?>">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php esc_html_e( 'Debug', 'creator-core' ); ?>
                        </button>
                        <button type="button" id="creator-new-chat" class="button">
                            <?php esc_html_e( 'New Chat', 'creator-core' ); ?>
                        </button>
                    </div>
                </div>

                <!-- Chat Messages Area -->
                <div id="creator-chat-messages" class="creator-chat-messages">
                    <div class="creator-welcome-message">
                        <div class="creator-welcome-logo">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 500">
                                <circle cx="250" cy="250" r="245"/>
                                <polygon points="115.93 250 304.81 61.12 344.06 100.38 194.43 250 344.06 399.62 304.81 438.88 115.93 250" style="fill: #fff;"/>
                            </svg>
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

            <!-- Debug Panel Modal -->
            <div id="creator-debug-panel" class="creator-debug-panel" style="display: none;">
                <div class="creator-debug-panel-overlay"></div>
                <div class="creator-debug-panel-content">
                    <div class="creator-debug-panel-header">
                        <h2><?php esc_html_e( 'Debug Logs', 'creator-core' ); ?></h2>
                        <div class="creator-debug-panel-actions">
                            <button type="button" class="button" id="creator-debug-refresh">
                                <span class="dashicons dashicons-update"></span>
                                <?php esc_html_e( 'Refresh', 'creator-core' ); ?>
                            </button>
                            <button type="button" class="button" id="creator-debug-clear">
                                <span class="dashicons dashicons-trash"></span>
                                <?php esc_html_e( 'Clear', 'creator-core' ); ?>
                            </button>
                            <button type="button" class="creator-debug-close" id="creator-debug-close">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                    </div>
                    <div class="creator-debug-panel-body">
                        <div class="creator-debug-sessions">
                            <h3><?php esc_html_e( 'Sessions', 'creator-core' ); ?></h3>
                            <div id="creator-debug-sessions-list" class="creator-debug-sessions-list">
                                <p class="loading"><?php esc_html_e( 'Loading sessions...', 'creator-core' ); ?></p>
                            </div>
                        </div>
                        <div class="creator-debug-logs">
                            <h3><?php esc_html_e( 'Log Details', 'creator-core' ); ?></h3>
                            <div id="creator-debug-logs-content" class="creator-debug-logs-content">
                                <p class="placeholder"><?php esc_html_e( 'Select a session to view logs', 'creator-core' ); ?></p>
                            </div>
                        </div>
                    </div>
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
