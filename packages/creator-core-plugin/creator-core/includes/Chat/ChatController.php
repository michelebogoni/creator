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
    private const MAX_LOOP_ITERATIONS = 100;

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

        // GET /creator/v1/chat/stream - SSE endpoint for real-time progress streaming.
        register_rest_route(
            $this->namespace,
            '/chat/stream',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_chat_stream' ],
                'permission_callback' => [ $this, 'check_permission' ],
                'args'                => [
                    'message' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field',
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
        $files        = $request->get_param( 'files' ) ?? [];

        // Validate and sanitize files.
        $files = $this->validate_files( $files );

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

            // Save user message (with file info if present).
            $message_to_save = $message;
            if ( ! empty( $files ) ) {
                $file_names      = array_column( $files, 'name' );
                $message_to_save = $message . "\n\n[Allegati: " . implode( ', ', $file_names ) . ']';
            }
            $this->save_message( $chat_id, 'user', $message_to_save );

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

            // Execute the multi-step loop (pass files to be forwarded to AI).
            $final_response = $this->execute_loop(
                $message,
                $context,
                $conversation_history,
                $proxy_client,
                $response_handler,
                $chat_id,
                $debug_logger,
                $files
            );

            // Save assistant response - save FULL JSON for AI context, not just message.
            // This ensures the AI sees the plan details when user says "Procedi".
            $this->save_message( $chat_id, 'assistant', wp_json_encode( $final_response ) );

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
     * Handle SSE streaming for real-time progress updates
     *
     * This endpoint streams progress events as the AI processes the request,
     * allowing the frontend to show real-time updates to the user.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return void Outputs SSE stream directly.
     */
    public function handle_chat_stream( WP_REST_Request $request ) {
        $message      = $request->get_param( 'message' );
        $chat_id      = $request->get_param( 'chat_id' );
        $confirm_plan = $request->get_param( 'confirm_plan' );

        // Validate license.
        $site_token = get_option( 'creator_site_token', '' );
        if ( empty( $site_token ) ) {
            $this->send_sse_error( __( 'Please configure your license key in settings.', 'creator-core' ) );
            return;
        }

        // Set SSE headers.
        $this->set_sse_headers();

        try {
            // Get or create chat.
            $chat_id = $this->get_or_create_chat( $chat_id );

            // Send initial connection event.
            $this->send_sse_event( 'connected', [
                'chat_id' => $chat_id,
                'message' => __( 'Connected - starting processing...', 'creator-core' ),
            ] );

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

            // Execute the streaming loop.
            $final_response = $this->execute_loop_streaming(
                $message,
                $context,
                $conversation_history,
                $proxy_client,
                $response_handler,
                $chat_id,
                $debug_logger
            );

            // Save assistant response.
            $this->save_message( $chat_id, 'assistant', wp_json_encode( $final_response ) );

            // Send complete event with final response.
            $this->send_sse_event( 'complete', [
                'success'  => true,
                'chat_id'  => $chat_id,
                'response' => $final_response,
            ] );

        } catch ( \Exception $e ) {
            $this->send_sse_error( $e->getMessage() );
        }

        exit; // End the SSE stream.
    }

    /**
     * Set SSE headers for streaming response
     *
     * @return void
     */
    private function set_sse_headers(): void {
        // Disable output buffering.
        while ( ob_get_level() ) {
            ob_end_clean();
        }

        // Set SSE headers.
        header( 'Content-Type: text/event-stream' );
        header( 'Cache-Control: no-cache' );
        header( 'Connection: keep-alive' );
        header( 'X-Accel-Buffering: no' ); // Disable nginx buffering.

        // Flush headers.
        if ( function_exists( 'flush' ) ) {
            flush();
        }
    }

    /**
     * Send an SSE event
     *
     * @param string $event Event name.
     * @param array  $data  Event data.
     * @return void
     */
    private function send_sse_event( string $event, array $data ): void {
        echo "event: {$event}\n";
        echo 'data: ' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE ) . "\n\n";

        if ( function_exists( 'flush' ) ) {
            flush();
        }

        // Also try ob_flush if available.
        if ( function_exists( 'ob_flush' ) && ob_get_level() > 0 ) {
            @ob_flush();
        }
    }

    /**
     * Send an SSE error event
     *
     * @param string $message Error message.
     * @return void
     */
    private function send_sse_error( string $message ): void {
        $this->set_sse_headers();
        $this->send_sse_event( 'error', [
            'success' => false,
            'message' => $message,
        ] );
        exit;
    }

    /**
     * Execute the multi-step AI loop with SSE streaming
     *
     * Similar to execute_loop but sends SSE events for each step.
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
    private function execute_loop_streaming(
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
        $error_memory     = [];

        while ( $iteration < self::MAX_LOOP_ITERATIONS ) {
            $iteration++;

            // Build context with last execution result.
            $loop_context = $context;
            if ( $last_result !== null ) {
                $loop_context['last_result'] = $last_result;

                // Flatten the result into top-level context for easier access.
                // This allows AI-generated code to use $context['page_id'] instead of
                // $context['last_result']['result']['page_id'].
                if ( ! empty( $last_result['result'] ) && is_array( $last_result['result'] ) ) {
                    foreach ( $last_result['result'] as $key => $value ) {
                        // Don't overwrite existing context keys (site_info, accumulated, etc.).
                        if ( ! isset( $loop_context[ $key ] ) ) {
                            $loop_context[ $key ] = $value;
                        }
                    }
                }
            }

            // Flatten accumulated context into top-level for easier access.
            // This allows $context['page_id'] to work if it was set in a checkpoint.
            if ( ! empty( $loop_context['accumulated'] ) && is_array( $loop_context['accumulated'] ) ) {
                foreach ( $loop_context['accumulated'] as $key => $value ) {
                    // Don't overwrite existing context keys.
                    if ( ! isset( $loop_context[ $key ] ) ) {
                        $loop_context[ $key ] = $value;
                    }
                }
            }

            // Send progress event - starting iteration.
            $this->send_sse_event( 'progress', [
                'iteration'       => $iteration,
                'phase'           => 'calling_ai',
                'display_message' => sprintf(
                    __( 'Iteration %d: Communicating with AI...', 'creator-core' ),
                    $iteration
                ),
            ] );

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

            // Get display message for this step.
            $display_message = $this->get_step_display_message( $processed, $iteration );

            // Track step.
            $step_data = [
                'iteration'       => $iteration,
                'type'            => $processed['type'],
                'step'            => $processed['step'] ?? '',
                'status'          => $processed['status'] ?? '',
                'message'         => $processed['message'] ?? '',
                'retry_count'     => $retry_count,
                'timestamp'       => current_time( 'mysql' ),
                'display_message' => $display_message,
            ];
            $all_steps[] = $step_data;

            // Send progress event for this step.
            // Include both display_message (short) and detailed_message (full explanation).
            $this->send_sse_event( 'progress', [
                'iteration'        => $iteration,
                'type'             => $processed['type'],
                'phase'            => $this->get_phase_from_type( $processed['type'] ),
                'display_message'  => $display_message,
                'detailed_message' => $processed['message'] ?? '',
                'step_data'        => $step_data,
            ] );

            // Handle request_docs type - fetch documentation and continue.
            if ( 'request_docs' === $processed['type'] ) {
                $documentation = $processed['documentation'] ?? [];

                $debug_logger->log_documentation(
                    $processed['data']['plugins_needed'] ?? [],
                    $documentation,
                    $iteration
                );

                $task            = $processed['data']['task'] ?? $processed['data']['reason'] ?? '';
                $current_message = wp_json_encode( [
                    'type'        => 'documentation_provided',
                    'docs'        => array_keys( $documentation ),
                    'task'        => $task,
                    'instruction' => 'Documentation has been loaded. Now proceed with the task. Generate the PHP code to execute.',
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

            // Handle roadmap type - iterate through all steps.
            if ( 'roadmap' === $processed['type'] && ! empty( $processed['data']['roadmap'] ) ) {
                $roadmap      = $processed['data']['roadmap'];
                $total_steps  = count( $roadmap );
                $roadmap_json = wp_json_encode( $roadmap );

                $conversation_history[] = [
                    'role'    => 'assistant',
                    'content' => wp_json_encode( $processed ),
                ];

                $current_message = wp_json_encode( [
                    'type'        => 'execute_roadmap',
                    'instruction' => "Now execute step 1 of {$total_steps}. Generate the PHP code for this step only.",
                    'step_index'  => 1,
                    'total_steps' => $total_steps,
                    'current_step' => $roadmap[0] ?? [],
                    'full_roadmap' => $roadmap_json,
                ] );
                $conversation_history[] = [
                    'role'    => 'user',
                    'content' => $current_message,
                ];
                continue;
            }

            // Handle checkpoint - continue with next step.
            if ( 'checkpoint' === $processed['type'] ) {
                $completed_step = $processed['data']['completed_step'] ?? 0;
                $total_steps    = $processed['data']['total_steps'] ?? 0;
                $next_step      = $processed['data']['next_step'] ?? null;

                $conversation_history[] = [
                    'role'    => 'assistant',
                    'content' => wp_json_encode( $processed ),
                ];

                if ( $next_step && $completed_step < $total_steps ) {
                    $next_step_data = $processed['data']['next_step_data'] ?? [];
                    $current_message = wp_json_encode( [
                        'type'        => 'execute_roadmap',
                        'instruction' => "Step {$completed_step} completed. Now execute step {$next_step} of {$total_steps}.",
                        'step_index'  => $next_step,
                        'total_steps' => $total_steps,
                        'current_step' => $next_step_data,
                    ] );
                    $conversation_history[] = [
                        'role'    => 'user',
                        'content' => $current_message,
                    ];
                    continue;
                }
            }

            // Handle execute_step type.
            if ( 'execute_step' === $processed['type'] ) {
                $execution_result = $processed['data']['result'] ?? null;
                $step_index       = $processed['data']['step_index'] ?? 0;
                $total_steps      = $processed['data']['total_steps'] ?? 0;

                $conversation_history[] = [
                    'role'    => 'assistant',
                    'content' => wp_json_encode( $processed ),
                ];

                if ( isset( $processed['data']['execution_failed'] ) && $processed['data']['execution_failed'] ) {
                    if ( $this->should_retry_execution( $processed, $retry_count ) ) {
                        $retry_count++;
                        $error_memory[] = [
                            'step'    => $step_index,
                            'error'   => $processed['data']['error'] ?? 'Unknown error',
                            'code'    => $processed['data']['failed_code'] ?? '',
                            'attempt' => $retry_count,
                        ];

                        $current_message = wp_json_encode( [
                            'type'         => 'retry_step',
                            'instruction'  => "Step {$step_index} failed. Please analyze the error and generate corrected code.",
                            'step_index'   => $step_index,
                            'total_steps'  => $total_steps,
                            'error'        => $processed['data']['error'] ?? '',
                            'failed_code'  => $processed['data']['failed_code'] ?? '',
                            'retry_count'  => $retry_count,
                            'error_memory' => $error_memory,
                        ] );
                        $conversation_history[] = [
                            'role'    => 'user',
                            'content' => $current_message,
                        ];
                        continue;
                    }
                }

                $retry_count  = 0;
                $error_memory = [];
                $last_result  = $execution_result;

                if ( $step_index < $total_steps ) {
                    $current_message = wp_json_encode( [
                        'type'            => 'step_completed',
                        'instruction'     => "Step {$step_index} completed successfully. Create a checkpoint and continue with step " . ( $step_index + 1 ) . ".",
                        'completed_step'  => $step_index,
                        'total_steps'     => $total_steps,
                        'execution_result' => $execution_result,
                    ] );
                    $conversation_history[] = [
                        'role'    => 'user',
                        'content' => $current_message,
                    ];
                    continue;
                }
            }

            // Handle execute type (simple execution).
            if ( 'execute' === $processed['type'] ) {
                $execution_result = $processed['data']['result'] ?? null;

                $conversation_history[] = [
                    'role'    => 'assistant',
                    'content' => wp_json_encode( $processed ),
                ];

                if ( isset( $processed['data']['execution_failed'] ) && $processed['data']['execution_failed'] ) {
                    if ( $this->should_retry_execution( $processed, $retry_count ) ) {
                        $retry_count++;
                        $error_memory[] = [
                            'error'   => $processed['data']['error'] ?? 'Unknown error',
                            'code'    => $processed['data']['failed_code'] ?? '',
                            'attempt' => $retry_count,
                        ];

                        $current_message = wp_json_encode( [
                            'type'         => 'retry_execution',
                            'instruction'  => 'Execution failed. Please analyze the error and generate corrected code.',
                            'error'        => $processed['data']['error'] ?? '',
                            'failed_code'  => $processed['data']['failed_code'] ?? '',
                            'retry_count'  => $retry_count,
                            'error_memory' => $error_memory,
                        ] );
                        $conversation_history[] = [
                            'role'    => 'user',
                            'content' => $current_message,
                        ];
                        continue;
                    }
                }

                $retry_count  = 0;
                $error_memory = [];
                $last_result  = $execution_result;

                if ( ! empty( $processed['continue_automatically'] ) ) {
                    $current_message = wp_json_encode( [
                        'type'             => 'execution_completed',
                        'instruction'      => 'Code executed. Verify the result and respond to the user.',
                        'execution_result' => $execution_result,
                    ] );
                    $conversation_history[] = [
                        'role'    => 'user',
                        'content' => $current_message,
                    ];
                    continue;
                }
            }

            // Handle verify type.
            if ( 'verify' === $processed['type'] ) {
                $conversation_history[] = [
                    'role'    => 'assistant',
                    'content' => wp_json_encode( $processed ),
                ];

                if ( ! empty( $processed['continue_automatically'] ) ) {
                    $verification_passed = $processed['data']['passed'] ?? true;
                    if ( $verification_passed ) {
                        $current_message = wp_json_encode( [
                            'type'        => 'verification_passed',
                            'instruction' => 'Verification passed. Provide final response to user.',
                        ] );
                    } else {
                        $current_message = wp_json_encode( [
                            'type'        => 'verification_failed',
                            'instruction' => 'Verification failed. Analyze and fix the issue.',
                            'issues'      => $processed['data']['issues'] ?? [],
                        ] );
                    }
                    $conversation_history[] = [
                        'role'    => 'user',
                        'content' => $current_message,
                    ];
                    continue;
                }
            }

            // Handle compress_history type.
            if ( 'compress_history' === $processed['type'] ) {
                $conversation_history[] = [
                    'role'    => 'assistant',
                    'content' => wp_json_encode( $processed ),
                ];

                if ( ! empty( $processed['continue_automatically'] ) ) {
                    $current_message = wp_json_encode( [
                        'type'        => 'history_compressed',
                        'instruction' => 'History compressed. Continue with the task.',
                    ] );
                    $conversation_history[] = [
                        'role'    => 'user',
                        'content' => $current_message,
                    ];
                    continue;
                }
            }

            // Terminal states or no continue flag - end the loop.
            if ( in_array( $processed['type'], [ 'complete', 'error', 'question', 'plan' ], true ) ) {
                $processed['steps'] = $all_steps;
                $debug_logger->end_session( $processed, $iteration );
                return $processed;
            }

            // No continue flag - end loop.
            if ( empty( $processed['continue_automatically'] ) ) {
                $processed['steps'] = $all_steps;
                $debug_logger->end_session( $processed, $iteration );
                return $processed;
            }
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
     * Get phase name from step type for display
     *
     * @param string $type Step type.
     * @return string Phase name.
     */
    private function get_phase_from_type( string $type ): string {
        $phase_map = [
            'request_docs'     => 'discovery',
            'roadmap'          => 'planning',
            'plan'             => 'planning',
            'execute_step'     => 'execution',
            'execute'          => 'execution',
            'checkpoint'       => 'execution',
            'verify'           => 'verification',
            'wp_cli'           => 'execution',
            'compress_history' => 'analysis',
            'question'         => 'analysis',
            'complete'         => 'complete',
            'error'            => 'error',
        ];
        return $phase_map[ $type ] ?? 'processing';
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
     * @param array           $files                Optional file attachments for the first message.
     * @return array Final response after loop completes.
     */
    private function execute_loop(
        string $message,
        array $context,
        array $conversation_history,
        ProxyClient $proxy_client,
        ResponseHandler $response_handler,
        int $chat_id,
        DebugLogger $debug_logger,
        array $files = []
    ): array {
        $iteration        = 0;
        $documentation    = null;
        $current_message  = $message;
        $all_steps        = [];
        $last_result      = null;
        $retry_count      = 0;
        $error_memory     = []; // Accumulates errors during retries, cleared on success.
        $pending_files    = $files; // Files to send with first AI call only.

        while ( $iteration < self::MAX_LOOP_ITERATIONS ) {
            $iteration++;

            // Build context with last execution result.
            $loop_context = $context;
            if ( $last_result !== null ) {
                $loop_context['last_result'] = $last_result;

                // Flatten the result into top-level context for easier access.
                // This allows AI-generated code to use $context['page_id'] instead of
                // $context['last_result']['result']['page_id'].
                if ( ! empty( $last_result['result'] ) && is_array( $last_result['result'] ) ) {
                    foreach ( $last_result['result'] as $key => $value ) {
                        // Don't overwrite existing context keys (site_info, accumulated, etc.).
                        if ( ! isset( $loop_context[ $key ] ) ) {
                            $loop_context[ $key ] = $value;
                        }
                    }
                }
            }

            // Flatten accumulated context into top-level for easier access.
            // This allows $context['page_id'] to work if it was set in a checkpoint.
            if ( ! empty( $loop_context['accumulated'] ) && is_array( $loop_context['accumulated'] ) ) {
                foreach ( $loop_context['accumulated'] as $key => $value ) {
                    // Don't overwrite existing context keys.
                    if ( ! isset( $loop_context[ $key ] ) ) {
                        $loop_context[ $key ] = $value;
                    }
                }
            }

            // Log AI request.
            $debug_logger->log_ai_request(
                $current_message,
                $loop_context,
                $conversation_history,
                $documentation,
                $iteration
            );

            // Call the AI (pass files only on first iteration, then clear them).
            $proxy_response = $proxy_client->send_message(
                $current_message,
                $loop_context,
                $conversation_history,
                $documentation,
                $pending_files
            );

            // Clear files after first call - they should only be sent once.
            $pending_files = [];

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

            // Track all steps for user display (thinking panel).
            $all_steps[] = [
                'iteration'       => $iteration,
                'type'            => $processed['type'],
                'step'            => $processed['step'] ?? '',
                'status'          => $processed['status'] ?? '',
                'message'         => $processed['message'] ?? '',
                'retry_count'     => $retry_count,
                'timestamp'       => current_time( 'mysql' ),
                'display_message' => $this->get_step_display_message( $processed, $iteration ),
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

                // Extract the task description from AI's request_docs response.
                // This is the AI's interpretation of what the user wants, not the raw message.
                $task = $processed['data']['task'] ?? $processed['data']['reason'] ?? '';

                // Build message that includes the task to continue.
                $current_message = wp_json_encode( [
                    'type'        => 'documentation_provided',
                    'docs'        => array_keys( $documentation ),
                    'task'        => $task, // AI's understood task from conversation.
                    'instruction' => 'Documentation has been loaded. Now proceed with the task. Generate the PHP code to execute.',
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

            // Handle roadmap type - return to user for confirmation.
            if ( 'roadmap' === $processed['type'] ) {
                $processed['steps'] = $all_steps;
                $debug_logger->end_session( $processed, $iteration );
                return $processed;
            }

            // Handle checkpoint type - pass accumulated context to next iteration.
            if ( 'checkpoint' === $processed['type'] ) {
                $accumulated_context = $processed['data']['accumulated_context'] ?? [];

                // Merge accumulated context into loop context for next iteration.
                if ( ! empty( $accumulated_context ) ) {
                    $context['accumulated'] = array_merge(
                        $context['accumulated'] ?? [],
                        $accumulated_context
                    );
                }

                // Build continuation message with checkpoint data.
                $current_message = wp_json_encode( [
                    'type'                => 'checkpoint_confirmed',
                    'completed_step'      => $processed['data']['completed_step'] ?? 0,
                    'total_steps'         => $processed['data']['total_steps'] ?? 0,
                    'next_step'           => $processed['data']['next_step'] ?? null,
                    'accumulated_context' => $context['accumulated'] ?? [],
                    'instruction'         => 'Checkpoint confirmed. Continue with the next step.',
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

            // Handle compress_history type - compress old messages and continue.
            if ( 'compress_history' === $processed['type'] ) {
                $conversation_history = $this->compress_conversation_history(
                    $conversation_history,
                    $processed['data']['summary'] ?? '',
                    $processed['data']['key_facts'] ?? [],
                    $processed['data']['preserve_last_messages'] ?? 4
                );

                // Continue with a confirmation.
                $current_message = wp_json_encode( [
                    'type'        => 'history_compressed',
                    'instruction' => 'Conversation history has been compressed. Continue with the current task.',
                ] );

                continue;
            }

            // Handle wp_cli type - WP-CLI command execution.
            if ( 'wp_cli' === $processed['type'] ) {
                if ( isset( $processed['execution_result'] ) ) {
                    $last_result = $processed['execution_result'];

                    // Log the WP-CLI execution.
                    $debug_logger->log_execution(
                        'WP-CLI: ' . ( $processed['data']['command'] ?? 'unknown command' ),
                        $last_result,
                        $iteration
                    );

                    // Check if WP-CLI is not available - AI needs to use alternative.
                    if ( ! empty( $last_result['error'] ) && strpos( $last_result['error'], 'not available' ) !== false ) {
                        $current_message = wp_json_encode( [
                            'type'        => 'wp_cli_not_available',
                            'error'       => $last_result['error'],
                            'instruction' => 'WP-CLI is not available on this server. Please use an alternative method: either provide the code for the user to add manually via the plugin UI, or use PHP API if available.',
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

                    // Check if command failed and we should retry.
                    if ( ! empty( $last_result ) && empty( $last_result['success'] ) ) {
                        if ( $this->should_retry_execution( $last_result, $retry_count ) ) {
                            $retry_count++;

                            // Add error to memory for context in future retries.
                            $error_memory[] = [
                                'attempt'       => $retry_count,
                                'error'         => $last_result['error'] ?? 'Unknown error',
                                'command_tried' => $processed['data']['command'] ?? '',
                            ];

                            $debug_logger->log_retry( $last_result, $retry_count, $iteration );

                            $retry_message = wp_json_encode( [
                                'type'         => 'wp_cli_failed',
                                'command'      => $processed['data']['command'] ?? '',
                                'error'        => $last_result['error'] ?? 'Unknown error',
                                'output'       => $last_result['output'] ?? '',
                                'retry_count'  => $retry_count,
                                'max_retries'  => self::MAX_RETRY_ATTEMPTS,
                                'error_memory' => $error_memory, // All previous errors.
                                'instruction'  => 'The WP-CLI command failed. Review error_memory to see ALL previous failed attempts. DO NOT repeat the same commands. Try a DIFFERENT approach.',
                            ] );

                            $current_message = $retry_message;

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
                        // Reset retry count and clear error memory on successful execution.
                        $retry_count  = 0;
                        $error_memory = [];
                    }
                }

                // Build continuation message with WP-CLI result.
                $current_message = wp_json_encode( [
                    'type'        => 'wp_cli_result',
                    'command'     => $processed['data']['command'] ?? '',
                    'result'      => $last_result,
                    'instruction' => 'WP-CLI command executed. Continue with the task or report completion.',
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

            // Handle execute_step type - similar to execute but with step tracking.
            if ( 'execute_step' === $processed['type'] ) {
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

                            // Add error to memory for context in future retries.
                            $error_memory[] = [
                                'attempt'    => $retry_count,
                                'error'      => $last_result['error'] ?? 'Unknown error',
                                'code_tried' => substr( $processed['data']['code'] ?? '', 0, 500 ), // First 500 chars.
                            ];

                            // Log the retry.
                            $debug_logger->log_retry( $last_result, $retry_count, $iteration );

                            // Build retry message with error details, step context, and error history.
                            $retry_message = wp_json_encode( [
                                'type'         => 'step_execution_failed',
                                'step_index'   => $processed['data']['step_index'] ?? 0,
                                'step_title'   => $processed['data']['step_title'] ?? '',
                                'error'        => $last_result['error'] ?? 'Unknown error',
                                'output'       => $last_result['output'] ?? '',
                                'retry_count'  => $retry_count,
                                'max_retries'  => self::MAX_RETRY_ATTEMPTS,
                                'error_memory' => $error_memory, // All previous errors for this step.
                                'instruction'  => 'Step execution failed. Review the error_memory to see ALL previous failed attempts and their errors. DO NOT repeat the same approaches that already failed. Try a DIFFERENT approach for this step.',
                            ] );

                            $current_message = $retry_message;

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
                        // Reset retry count and clear error memory on successful execution.
                        $retry_count  = 0;
                        $error_memory = [];
                    }
                }

                // Build continuation message with step result.
                $current_message = wp_json_encode( [
                    'type'        => 'step_execution_result',
                    'step_index'  => $processed['data']['step_index'] ?? 0,
                    'total_steps' => $processed['data']['total_steps'] ?? 0,
                    'result'      => $last_result,
                    'instruction' => 'Step executed. Report checkpoint with accumulated context, then continue to next step.',
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

                        // Add error to memory for context in future retries.
                        $error_memory[] = [
                            'attempt'    => $retry_count,
                            'error'      => $last_result['error'] ?? 'Unknown error',
                            'code_tried' => substr( $processed['data']['code'] ?? '', 0, 500 ), // First 500 chars.
                        ];

                        // Log the retry.
                        $debug_logger->log_retry( $last_result, $retry_count, $iteration );

                        // Build retry message with error details and error history.
                        $retry_message = wp_json_encode( [
                            'type'         => 'execution_failed',
                            'error'        => $last_result['error'] ?? 'Unknown error',
                            'output'       => $last_result['output'] ?? '',
                            'retry_count'  => $retry_count,
                            'max_retries'  => self::MAX_RETRY_ATTEMPTS,
                            'error_memory' => $error_memory, // All previous errors.
                            'instruction'  => 'The previous code execution failed. Review error_memory to see ALL previous failed attempts. DO NOT repeat the same approaches. Try a DIFFERENT approach.',
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
                    // Reset retry count and clear error memory on successful execution.
                    $retry_count  = 0;
                    $error_memory = [];
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
     * Compress conversation history to reduce token usage
     *
     * Replaces old messages with a summary while preserving recent messages.
     *
     * @param array  $history                 The conversation history.
     * @param string $summary                 Summary of old conversation.
     * @param array  $key_facts               Key facts to preserve.
     * @param int    $preserve_last_messages  Number of recent messages to keep intact.
     * @return array Compressed conversation history.
     */
    private function compress_conversation_history(
        array $history,
        string $summary,
        array $key_facts,
        int $preserve_last_messages = 4
    ): array {
        $total_messages = count( $history );

        // If history is small enough, don't compress.
        if ( $total_messages <= $preserve_last_messages + 2 ) {
            return $history;
        }

        // Build compressed summary message.
        $compressed_content = "=== CONVERSATION SUMMARY ===\n";
        $compressed_content .= $summary . "\n\n";

        if ( ! empty( $key_facts ) ) {
            $compressed_content .= "KEY FACTS:\n";
            foreach ( $key_facts as $fact ) {
                $key   = $fact['key'] ?? '';
                $value = $fact['value'] ?? '';
                $desc  = $fact['description'] ?? '';
                $compressed_content .= "- {$key}: {$value}";
                if ( $desc ) {
                    $compressed_content .= " ({$desc})";
                }
                $compressed_content .= "\n";
            }
        }
        $compressed_content .= "=== END SUMMARY ===";

        // Create new history with summary at the beginning.
        $new_history = [
            [
                'role'    => 'system',
                'content' => $compressed_content,
            ],
        ];

        // Add the last N messages unchanged.
        $preserved = array_slice( $history, -$preserve_last_messages );
        $new_history = array_merge( $new_history, $preserved );

        return $new_history;
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

    /**
     * Get a user-friendly display message for a processing step
     *
     * Converts technical step data into human-readable messages for the thinking panel.
     *
     * @param array $processed The processed response.
     * @param int   $iteration The iteration number.
     * @return string User-friendly message.
     */
    private function get_step_display_message( array $processed, int $iteration ): string {
        $type   = $processed['type'] ?? 'unknown';
        $status = $processed['status'] ?? '';
        $data   = $processed['data'] ?? [];

        switch ( $type ) {
            case 'request_docs':
                $plugins = $data['plugins_needed'] ?? [];
                if ( ! empty( $plugins ) ) {
                    return sprintf(
                        /* translators: %s: plugin names */
                        __( 'Researching documentation for: %s', 'creator-core' ),
                        implode( ', ', $plugins )
                    );
                }
                return __( 'Researching plugin documentation...', 'creator-core' );

            case 'roadmap':
                $total = $data['total_steps'] ?? 0;
                if ( $total > 0 ) {
                    return sprintf(
                        /* translators: %d: number of steps */
                        __( 'Created roadmap with %d steps', 'creator-core' ),
                        $total
                    );
                }
                return __( 'Creating execution roadmap...', 'creator-core' );

            case 'execute_step':
                $step_index = $data['step_index'] ?? 0;
                $total      = $data['total_steps'] ?? 0;
                $step_title = $data['step_title'] ?? '';
                if ( $step_index && $total ) {
                    if ( $step_title ) {
                        return sprintf(
                            /* translators: 1: step number, 2: total steps, 3: step title */
                            __( 'Executing step %1$d/%2$d: %3$s', 'creator-core' ),
                            $step_index,
                            $total,
                            $step_title
                        );
                    }
                    return sprintf(
                        /* translators: 1: step number, 2: total steps */
                        __( 'Executing step %1$d of %2$d', 'creator-core' ),
                        $step_index,
                        $total
                    );
                }
                return __( 'Executing step...', 'creator-core' );

            case 'checkpoint':
                $completed = $data['completed_step'] ?? 0;
                $total     = $data['total_steps'] ?? 0;
                if ( $completed && $total ) {
                    $percentage = round( ( $completed / $total ) * 100 );
                    return sprintf(
                        /* translators: 1: percentage, 2: completed steps, 3: total steps */
                        __( 'Progress: %1$d%% (%2$d/%3$d steps completed)', 'creator-core' ),
                        $percentage,
                        $completed,
                        $total
                    );
                }
                return __( 'Checkpoint reached', 'creator-core' );

            case 'execute':
                return __( 'Executing code...', 'creator-core' );

            case 'verify':
                return __( 'Verifying changes...', 'creator-core' );

            case 'wp_cli':
                $command = $data['command'] ?? '';
                if ( $command ) {
                    return sprintf(
                        /* translators: %s: WP-CLI command */
                        __( 'Running WP-CLI: %s', 'creator-core' ),
                        $command
                    );
                }
                return __( 'Running WP-CLI command...', 'creator-core' );

            case 'compress_history':
                return __( 'Optimizing conversation history...', 'creator-core' );

            case 'question':
                return __( 'Preparing clarification question...', 'creator-core' );

            case 'plan':
                return __( 'Creating action plan...', 'creator-core' );

            case 'complete':
                return __( 'Task completed', 'creator-core' );

            case 'error':
                return __( 'Error encountered', 'creator-core' );

            default:
                if ( $status ) {
                    return $status;
                }
                return sprintf(
                    /* translators: %s: step type */
                    __( 'Processing: %s', 'creator-core' ),
                    $type
                );
        }
    }

    /**
     * Validate and sanitize uploaded files
     *
     * @param array $files Array of file data from request.
     * @return array Validated files array.
     */
    private function validate_files( array $files ): array {
        if ( empty( $files ) ) {
            return [];
        }

        $max_files     = 5;
        $max_file_size = 10 * 1024 * 1024; // 10MB.
        $allowed_types = [
            'image/png',
            'image/jpeg',
            'image/jpg',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
            'text/html',
            'text/css',
            'application/javascript',
            'application/json',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        $validated = [];

        foreach ( $files as $index => $file ) {
            // Limit number of files.
            if ( count( $validated ) >= $max_files ) {
                break;
            }

            // Validate required fields.
            if ( empty( $file['name'] ) || empty( $file['type'] ) || empty( $file['data'] ) ) {
                continue;
            }

            // Validate file type.
            if ( ! in_array( $file['type'], $allowed_types, true ) ) {
                continue;
            }

            // Validate file size (base64 is ~33% larger than original).
            $data_size = strlen( $file['data'] ) * 0.75;
            if ( $data_size > $max_file_size ) {
                continue;
            }

            // Validate base64 encoding.
            if ( base64_decode( $file['data'], true ) === false ) {
                continue;
            }

            $validated[] = [
                'name'   => sanitize_file_name( $file['name'] ),
                'type'   => $file['type'],
                'size'   => $file['size'] ?? $data_size,
                'base64' => $file['data'], // Firebase expects 'base64' field.
            ];
        }

        return $validated;
    }
}
