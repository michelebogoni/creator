<?php
/**
 * Conversation Manager
 *
 * Gestisce le conversazioni multi-step e la storia dei messaggi.
 * Supporta retry automatico per errori di esecuzione.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Conversation;

defined( 'ABSPATH' ) || exit;

/**
 * Class ConversationManager
 *
 * Manages conversation state, history, and multi-step execution
 */
class ConversationManager {

    /**
     * Maximum retry attempts for failed executions
     *
     * @var int
     */
    private const MAX_RETRY_ATTEMPTS = 3;

    /**
     * Maximum conversation history messages to include
     *
     * @var int
     */
    private const MAX_HISTORY_MESSAGES = 20;

    /**
     * Current chat ID
     *
     * @var int|null
     */
    private ?int $chat_id = null;

    /**
     * Current user ID
     *
     * @var int
     */
    private int $user_id;

    /**
     * Retry counter for current execution
     *
     * @var int
     */
    private int $retry_count = 0;

    /**
     * Constructor
     */
    public function __construct() {
        $this->user_id = get_current_user_id();
    }

    /**
     * Get or create a chat session
     *
     * @param int|null $chat_id Existing chat ID or null to create new.
     * @return int The chat ID.
     */
    public function get_or_create_chat( ?int $chat_id = null ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'creator_chats';

        // If chat_id provided, verify it exists and belongs to user.
        if ( $chat_id ) {
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE id = %d AND user_id = %d",
                    $chat_id,
                    $this->user_id
                )
            );

