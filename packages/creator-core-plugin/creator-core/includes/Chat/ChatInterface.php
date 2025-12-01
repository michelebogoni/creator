<?php
/**
 * Chat Interface
 *
 * @package CreatorCore
 */

namespace CreatorCore\Chat;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Integrations\ProxyClient;
use CreatorCore\Permission\CapabilityChecker;
use CreatorCore\Backup\SnapshotManager;
use CreatorCore\Audit\AuditLogger;
use CreatorCore\User\UserProfile;

/**
 * Class ChatInterface
 *
 * Handles the chat interface and messaging
 */
class ChatInterface {

    /**
     * Proxy client instance
     *
     * @var ProxyClient
     */
    private ProxyClient $proxy_client;

    /**
     * Capability checker instance
     *
     * @var CapabilityChecker
     */
    private CapabilityChecker $capability_checker;

    /**
     * Snapshot manager instance
     *
     * @var SnapshotManager
     */
    private SnapshotManager $snapshot_manager;

    /**
     * Audit logger instance
     *
     * @var AuditLogger
     */
    private AuditLogger $logger;

    /**
     * Message handler instance
     *
     * @var MessageHandler
     */
    private MessageHandler $message_handler;

    /**
     * Constructor
     *
     * @param ProxyClient       $proxy_client       Proxy client instance.
     * @param CapabilityChecker $capability_checker Capability checker instance.
     * @param SnapshotManager   $snapshot_manager   Snapshot manager instance.
     * @param AuditLogger       $logger             Audit logger instance.
     */
    public function __construct(
        ProxyClient $proxy_client,
        CapabilityChecker $capability_checker,
        SnapshotManager $snapshot_manager,
        AuditLogger $logger
    ) {
        $this->proxy_client       = $proxy_client;
        $this->capability_checker = $capability_checker;
        $this->snapshot_manager   = $snapshot_manager;
        $this->logger             = $logger;
        $this->message_handler    = new MessageHandler( $logger );
    }

    /**
     * Render the chat interface
     *
     * @return void
     */
    public function render(): void {
        // Check permission
        if ( ! $this->capability_checker->can_use_creator() ) {
            wp_die(
                esc_html__( 'You do not have permission to access Creator.', 'creator-core' ),
                esc_html__( 'Access Denied', 'creator-core' ),
                [ 'response' => 403, 'back_link' => true ]
            );
        }

        // Get or create chat
        $chat_id = isset( $_GET['chat_id'] ) ? absint( $_GET['chat_id'] ) : null;
        $chat    = $chat_id ? $this->get_chat( $chat_id ) : null;

        // Load template
        include CREATOR_CORE_PATH . 'templates/chat-interface.php';
    }

