<?php
/**
 * Chat Controller
 *
 * REST endpoint POST /creator/v1/chat per gestire i messaggi.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Chat;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Context\ContextLoader;
use CreatorCore\Proxy\ProxyClient;
use CreatorCore\Response\ResponseHandler;
use CreatorCore\Executor\CodeExecutor;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class ChatController
 *
 * Handles REST API endpoints for chat functionality
 */
class ChatController {

    /**
     * REST namespace
     *
     * @var string
     */
    private string $namespace = 'creator/v1';

    /**
     * Register REST routes
     *
     * @return void
     */
    public function register_routes(): void {
        // POST /creator/v1/chat - Send a message
        register_rest_route(
            $this->namespace,
            '/chat',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_chat_message' ],
                'permission_callback' => [ $this, 'check_permission' ],
                'args'                => [
                    'message' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field',
                        'validate_callback' => function( $value ) {
                            return ! empty( trim( $value ) );
                        },
                    ],
                    'chat_id' => [
                        'required'          => false,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );

        // GET /creator/v1/chat/history - Get chat history
        register_rest_route(
            $this->namespace,
            '/chat/history',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_chat_history' ],
                'permission_callback' => [ $this, 'check_permission' ],
                'args'                => [
                    'chat_id' => [
                        'required'          => false,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'limit' => [
                        'required'          => false,
                        'type'              => 'integer',
                        'default'           => 50,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );

        // POST /creator/v1/chat/new - Create new chat
        register_rest_route(
            $this->namespace,
            '/chat/new',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'create_new_chat' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ]
        );
    }

    /**
     * Check user permission
     *
     * @return bool|WP_Error
     */
    public function check_permission() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to access this endpoint.', 'creator-core' ),
                [ 'status' => 403 ]
            );
        }

        return true;
    }

