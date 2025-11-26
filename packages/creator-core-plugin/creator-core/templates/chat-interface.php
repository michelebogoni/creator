<?php
/**
 * Chat Interface Template
 *
 * @package CreatorCore
 * @var array|null $chat Current chat data
 * @var int|null $chat_id Current chat ID
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap creator-chat-wrap">
    <div class="creator-chat-container" data-chat-id="<?php echo esc_attr( $chat_id ?? '' ); ?>">
        <!-- Chat Header -->
        <div class="creator-chat-header">
            <div class="creator-chat-header-left">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=creator-dashboard' ) ); ?>" class="creator-back-btn">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                </a>
                <h1 class="creator-chat-title">
                    <?php if ( $chat ) : ?>
                        <span class="title-text"><?php echo esc_html( $chat['title'] ); ?></span>
                        <button type="button" class="creator-edit-title" title="<?php esc_attr_e( 'Edit title', 'creator-core' ); ?>">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                    <?php else : ?>
                        <?php esc_html_e( 'New Chat', 'creator-core' ); ?>
                    <?php endif; ?>
                </h1>
            </div>
            <div class="creator-chat-header-right">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=creator-settings' ) ); ?>" class="creator-header-btn" title="<?php esc_attr_e( 'Settings', 'creator-core' ); ?>">
                    <span class="dashicons dashicons-admin-generic"></span>
                </a>
                <button type="button" class="creator-header-btn creator-help-btn" title="<?php esc_attr_e( 'Help', 'creator-core' ); ?>">
                    <span class="dashicons dashicons-editor-help"></span>
                </button>
            </div>
        </div>

        <!-- Chat Messages -->
        <div class="creator-chat-messages" id="creator-messages">
            <?php if ( $chat && ! empty( $chat['messages'] ) ) : ?>
                <?php foreach ( $chat['messages'] as $message ) : ?>
                    <div class="creator-message creator-message-<?php echo esc_attr( $message['role'] ); ?>" data-message-id="<?php echo esc_attr( $message['id'] ); ?>">
                        <div class="creator-message-avatar">
                            <?php if ( $message['role'] === 'user' ) : ?>
                                <?php echo get_avatar( get_current_user_id(), 32 ); ?>
                            <?php else : ?>
                                <span class="creator-ai-avatar">
                                    <span class="dashicons dashicons-superhero-alt"></span>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="creator-message-content">
                            <div class="creator-message-header">
                                <span class="creator-message-sender">
                                    <?php echo $message['role'] === 'user' ? esc_html( wp_get_current_user()->display_name ) : esc_html__( 'Creator', 'creator-core' ); ?>
                                </span>
                                <span class="creator-message-time"><?php echo esc_html( human_time_diff( strtotime( $message['created_at'] ) ) ); ?></span>
                            </div>
                            <div class="creator-message-body">
                                <?php echo wp_kses_post( $message['content'] ); ?>
                            </div>

                            <?php if ( ! empty( $message['actions'] ) ) : ?>
                                <div class="creator-message-actions">
                                    <?php foreach ( $message['actions'] as $action ) : ?>
                                        <?php include CREATOR_CORE_PATH . 'templates/action-card.php'; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="creator-welcome-message">
                    <div class="creator-welcome-icon">
                        <span class="dashicons dashicons-superhero-alt"></span>
                    </div>
                    <h2><?php esc_html_e( 'Welcome to Creator!', 'creator-core' ); ?></h2>
                    <p><?php esc_html_e( 'I can help you create pages, posts, manage Elementor, and much more. How can I help you today?', 'creator-core' ); ?></p>
                    <div class="creator-suggestions">
                        <button type="button" class="creator-suggestion" data-prompt="<?php esc_attr_e( 'Create a new About page', 'creator-core' ); ?>">
                            <?php esc_html_e( 'Create About page', 'creator-core' ); ?>
                        </button>
                        <button type="button" class="creator-suggestion" data-prompt="<?php esc_attr_e( 'Write a blog post about', 'creator-core' ); ?>">
                            <?php esc_html_e( 'Write a blog post', 'creator-core' ); ?>
                        </button>
                        <button type="button" class="creator-suggestion" data-prompt="<?php esc_attr_e( 'Help me with SEO', 'creator-core' ); ?>">
                            <?php esc_html_e( 'Help with SEO', 'creator-core' ); ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Chat Input -->
        <div class="creator-chat-input-container">
            <form id="creator-chat-form" class="creator-chat-form">
                <?php wp_nonce_field( 'creator_chat_nonce', 'creator_nonce' ); ?>
                <input type="hidden" name="chat_id" id="creator-chat-id" value="<?php echo esc_attr( $chat_id ?? '' ); ?>">

                <div class="creator-input-wrapper">
                    <textarea
                        id="creator-message-input"
                        name="message"
                        placeholder="<?php esc_attr_e( 'Type your message...', 'creator-core' ); ?>"
                        rows="1"
                        class="creator-message-input"
                    ></textarea>

                    <div class="creator-input-actions">
                        <button type="submit" class="creator-send-btn" id="creator-send-btn">
                            <span class="dashicons dashicons-arrow-right-alt"></span>
                            <span class="sr-only"><?php esc_html_e( 'Send', 'creator-core' ); ?></span>
                        </button>
                    </div>
                </div>

                <div class="creator-input-info">
                    <span class="creator-typing-indicator" style="display: none;">
                        <span class="dot"></span>
                        <span class="dot"></span>
                        <span class="dot"></span>
                        <?php esc_html_e( 'Creator is typing...', 'creator-core' ); ?>
                    </span>
                    <span class="creator-mock-badge" style="<?php echo defined( 'CREATOR_MOCK_MODE' ) && CREATOR_MOCK_MODE ? '' : 'display:none;'; ?>">
                        <?php esc_html_e( 'Mock Mode', 'creator-core' ); ?>
                    </span>
                </div>
            </form>
        </div>
    </div>
</div>