    /**
     * Create a new chat
     *
     * @param string $title Chat title.
     * @return int|false Chat ID or false on failure.
     */
    public function create_chat( string $title = '' ): int {
        global $wpdb;

        $user_id = get_current_user_id();

        if ( empty( $title ) ) {
            $title = sprintf(
                /* translators: %s: Date and time */
                __( 'Chat %s', 'creator-core' ),
                current_time( 'Y-m-d H:i' )
            );
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'creator_chats',
            [
                'user_id'    => $user_id,
                'title'      => sanitize_text_field( $title ),
                'status'     => 'active',
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s' ]
        );

        if ( $result === false ) {
            return false;
        }

        $chat_id = $wpdb->insert_id;

        $this->logger->success( 'chat_created', [
            'chat_id' => $chat_id,
            'title'   => $title,
        ]);

        return $chat_id;
    }

    /**
     * Get chat by ID
     *
     * @param int $chat_id Chat ID.
     * @return array|null
     */
    public function get_chat( int $chat_id ): ?array {
        global $wpdb;

        $chat = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}creator_chats WHERE id = %d",
                $chat_id
            ),
            ARRAY_A
        );

        if ( ! $chat ) {
            return null;
        }

        // Get messages
        $chat['messages'] = $this->get_chat_messages( $chat_id );

        return $chat;
    }

    /**
     * Get user's chats
     *
     * @param int|null $user_id User ID (current user if null).
     * @param array    $args    Query arguments.
     * @return array
     */
    public function get_user_chats( ?int $user_id = null, array $args = [] ): array {
        global $wpdb;

        if ( $user_id === null ) {
            $user_id = get_current_user_id();
        }

        $defaults = [
            'status'   => 'active',
            'per_page' => 20,
            'page'     => 1,
            'orderby'  => 'updated_at',
            'order'    => 'DESC',
        ];

        $args   = wp_parse_args( $args, $defaults );
        $offset = ( $args['page'] - 1 ) * $args['per_page'];

        $where = [ 'user_id = %d' ];
        $values = [ $user_id ];

        if ( $args['status'] !== 'all' ) {
            $where[]  = 'status = %s';
            $values[] = $args['status'];
        }

        $where_clause = implode( ' AND ', $where );
        $orderby      = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );

        $values[] = $args['per_page'];
        $values[] = $offset;

        $chats = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}creator_chats
                 WHERE {$where_clause}
                 ORDER BY {$orderby}
                 LIMIT %d OFFSET %d",
                $values
            ),
            ARRAY_A
        );

        // Add message count to each chat
        foreach ( $chats as &$chat ) {
            $chat['message_count'] = $this->get_message_count( $chat['id'] );
        }

        return $chats;
    }

    /**
     * Get messages for a chat
     *
     * @param int $chat_id Chat ID.
     * @return array
     */
    public function get_chat_messages( int $chat_id ): array {
        return $this->message_handler->get_messages( $chat_id );
    }

    /**
     * Send a message
     *
     * @param int    $chat_id Chat ID.
     * @param string $content Message content.
     * @return array Result with message ID and AI response.
     */
    public function send_message( int $chat_id, string $content ): array {
        // Verify chat exists and belongs to user
        $chat = $this->get_chat( $chat_id );

        if ( ! $chat ) {
            return [
                'success' => false,
                'error'   => __( 'Chat not found', 'creator-core' ),
            ];
        }

        if ( (int) $chat['user_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            return [
                'success' => false,
                'error'   => __( 'Access denied', 'creator-core' ),
            ];
        }

        // Save user message
        $user_message_id = $this->message_handler->save_message( $chat_id, $content, 'user' );

        if ( ! $user_message_id ) {
            return [
                'success' => false,
                'error'   => __( 'Failed to save message', 'creator-core' ),
            ];
        }

        // Build context
        $context_collector = new ContextCollector();
        $context          = $context_collector->get_wordpress_context();

        // Build conversation history
        $history = $this->build_conversation_history( $chat_id );

        // Extract pending actions from previous messages
        $pending_actions = $this->extract_pending_actions( $chat_id );

        // Prepare prompt with context (include pending actions info)
        $prompt = $this->prepare_prompt( $content, $context, $history, $pending_actions );

        // Send to AI
        $ai_response = $this->proxy_client->send_to_ai( $prompt, 'TEXT_GEN', [
            'chat_id'         => $chat_id,
            'message_id'      => $user_message_id,
            'user_message'    => $content, // Original user message for mock mode intent detection
            'pending_actions' => $pending_actions, // Pending actions for confirmation handling
            'conversation'    => $history, // Conversation history for context extraction
        ]);

        if ( ! $ai_response['success'] ) {
            // Save error as assistant message
            $this->message_handler->save_message(
                $chat_id,
                __( 'Sorry, I encountered an error processing your request.', 'creator-core' ),
                'assistant',
                'error',
                [ 'error' => $ai_response['error'] ?? 'Unknown error' ]
            );

            return [
                'success'        => false,
                'user_message_id' => $user_message_id,
                'error'          => $ai_response['error'] ?? __( 'AI request failed', 'creator-core' ),
            ];
        }

        // Parse AI response
        // Firebase returns 'content' key, not 'response'
        $ai_content = $ai_response['content'] ?? $ai_response['response'] ?? '';
        $parsed_response = $this->parse_ai_response( $ai_content );

        // Save assistant message
        // Map Firebase response keys to expected format
        $usage_data = [
            'tokens_used' => $ai_response['tokens_used'] ?? 0,
            'cost_usd'    => $ai_response['cost_usd'] ?? 0,
            'latency_ms'  => $ai_response['latency_ms'] ?? 0,
        ];

        $assistant_message_id = $this->message_handler->save_message(
            $chat_id,
            $parsed_response['message'],
            'assistant',
            $parsed_response['has_actions'] ? 'action' : 'text',
            [
                'actions'   => $parsed_response['actions'],
                'usage'     => $usage_data,
                'provider'  => $ai_response['provider'] ?? 'unknown',
                'model'     => $ai_response['model'] ?? 'unknown',
            ]
        );

        // Update chat timestamp
        $this->update_chat_timestamp( $chat_id );

        $this->logger->success( 'message_sent', [
            'chat_id'      => $chat_id,
            'user_msg_id'  => $user_message_id,
            'ai_msg_id'    => $assistant_message_id,
            'has_actions'  => $parsed_response['has_actions'],
        ]);

        return [
            'success'             => true,
            'user_message_id'     => $user_message_id,
            'assistant_message_id' => $assistant_message_id,
            'response'            => $parsed_response['message'],
            'actions'             => $parsed_response['actions'],
            'usage'               => $usage_data,
        ];
    }

    /**
     * Build conversation history for context
     *
     * @param int $chat_id Chat ID.
     * @param int $limit   Number of messages to include.
     * @return array
     */
    private function build_conversation_history( int $chat_id, int $limit = 10 ): array {
        $messages = $this->message_handler->get_messages( $chat_id, [
            'per_page' => $limit,
            'order'    => 'DESC',
        ]);

        $history = [];

        foreach ( array_reverse( $messages ) as $message ) {
            $history[] = [
                'role'    => $message['role'],
                'content' => $message['content'],
            ];
        }

        return $history;
    }

    /**
     * Prepare prompt with context
     *
     * @param string $user_message    User's message.
     * @param array  $context         WordPress context.
     * @param array  $history         Conversation history.
     * @param array  $pending_actions Pending actions from previous messages.
     * @return string
     */
    private function prepare_prompt( string $user_message, array $context, array $history, array $pending_actions = [] ): string {
        $context_collector = new ContextCollector();
        $context_summary   = $context_collector->get_context_summary();

        // Get user profile AI instructions (level-specific behavior)
        $user_level      = UserProfile::get_level();
        $ai_instructions = UserProfile::get_ai_instructions( $user_level );

        // Get maxi-onboarding summary for comprehensive site context
        $maxi_onboarding = $context_collector->get_maxi_onboarding_summary();

        $prompt = "You are Creator, an AI assistant for WordPress development.\n\n";

        // Include AI behavior instructions based on user level
        $prompt .= "## Your Behavior Guidelines\n";
        $prompt .= $ai_instructions . "\n\n";

        // Include maxi-onboarding (comprehensive site context)
        $prompt .= "## Site Overview (Maxi-Onboarding)\n";
        $prompt .= $maxi_onboarding . "\n\n";

        $prompt .= "## Current WordPress Environment\n";
        $prompt .= $context_summary . "\n\n";

        if ( ! empty( $history ) ) {
            $prompt .= "## Conversation History\n";
            foreach ( $history as $msg ) {
                $prompt .= sprintf( "%s: %s\n", ucfirst( $msg['role'] ), $msg['content'] );
            }
            $prompt .= "\n";
        }

        // Include pending actions info
        if ( ! empty( $pending_actions ) ) {
            $prompt .= "## Pending Actions (waiting for user confirmation)\n";
            foreach ( $pending_actions as $action ) {
                $prompt .= sprintf( "- %s: %s\n", $action['type'], wp_json_encode( $action['params'] ?? [] ) );
            }
            $prompt .= "\n";
            $prompt .= "IMPORTANT: If the user is confirming these actions (saying 'yes', 'ok', 'proceed', 'si', 'procedi', etc.), ";
            $prompt .= "you should execute them. If they are rejecting (saying 'no', 'cancel', 'annulla', etc.), cancel the actions.\n\n";
        }

        $prompt .= "## User Request\n";
        $prompt .= $user_message . "\n\n";

        $prompt .= "## Response Format\n";
        $prompt .= "ALWAYS respond with valid JSON in this format:\n";
        $prompt .= "{\n";
        $prompt .= '  "intent": "action_type or conversation",' . "\n";
        $prompt .= '  "confidence": 0.0-1.0,' . "\n";
        $prompt .= '  "actions": [{"type": "action_name", "params": {...}, "status": "pending|ready"}],' . "\n";
        $prompt .= '  "message": "Your message to the user in their language"' . "\n";
        $prompt .= "}\n\n";
        $prompt .= "Action status meanings:\n";
        $prompt .= "- 'pending': Action needs user confirmation before execution\n";
        $prompt .= "- 'ready': User has confirmed, action should be executed immediately\n\n";
        $prompt .= "For conversations without actions, use actions: [] (empty array).\n";
        $prompt .= "Always respond in the same language the user is using.";

        return $prompt;
    }

    /**
     * Extract pending actions from previous messages
     *
     * @param int $chat_id Chat ID.
     * @return array
     */
    private function extract_pending_actions( int $chat_id ): array {
        $messages = $this->message_handler->get_messages( $chat_id, [
            'per_page' => 5,
            'order'    => 'DESC',
        ]);

        $pending_actions = [];

        foreach ( $messages as $message ) {
            // Only check assistant messages
            if ( $message['role'] !== 'assistant' ) {
                continue;
            }

            // Check if message has actions metadata
            $metadata = $message['metadata'] ?? [];

            if ( is_string( $metadata ) ) {
                $metadata = json_decode( $metadata, true ) ?? [];
            }

            $actions = $metadata['actions'] ?? [];

            foreach ( $actions as $action ) {
                // Include actions that are pending (not yet executed or cancelled)
                $status = $action['status'] ?? 'pending';
                if ( in_array( $status, [ 'pending', 'proposed' ], true ) ) {
                    $pending_actions[] = $action;
                }
            }

            // Only check the most recent assistant message with actions
            if ( ! empty( $pending_actions ) ) {
                break;
            }
        }

        return $pending_actions;
    }

    /**
     * Parse AI response
     *
     * @param string $response AI response.
     * @return array
     */
    private function parse_ai_response( string $response ): array {
        // Try to parse as JSON
        $json = json_decode( $response, true );

        if ( json_last_error() === JSON_ERROR_NONE && is_array( $json ) ) {
            return [
                'message'     => $json['message'] ?? $response,
                'actions'     => $json['actions'] ?? [],
                'intent'      => $json['intent'] ?? null,
                'confidence'  => $json['confidence'] ?? 0,
                'has_actions' => ! empty( $json['actions'] ),
            ];
        }

        // Plain text response
        return [
            'message'     => $response,
            'actions'     => [],
            'intent'      => 'conversation',
            'confidence'  => 1.0,
            'has_actions' => false,
        ];
    }

    /**
     * Update chat title
     *
     * @param int    $chat_id Chat ID.
     * @param string $title   New title.
     * @return bool
     */
    public function update_chat_title( int $chat_id, string $title ): bool {
        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'creator_chats',
            [
                'title'      => sanitize_text_field( $title ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $chat_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        return $result !== false;
    }

    /**
     * Update chat timestamp
     *
     * @param int $chat_id Chat ID.
     * @return bool
     */
    private function update_chat_timestamp( int $chat_id ): bool {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'creator_chats',
            [ 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $chat_id ],
            [ '%s' ],
            [ '%d' ]
        ) !== false;
    }

    /**
     * Archive a chat
     *
     * @param int $chat_id Chat ID.
     * @return bool
     */
    public function archive_chat( int $chat_id ): bool {
        global $wpdb;

        $result = $wpdb->update(
            $wpdb->prefix . 'creator_chats',
            [ 'status' => 'archived', 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $chat_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        if ( $result !== false ) {
            $this->logger->success( 'chat_archived', [ 'chat_id' => $chat_id ] );
        }

        return $result !== false;
    }

    /**
     * Delete a chat
     *
     * @param int $chat_id Chat ID.
     * @return bool
     */
    public function delete_chat( int $chat_id ): bool {
        global $wpdb;

        // Delete messages first
        $wpdb->delete(
            $wpdb->prefix . 'creator_messages',
            [ 'chat_id' => $chat_id ],
            [ '%d' ]
        );

        // Delete chat
        $result = $wpdb->delete(
            $wpdb->prefix . 'creator_chats',
            [ 'id' => $chat_id ],
            [ '%d' ]
        );

        if ( $result !== false ) {
            $this->logger->success( 'chat_deleted', [ 'chat_id' => $chat_id ] );
        }

        return $result !== false;
    }

    /**
     * Get message count for a chat
     *
     * @param int $chat_id Chat ID.
     * @return int
     */
    private function get_message_count( int $chat_id ): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}creator_messages WHERE chat_id = %d",
                $chat_id
            )
        );
    }

    /**
     * Get recent chats for dashboard
     *
     * @param int $limit Number of chats.
     * @return array
     */
    public function get_recent_chats( int $limit = 5 ): array {
        return $this->get_user_chats( null, [
            'per_page' => $limit,
            'status'   => 'active',
        ]);
    }
}