    /**
     * Handle incoming chat message
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_chat_message( WP_REST_Request $request ) {
        $message = $request->get_param( 'message' );
        $chat_id = $request->get_param( 'chat_id' );

        // Validate license
        $site_token = get_option( 'creator_site_token', '' );
        if ( empty( $site_token ) ) {
            return new WP_Error(
                'license_required',
                __( 'Please configure your license key in settings.', 'creator-core' ),
                [ 'status' => 403 ]
            );
        }

        try {
            // Get or create chat
            $chat_id = $this->get_or_create_chat( $chat_id );

            // Save user message
            $this->save_message( $chat_id, 'user', $message );

            // Gather WordPress context
            $context_loader = new ContextLoader();
            $context = $context_loader->get_context();

            // Send to proxy
            $proxy = new ProxyClient();
            $proxy_response = $proxy->send_message( $message, $context, $chat_id );

            if ( is_wp_error( $proxy_response ) ) {
                return $proxy_response;
            }

            // Parse response
            $response_handler = new ResponseHandler();
            $parsed_response = $response_handler->parse( $proxy_response );

            // Execute code if present
            if ( $parsed_response['has_code'] ) {
                $executor = new CodeExecutor();
                $execution_result = $executor->execute( $parsed_response['code'] );
                $parsed_response['execution_result'] = $execution_result;
            }

            // Save assistant message
            $this->save_message( $chat_id, 'assistant', $parsed_response['text'] );

            return new WP_REST_Response(
                [
                    'success'  => true,
                    'chat_id'  => $chat_id,
                    'response' => $parsed_response,
                ],
                200
            );

        } catch ( \Exception $e ) {
            return new WP_Error(
                'chat_error',
                $e->getMessage(),
                [ 'status' => 500 ]
            );
        }
    }

    /**
     * Get chat history
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_chat_history( WP_REST_Request $request ) {
        global $wpdb;

        $chat_id = $request->get_param( 'chat_id' );
        $limit   = min( $request->get_param( 'limit' ), 100 );
        $user_id = get_current_user_id();

        $table_messages = $wpdb->prefix . 'creator_messages';
        $table_chats    = $wpdb->prefix . 'creator_chats';

        // If chat_id is provided, get messages for that chat
        if ( $chat_id ) {
            // Verify chat belongs to user
            $chat = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table_chats} WHERE id = %d AND user_id = %d",
                    $chat_id,
                    $user_id
                )
            );

            if ( ! $chat ) {
                return new WP_Error(
                    'chat_not_found',
                    __( 'Chat not found.', 'creator-core' ),
                    [ 'status' => 404 ]
                );
            }

            $messages = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, role, content, created_at FROM {$table_messages}
                     WHERE chat_id = %d ORDER BY created_at ASC LIMIT %d",
                    $chat_id,
                    $limit
                )
            );

            return new WP_REST_Response(
                [
                    'success'  => true,
                    'chat'     => $chat,
                    'messages' => $messages,
                ],
                200
            );
        }

        // Otherwise, get list of user's chats
        $chats = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, status, created_at, updated_at FROM {$table_chats}
                 WHERE user_id = %d AND status = 'active'
                 ORDER BY updated_at DESC LIMIT %d",
                $user_id,
                $limit
            )
        );

        return new WP_REST_Response(
            [
                'success' => true,
                'chats'   => $chats,
            ],
            200
        );
    }

    /**
     * Create a new chat
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error
     */
    public function create_new_chat( WP_REST_Request $request ) {
        global $wpdb;

        $user_id = get_current_user_id();
        $table   = $wpdb->prefix . 'creator_chats';

        $result = $wpdb->insert(
            $table,
            [
                'user_id'    => $user_id,
                'title'      => __( 'New Chat', 'creator-core' ),
                'status'     => 'active',
                'ai_model'   => 'gemini',
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( false === $result ) {
            return new WP_Error(
                'chat_create_failed',
                __( 'Failed to create new chat.', 'creator-core' ),
                [ 'status' => 500 ]
            );
        }

        $chat_id = $wpdb->insert_id;

        return new WP_REST_Response(
            [
                'success' => true,
                'chat_id' => $chat_id,
            ],
            201
        );
    }

    /**
     * Get or create a chat session
     *
     * @param int|null $chat_id Existing chat ID or null to create new.
     * @return int The chat ID.
     */
    private function get_or_create_chat( ?int $chat_id ): int {
        global $wpdb;

        $user_id = get_current_user_id();
        $table   = $wpdb->prefix . 'creator_chats';

        // If chat_id provided, verify it exists and belongs to user
        if ( $chat_id ) {
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE id = %d AND user_id = %d",
                    $chat_id,
                    $user_id
                )
            );

            if ( $exists ) {
                // Update timestamp
                $wpdb->update(
                    $table,
                    [ 'updated_at' => current_time( 'mysql' ) ],
                    [ 'id' => $chat_id ],
                    [ '%s' ],
                    [ '%d' ]
                );

                return (int) $exists;
            }
        }

        // Create new chat
        $wpdb->insert(
            $table,
            [
                'user_id'    => $user_id,
                'title'      => __( 'New Chat', 'creator-core' ),
                'status'     => 'active',
                'ai_model'   => 'gemini',
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s' ]
        );

        return $wpdb->insert_id;
    }

    /**
     * Save a message to the database
     *
     * @param int    $chat_id The chat ID.
     * @param string $role    The message role (user/assistant).
     * @param string $content The message content.
     * @return int The message ID.
     */
    private function save_message( int $chat_id, string $role, string $content ): int {
        global $wpdb;

        $table = $wpdb->prefix . 'creator_messages';

        $wpdb->insert(
            $table,
            [
                'chat_id'      => $chat_id,
                'user_id'      => get_current_user_id(),
                'role'         => $role,
                'content'      => $content,
                'message_type' => 'text',
                'created_at'   => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s' ]
        );

        // Update chat title if this is the first user message
        if ( 'user' === $role ) {
            $this->maybe_update_chat_title( $chat_id, $content );
        }

        return $wpdb->insert_id;
    }

    /**
     * Update chat title based on first message
     *
     * @param int    $chat_id The chat ID.
     * @param string $content The message content.
     * @return void
     */
    private function maybe_update_chat_title( int $chat_id, string $content ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'creator_chats';

        // Check if title is still default
        $current_title = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT title FROM {$table} WHERE id = %d",
                $chat_id
            )
        );

        if ( __( 'New Chat', 'creator-core' ) === $current_title ) {
            // Generate title from first message (first 50 chars)
            $title = wp_trim_words( $content, 8, '...' );
            $title = substr( $title, 0, 50 );

            $wpdb->update(
                $table,
                [ 'title' => $title ],
                [ 'id' => $chat_id ],
                [ '%s' ],
                [ '%d' ]
            );
        }
    }
}
