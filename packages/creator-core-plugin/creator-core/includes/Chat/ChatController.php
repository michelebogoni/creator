<?php
/**
 * Chat Controller
 *
 * REST endpoint POST /creator/v1/chat per gestire i messaggi.
 * Implementa il loop multi-step per task complessi seguendo le specifiche:
 * Discovery -> Strategy -> Implementation -> Verification
 *
 * @package CreatorCore
 */

namespace CreatorCore\Chat;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Context\ContextLoader;
use CreatorCore\Proxy\ProxyClient;
use CreatorCore\Response\ResponseHandler;
use CreatorCore\Conversation\ConversationManager;
use CreatorCore\Debug\DebugLogger;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class ChatController
 *
 * Handles REST API endpoints for chat functionality with multi-step support
 */
class ChatController {

    /**
     * REST namespace
     *
     * @var string
     */
    private string $namespace = 'creator/v1';

    /**
     * Maximum loop iterations to prevent infinite loops
     *
     * @var int
     */
    private const MAX_LOOP_ITERATIONS = 20;

    /**
     * Maximum retry attempts for failed executions
     *
     * @var int
     */
    private const MAX_RETRY_ATTEMPTS = 3;

    /**
     * Register REST routes
     *
     * @return void
     */
    public function register_routes(): void {
        // POST /creator/v1/chat - Send a message (main endpoint).
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
                        'validate_callback' => function ( $value ) {
                            return ! empty( trim( $value ) );
                        },
                    ],
                    'chat_id' => [
                        'required'          => false,
                        'type'              => 'integer',
                        'sanitize_callback' => 'absint',
                    ],
                    'confirm_plan' => [
                        'required'          => false,
                        'type'              => 'boolean',
                        'default'           => false,
                    ],
                ],
            ]
        );

        // GET /creator/v1/chat/history - Get chat history.
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

        // POST /creator/v1/chat/new - Create new chat.
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
     * Handle incoming chat message with multi-step loop support
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error
     */
    public function handle_chat_message( WP_REST_Request $request ) {
        $message      = $request->get_param( 'message' );
        $chat_id      = $request->get_param( 'chat_id' );
        $confirm_plan = $request->get_param( 'confirm_plan' );

        // Validate license.
        $site_token = get_option( 'creator_site_token', '' );
        if ( empty( $site_token ) ) {
            return new WP_Error(
                'license_required',
                __( 'Please configure your license key in settings.', 'creator-core' ),
                [ 'status' => 403 ]
            );
        }

        try {
            // Get or create chat.
            $chat_id = $this->get_or_create_chat( $chat_id );

            // Save user message.
            $this->save_message( $chat_id, 'user', $message );

            // Initialize components.
            $context_loader   = new ContextLoader();
            $proxy_client     = new ProxyClient();
            $response_handler = new ResponseHandler();
            $debug_logger     = new DebugLogger();

            // Start debug session.
            $debug_logger->start_session( $message, $chat_id );

            // Gather WordPress context.
            $context = $context_loader->get_context();

            // Log the context.
            $debug_logger->log_context( $context );

            // Get conversation history.
            $conversation_history = $this->get_conversation_history_for_ai( $chat_id );

            // If confirming a plan, modify the message.
            if ( $confirm_plan ) {
                $message = 'Procedi con il piano.';
            }

            // Execute the multi-step loop.
            $final_response = $this->execute_loop(
                $message,
                $context,
                $conversation_history,
                $proxy_client,
                $response_handler,
                $chat_id,
                $debug_logger
            );

            // Save assistant response.
            $this->save_message( $chat_id, 'assistant', $final_response['message'] ?? '' );

            return new WP_REST_Response(
                [
                    'success'  => true,
                    'chat_id'  => $chat_id,
                    'response' => $final_response,
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
     * Execute the multi-step AI loop
     *
     * Continues automatically when response has continue_automatically = true.
     * Includes retry logic for failed executions (up to MAX_RETRY_ATTEMPTS).
     *
     * @param string          $message              Initial user message.
     * @param array           $context              WordPress context.
     * @param array           $conversation_history Previous messages.
     * @param ProxyClient     $proxy_client         Proxy client instance.
     * @param ResponseHandler $response_handler     Response handler instance.
     * @param int             $chat_id              Chat ID.
     * @param DebugLogger     $debug_logger         Debug logger instance.
     * @return array Final response after loop completes.
     */
    private function execute_loop(
        string $message,
        array $context,
        array $conversation_history,
        ProxyClient $proxy_client,
        ResponseHandler $response_handler,
        int $chat_id,
        DebugLogger $debug_logger
    ): array {
        $iteration        = 0;
        $documentation    = null;
        $current_message  = $message;
        $all_steps        = [];
        $last_result      = null;
        $retry_count      = 0;

        while ( $iteration < self::MAX_LOOP_ITERATIONS ) {
            $iteration++;

            // Build context with last execution result.
            $loop_context = $context;
            if ( $last_result !== null ) {
                $loop_context['last_result'] = $last_result;
            }

            // Log AI request.
            $debug_logger->log_ai_request(
                $current_message,
                $loop_context,
                $conversation_history,
                $documentation,
                $iteration
            );

            // Call the AI.
            $proxy_response = $proxy_client->send_message(
                $current_message,
                $loop_context,
                $conversation_history,
                $documentation
            );

            // Log AI response.
            $debug_logger->log_ai_response( $proxy_response, $iteration );

            // Handle proxy errors.
            if ( is_wp_error( $proxy_response ) ) {
                $error_response = [
                    'type'    => 'error',
                    'step'    => 'implementation',
                    'status'  => __( 'Error', 'creator-core' ),
                    'message' => $proxy_response->get_error_message(),
                    'data'    => [],
                    'steps'   => $all_steps,
                ];
                $debug_logger->end_session( $error_response, $iteration );
                return $error_response;
            }

            // Process the response.
            $processed = $response_handler->handle( $proxy_response, $loop_context );

            // Log processed response.
            $debug_logger->log_processed_response( $processed, $iteration );

            // Track all steps for debugging.
            $all_steps[] = [
                'iteration'   => $iteration,
                'type'        => $processed['type'],
                'step'        => $processed['step'] ?? '',
                'status'      => $processed['status'] ?? '',
                'retry_count' => $retry_count,
            ];

            // Handle request_docs type - fetch documentation and continue.
            if ( 'request_docs' === $processed['type'] ) {
                $documentation = $processed['documentation'] ?? [];

                // Log documentation fetch.
                $debug_logger->log_documentation(
                    $processed['data']['plugins_needed'] ?? [],
                    $documentation,
                    $iteration
                );

                // Build message that includes the original task reminder.
                $current_message = wp_json_encode( [
                    'type'          => 'documentation_provided',
                    'docs'          => array_keys( $documentation ),
                    'original_task' => $message, // Include original user request.
                    'instruction'   => 'Documentation has been loaded. Now proceed with the original task. Generate the code to execute.',
                ] );
                $conversation_history[] = [
                    'role'    => 'assistant',
                    'content' => wp_json_encode( $processed ),
                ];
                $conversation_history[] = [
                    'role'    => 'user',
                    'content' => $current_message,
                ];
                continue;
            }

            // Store execution/verification results for next iteration.
            if ( isset( $processed['execution_result'] ) ) {
                $last_result = $processed['execution_result'];

                // Log the execution.
                $debug_logger->log_execution(
                    $processed['data']['code'] ?? '',
                    $last_result,
                    $iteration
                );

                // Check if execution failed and we should retry.
                if ( ! empty( $last_result ) && empty( $last_result['success'] ) ) {
                    if ( $this->should_retry_execution( $last_result, $retry_count ) ) {
                        $retry_count++;

                        // Log the retry.
                        $debug_logger->log_retry( $last_result, $retry_count, $iteration );

                        // Build retry message with error details.
                        $retry_message = wp_json_encode( [
                            'type'        => 'execution_failed',
                            'error'       => $last_result['error'] ?? 'Unknown error',
                            'output'      => $last_result['output'] ?? '',
                            'retry_count' => $retry_count,
                            'max_retries' => self::MAX_RETRY_ATTEMPTS,
                            'instruction' => 'The previous code execution failed. Please analyze the error and try a different approach.',
                        ] );

                        $current_message = $retry_message;

                        // Add to conversation history.
                        $conversation_history[] = [
                            'role'    => 'assistant',
                            'content' => wp_json_encode( $processed ),
                        ];
                        $conversation_history[] = [
                            'role'    => 'user',
                            'content' => $current_message,
                        ];
                        continue;
                    }
                } else {
                    // Reset retry count on successful execution.
                    $retry_count = 0;
                }
            } elseif ( isset( $processed['verification_result'] ) ) {
                $last_result = $processed['verification_result'];

                // Log verification as execution.
                $debug_logger->log_execution(
                    $processed['data']['code'] ?? '',
                    $last_result,
                    $iteration
                );
            }

            // Check if we should continue automatically.
            if ( ! empty( $processed['continue_automatically'] ) ) {
                // Build continuation message with result.
                $continuation = [
                    'type'   => 'execution_result',
                    'result' => $last_result,
                ];
                $current_message = wp_json_encode( $continuation );

                // Add to conversation history.
                $conversation_history[] = [
                    'role'    => 'assistant',
                    'content' => wp_json_encode( $processed ),
                ];
                $conversation_history[] = [
                    'role'    => 'user',
                    'content' => $current_message,
                ];
                continue;
            }

            // Loop complete - return the final response.
            $processed['steps'] = $all_steps;
            $debug_logger->end_session( $processed, $iteration );
            return $processed;
        }

        // Max iterations reached.
        $max_iter_response = [
            'type'    => 'error',
            'step'    => 'implementation',
            'status'  => __( 'Error', 'creator-core' ),
            'message' => __( 'Maximum loop iterations reached. Task may be too complex.', 'creator-core' ),
            'data'    => [],
            'steps'   => $all_steps,
        ];
        $debug_logger->end_session( $max_iter_response, $iteration );
        return $max_iter_response;
    }

    /**
     * Determine if a failed execution should be retried
     *
     * @param array $execution_result The execution result.
     * @param int   $current_retry    Current retry count.
     * @return bool Whether to retry.
     */
    private function should_retry_execution( array $execution_result, int $current_retry ): bool {
        // Check max retries.
        if ( $current_retry >= self::MAX_RETRY_ATTEMPTS ) {
            return false;
        }

        $error = $execution_result['error'] ?? '';

        // Non-retryable security errors.
        $non_retryable = [
            'Forbidden function',
            'Base64 decode is not allowed',
            'Backtick execution',
            'superglobal access',
        ];

        foreach ( $non_retryable as $pattern ) {
            if ( stripos( $error, $pattern ) !== false ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get conversation history formatted for the AI
     *
     * @param int $chat_id The chat ID.
     * @return array Messages formatted for AI.
     */
    private function get_conversation_history_for_ai( int $chat_id ): array {
        global $wpdb;

        $table    = $wpdb->prefix . 'creator_messages';
        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT role, content FROM {$table}
                 WHERE chat_id = %d
                 ORDER BY created_at ASC
                 LIMIT 20",
                $chat_id
            ),
            ARRAY_A
        );

        if ( ! $messages ) {
            return [];
        }

        // Format for AI - map 'assistant' to expected format.
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

        // If chat_id is provided, get messages for that chat.
        if ( $chat_id ) {
            // Verify chat belongs to user.
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

        // Otherwise, get list of user's chats.
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
                'ai_model'   => get_option( 'creator_default_model', 'gemini' ),
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

        // If chat_id provided, verify it exists and belongs to user.
        if ( $chat_id ) {
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE id = %d AND user_id = %d",
                    $chat_id,
                    $user_id
                )
            );

            if ( $exists ) {
                // Update timestamp.
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

        // Create new chat.
        $wpdb->insert(
            $table,
            [
                'user_id'    => $user_id,
                'title'      => __( 'New Chat', 'creator-core' ),
                'status'     => 'active',
                'ai_model'   => get_option( 'creator_default_model', 'gemini' ),
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

        // Update chat title if this is the first user message.
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

        // Check if title is still default.
        $current_title = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT title FROM {$table} WHERE id = %d",
                $chat_id
            )
        );

        if ( __( 'New Chat', 'creator-core' ) === $current_title ) {
            // Generate title from first message (first 50 chars).
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