            if ( $exists ) {
                $this->chat_id = (int) $exists;
                $this->update_chat_timestamp();
                return $this->chat_id;
            }
        }

        // Create new chat.
        $wpdb->insert(
            $table,
            [
                'user_id'    => $this->user_id,
                'title'      => __( 'New Chat', 'creator-core' ),
                'status'     => 'active',
                'ai_model'   => get_option( 'creator_default_model', 'gemini' ),
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s' ]
        );

        $this->chat_id = $wpdb->insert_id;
        return $this->chat_id;
    }

    /**
     * Save a message to the conversation
     *
     * @param string $role    The message role (user/assistant).
     * @param string $content The message content.
     * @param array  $metadata Optional metadata (execution results, etc.).
     * @return int The message ID.
     */
    public function save_message( string $role, string $content, array $metadata = [] ): int {
        global $wpdb;

        if ( ! $this->chat_id ) {
            $this->get_or_create_chat();
        }

        $table = $wpdb->prefix . 'creator_messages';

        $wpdb->insert(
            $table,
            [
                'chat_id'      => $this->chat_id,
                'user_id'      => $this->user_id,
                'role'         => $role,
                'content'      => $content,
                'message_type' => 'text',
                'metadata'     => ! empty( $metadata ) ? wp_json_encode( $metadata ) : null,
                'created_at'   => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%s' ]
        );

        // Update chat title if this is the first user message.
        if ( 'user' === $role ) {
            $this->maybe_update_chat_title( $content );
        }

        return $wpdb->insert_id;
    }

    /**
     * Get conversation history formatted for AI
     *
     * @return array Messages formatted for AI context.
     */
    public function get_history_for_ai(): array {
        global $wpdb;

        if ( ! $this->chat_id ) {
            return [];
        }

        $table    = $wpdb->prefix . 'creator_messages';
        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT role, content, metadata FROM {$table}
                 WHERE chat_id = %d
                 ORDER BY created_at ASC
                 LIMIT %d",
                $this->chat_id,
                self::MAX_HISTORY_MESSAGES
            ),
            ARRAY_A
        );

        if ( ! $messages ) {
            return [];
        }

        return array_map(
            function ( $msg ) {
                return [
                    'role'    => $msg['role'],
                    'content' => $msg['content'],
                ];
            },
            $messages
        );
    }

    /**
     * Check if we should retry a failed execution
     *
     * @param array $execution_result The execution result.
     * @return bool Whether to retry.
     */
    public function should_retry( array $execution_result ): bool {
        // Don't retry successful executions.
        if ( ! empty( $execution_result['success'] ) ) {
            $this->retry_count = 0;
            return false;
        }

        // Check if we've exceeded max retries.
        if ( $this->retry_count >= self::MAX_RETRY_ATTEMPTS ) {
            $this->retry_count = 0;
            return false;
        }

        // Check if error is retryable.
        $error = $execution_result['error'] ?? '';

        // Non-retryable errors.
        $non_retryable = [
            'Forbidden function',
            'Base64 decode is not allowed',
            'Backtick execution',
            'superglobal access',
        ];

        foreach ( $non_retryable as $pattern ) {
            if ( stripos( $error, $pattern ) !== false ) {
                $this->retry_count = 0;
                return false;
            }
        }

        $this->retry_count++;
        return true;
    }

    /**
     * Get retry count
     *
     * @return int Current retry count.
     */
    public function get_retry_count(): int {
        return $this->retry_count;
    }

    /**
     * Reset retry counter
     *
     * @return void
     */
    public function reset_retry_count(): void {
        $this->retry_count = 0;
    }

    /**
     * Build retry message for AI
     *
     * @param array $execution_result The failed execution result.
     * @return string Message to send to AI for retry.
     */
    public function build_retry_message( array $execution_result ): string {
        $error = $execution_result['error'] ?? 'Unknown error';
        $output = $execution_result['output'] ?? '';

        $message = [
            'type'        => 'execution_failed',
            'error'       => $error,
            'output'      => $output,
            'retry_count' => $this->retry_count,
            'max_retries' => self::MAX_RETRY_ATTEMPTS,
            'instruction' => 'Please analyze the error and try a different approach.',
        ];

        return wp_json_encode( $message );
    }

    /**
     * Build continuation message after successful execution
     *
     * @param array $execution_result The execution result.
     * @return string Message to send to AI to continue.
     */
    public function build_continuation_message( array $execution_result ): string {
        $message = [
            'type'   => 'execution_result',
            'result' => $execution_result,
        ];

        return wp_json_encode( $message );
    }

    /**
     * Get chat ID
     *
     * @return int|null The current chat ID.
     */
    public function get_chat_id(): ?int {
        return $this->chat_id;
    }

    /**
     * Set chat ID
     *
     * @param int $chat_id The chat ID.
     * @return void
     */
    public function set_chat_id( int $chat_id ): void {
        $this->chat_id = $chat_id;
    }

    /**
     * Update chat timestamp
     *
     * @return void
     */
    private function update_chat_timestamp(): void {
        global $wpdb;

        if ( ! $this->chat_id ) {
            return;
        }

        $wpdb->update(
            $wpdb->prefix . 'creator_chats',
            [ 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $this->chat_id ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    /**
     * Update chat title based on first message
     *
     * @param string $content The message content.
     * @return void
     */
    private function maybe_update_chat_title( string $content ): void {
        global $wpdb;

        if ( ! $this->chat_id ) {
            return;
        }

        $table = $wpdb->prefix . 'creator_chats';

        // Check if title is still default.
        $current_title = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT title FROM {$table} WHERE id = %d",
                $this->chat_id
            )
        );

        if ( __( 'New Chat', 'creator-core' ) === $current_title ) {
            // Generate title from first message (first 50 chars).
            $title = wp_trim_words( $content, 8, '...' );
            $title = substr( $title, 0, 50 );

            $wpdb->update(
                $table,
                [ 'title' => $title ],
                [ 'id' => $this->chat_id ],
                [ '%s' ],
                [ '%d' ]
            );
        }
    }

    /**
     * Get all chats for current user
     *
     * @param int $limit Maximum chats to return.
     * @return array List of chats.
     */
    public function get_user_chats( int $limit = 50 ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'creator_chats';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, status, created_at, updated_at FROM {$table}
                 WHERE user_id = %d AND status = 'active'
                 ORDER BY updated_at DESC
                 LIMIT %d",
                $this->user_id,
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Archive a chat (soft delete)
     *
     * @param int $chat_id The chat ID to archive.
     * @return bool Success status.
     */
    public function archive_chat( int $chat_id ): bool {
        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'creator_chats',
            [ 'status' => 'archived' ],
            [
                'id'      => $chat_id,
                'user_id' => $this->user_id,
            ],
            [ '%s' ],
            [ '%d', '%d' ]
        );

        return $result !== false;
    }

    /**
     * Get execution summary for a chat
     *
     * @return array Summary of executions in current chat.
     */
    public function get_execution_summary(): array {
        global $wpdb;

        if ( ! $this->chat_id ) {
            return [
                'total_messages'    => 0,
                'user_messages'     => 0,
                'assistant_messages'=> 0,
                'executions'        => 0,
                'successful'        => 0,
                'failed'            => 0,
            ];
        }

        $table = $wpdb->prefix . 'creator_messages';

        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE chat_id = %d",
                $this->chat_id
            )
        );

        $user_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE chat_id = %d AND role = 'user'",
                $this->chat_id
            )
        );

        return [
            'total_messages'     => $total,
            'user_messages'      => $user_count,
            'assistant_messages' => $total - $user_count,
        ];
    }
}
