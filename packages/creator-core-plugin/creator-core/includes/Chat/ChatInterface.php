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
use CreatorCore\Context\CreatorContext;
use CreatorCore\Executor\CodeExecutor;
use CreatorCore\Executor\ExecutionVerifier;

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
     * Creator context instance
     *
     * @var CreatorContext|null
     */
    private ?CreatorContext $creator_context = null;

    /**
     * Phase detector instance
     *
     * @var PhaseDetector|null
     */
    private ?PhaseDetector $phase_detector = null;

    /**
     * Code executor instance
     *
     * @var CodeExecutor|null
     */
    private ?CodeExecutor $code_executor = null;

    /**
     * Execution verifier instance
     *
     * @var ExecutionVerifier|null
     */
    private ?ExecutionVerifier $execution_verifier = null;

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

        // Initialize context system components (may fail gracefully)
        try {
            $this->creator_context    = new CreatorContext();
            $this->phase_detector     = new PhaseDetector();
            $this->code_executor      = new CodeExecutor();
            $this->execution_verifier = new ExecutionVerifier( $logger );
        } catch ( \Throwable $e ) {
            // Log error but don't fail - components can be initialized lazily when needed
            error_log( 'Creator: Failed to initialize context components: ' . $e->getMessage() );
        }
    }

    /**
     * Get Creator context (with lazy initialization)
     *
     * @return CreatorContext
     */
    private function get_creator_context(): CreatorContext {
        if ( $this->creator_context === null ) {
            $this->creator_context = new CreatorContext();
        }
        return $this->creator_context;
    }

    /**
     * Get phase detector (with lazy initialization)
     *
     * @return PhaseDetector
     */
    private function get_phase_detector(): PhaseDetector {
        if ( $this->phase_detector === null ) {
            $this->phase_detector = new PhaseDetector();
        }
        return $this->phase_detector;
    }

    /**
     * Get code executor (with lazy initialization)
     *
     * @return CodeExecutor
     */
    private function get_code_executor(): CodeExecutor {
        if ( $this->code_executor === null ) {
            $this->code_executor = new CodeExecutor();
        }
        return $this->code_executor;
    }

    /**
     * Get execution verifier (with lazy initialization)
     *
     * @return ExecutionVerifier
     */
    private function get_execution_verifier(): ExecutionVerifier {
        if ( $this->execution_verifier === null ) {
            $this->execution_verifier = new ExecutionVerifier( $this->logger );
        }
        return $this->execution_verifier;
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
     * @param string $ai_model AI model (gemini or claude).
     * @return int|false Chat ID or false on failure.
     */
    public function create_chat( string $title = '', string $ai_model = '' ) {
        global $wpdb;

        $user_id = get_current_user_id();

        if ( empty( $title ) ) {
            $title = sprintf(
                /* translators: %s: Date and time */
                __( 'Chat %s', 'creator-core' ),
                current_time( 'Y-m-d H:i' )
            );
        }

        // Use user's default model if not specified
        if ( empty( $ai_model ) || ! in_array( $ai_model, UserProfile::get_valid_models(), true ) ) {
            $ai_model = UserProfile::get_default_model();
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'creator_chats',
            [
                'user_id'    => $user_id,
                'title'      => sanitize_text_field( $title ),
                'status'     => 'active',
                'ai_model'   => $ai_model,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( $result === false ) {
            return false;
        }

        $chat_id = $wpdb->insert_id;

        $this->logger->success( 'chat_created', [
            'chat_id'  => $chat_id,
            'title'    => $title,
            'ai_model' => $ai_model,
        ]);

        return $chat_id;
    }

    /**
     * Get chat AI model
     *
     * @param int $chat_id Chat ID.
     * @return string|null Model or null if not found.
     */
    public function get_chat_model( int $chat_id ): ?string {
        global $wpdb;

        $model = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ai_model FROM {$wpdb->prefix}creator_chats WHERE id = %d",
                $chat_id
            )
        );

        return $model ?: null;
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
        try {
            $user_message_id = $this->message_handler->save_message( $chat_id, $content, 'user' );
        } catch ( \Throwable $e ) {
            error_log( 'Creator: Error saving user message: ' . $e->getMessage() );
            return [
                'success' => false,
                'error'   => 'Error saving message: ' . $e->getMessage(),
            ];
        }

        if ( ! $user_message_id ) {
            return [
                'success' => false,
                'error'   => __( 'Failed to save message', 'creator-core' ),
            ];
        }

        // Build context
        try {
            $context_collector = new ContextCollector();
            $context           = $context_collector->get_wordpress_context();
        } catch ( \Throwable $e ) {
            error_log( 'Creator: Error collecting context: ' . $e->getMessage() );
            return [
                'success' => false,
                'error'   => 'Error collecting context: ' . $e->getMessage(),
            ];
        }

        // Build conversation history
        try {
            $history = $this->build_conversation_history( $chat_id );
        } catch ( \Throwable $e ) {
            error_log( 'Creator: Error building history: ' . $e->getMessage() );
            $history = [];
        }

        // Extract pending actions from previous messages
        try {
            $pending_actions = $this->extract_pending_actions( $chat_id );
        } catch ( \Throwable $e ) {
            error_log( 'Creator: Error extracting pending actions: ' . $e->getMessage() );
            $pending_actions = [];
        }

        // Prepare prompt with context (include pending actions info)
        try {
            $prompt = $this->prepare_prompt( $content, $context, $history, $pending_actions );
        } catch ( \Throwable $e ) {
            error_log( 'Creator: Error preparing prompt: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() );
            return [
                'success' => false,
                'error'   => 'Error preparing prompt: ' . $e->getMessage(),
            ];
        }

        // Get the chat's AI model (locked per chat)
        $ai_model = $chat['ai_model'] ?? UserProfile::get_default_model();

        // Send to AI
        try {
            $ai_response = $this->proxy_client->send_to_ai( $prompt, 'TEXT_GEN', [
                'chat_id'         => $chat_id,
                'message_id'      => $user_message_id,
                'user_message'    => $content, // Original user message for mock mode intent detection
                'pending_actions' => $pending_actions, // Pending actions for confirmation handling
                'conversation'    => $history, // Conversation history for context extraction
                'model'           => $ai_model, // AI model (gemini or claude)
            ]);
        } catch ( \Throwable $e ) {
            error_log( 'Creator: Error sending to AI: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() );
            return [
                'success'         => false,
                'user_message_id' => $user_message_id,
                'error'           => 'Error sending to AI: ' . $e->getMessage(),
            ];
        }

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
        try {
            $parsed_response = $this->parse_ai_response( $ai_content );
        } catch ( \Throwable $e ) {
            error_log( 'Creator: Error parsing AI response: ' . $e->getMessage() );
            $parsed_response = [
                'message'     => $ai_content,
                'actions'     => [],
                'has_actions' => false,
            ];
        }

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
     * Uses the new CreatorContext system for comprehensive context injection.
     *
     * @param string $user_message    User's message.
     * @param array  $context         WordPress context (legacy, kept for compatibility).
     * @param array  $history         Conversation history.
     * @param array  $pending_actions Pending actions from previous messages.
     * @return string
     */
    private function prepare_prompt( string $user_message, array $context, array $history, array $pending_actions = [] ): string {
        // Get comprehensive Creator Context (stored document)
        $creator_context_prompt = '';
        try {
            $creator_context_prompt = $this->get_creator_context()->get_context_as_prompt();
        } catch ( \Throwable $e ) {
            error_log( 'Creator: Error getting context as prompt: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() );
        }

        // If no stored context, fall back to legacy method
        if ( empty( $creator_context_prompt ) ) {
            try {
                $context_collector = new ContextCollector();
                $creator_context_prompt = $context_collector->get_maxi_onboarding_summary();
            } catch ( \Throwable $e ) {
                error_log( 'Creator: Error getting maxi onboarding: ' . $e->getMessage() );
                $creator_context_prompt = ''; // Use empty context as fallback
            }
        }

        // Detect user input type and determine expected phase
        $prev_phase = $this->get_last_phase( $history );
        $input_classification = [ 'type' => 'new_request', 'next_phase' => 'discovery' ]; // Default
        try {
            $input_classification = $this->get_phase_detector()->classify_user_input( $user_message, $prev_phase );
        } catch ( \Throwable $e ) {
            error_log( 'Creator: Error classifying input: ' . $e->getMessage() );
        }

        $prompt = "You are Creator, an AI assistant for WordPress automation.\n\n";

        // Include the full Creator Context document
        $prompt .= $creator_context_prompt . "\n\n";

        // Include conversation history
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
            $prompt .= "set action status to 'ready' and include executable code. ";
            $prompt .= "If they are rejecting (saying 'no', 'cancel', 'annulla', etc.), cancel the actions.\n\n";
        }

        // User input context
        $prompt .= "## User Input Analysis\n";
        $prompt .= sprintf( "- Input type: %s\n", $input_classification['type'] );
        $prompt .= sprintf( "- Expected next phase: %s\n", $input_classification['next_phase'] );
        $prompt .= sprintf( "- Previous phase: %s\n\n", $prev_phase ?: 'none' );

        $prompt .= "## User Request\n";
        $prompt .= $user_message . "\n\n";

        $prompt .= "## Response Format\n";
        $prompt .= "ALWAYS respond with valid JSON in this format:\n";
        $prompt .= "```json\n";
        $prompt .= "{\n";
        $prompt .= '  "phase": "discovery|proposal|execution",' . "\n";
        $prompt .= '  "intent": "action_type or conversation",' . "\n";
        $prompt .= '  "confidence": 0.0-1.0,' . "\n";
        $prompt .= '  "message": "Your message to the user in their language",' . "\n";
        $prompt .= '  "questions": ["question1", "question2"] // only in discovery phase,' . "\n";
        $prompt .= '  "plan": { // only in proposal phase' . "\n";
        $prompt .= '    "summary": "What will be done",' . "\n";
        $prompt .= '    "steps": ["step1", "step2"],' . "\n";
        $prompt .= '    "estimated_credits": 10,' . "\n";
        $prompt .= '    "risks": ["risk1"],' . "\n";
        $prompt .= '    "rollback_possible": true' . "\n";
        $prompt .= '  },' . "\n";
        $prompt .= '  "code": { // only in execution phase' . "\n";
        $prompt .= '    "type": "wpcode_snippet|direct_execution",' . "\n";
        $prompt .= '    "title": "Descriptive title",' . "\n";
        $prompt .= '    "description": "What this code does",' . "\n";
        $prompt .= '    "language": "php",' . "\n";
        $prompt .= '    "content": "<?php // your code here",' . "\n";
        $prompt .= '    "location": "everywhere|frontend|admin",' . "\n";
        $prompt .= '    "auto_execute": false // true only after user confirmation' . "\n";
        $prompt .= '  },' . "\n";
        $prompt .= '  "actions": [{"type": "action_name", "params": {...}, "status": "pending|ready"}]' . "\n";
        $prompt .= "}\n";
        $prompt .= "```\n\n";
        $prompt .= "Phase meanings:\n";
        $prompt .= "- 'discovery': You need more information. Ask clarifying questions.\n";
        $prompt .= "- 'proposal': You have enough info. Propose a plan and ask for confirmation.\n";
        $prompt .= "- 'execution': User confirmed. Generate and execute code.\n\n";
        $prompt .= "Action status meanings:\n";
        $prompt .= "- 'pending': Action needs user confirmation before execution\n";
        $prompt .= "- 'ready': User has confirmed, action should be executed immediately\n\n";
        $prompt .= "For conversations without actions, use actions: [] (empty array).\n";
        $prompt .= "Always respond in the same language the user is using.";

        return $prompt;
    }

    /**
     * Get the last phase from conversation history
     *
     * @param array $history Conversation history.
     * @return string|null
     */
    private function get_last_phase( array $history ): ?string {
        // Look for the last assistant message and try to detect its phase
        for ( $i = count( $history ) - 1; $i >= 0; $i-- ) {
            if ( $history[ $i ]['role'] === 'assistant' ) {
                $content = $history[ $i ]['content'];
                // Try to extract phase from JSON response
                if ( preg_match( '/"phase"\s*:\s*"(discovery|proposal|execution)"/', $content, $matches ) ) {
                    return $matches[1];
                }
            }
        }
        return null;
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
        // Clean up the response - remove markdown code blocks if present
        $cleaned_response = $this->extract_json_from_response( $response );

        // Try to parse as JSON
        $json = json_decode( $cleaned_response, true );

        if ( json_last_error() === JSON_ERROR_NONE && is_array( $json ) ) {
            $parsed = [
                'message'     => $json['message'] ?? $response,
                'actions'     => $json['actions'] ?? [],
                'intent'      => $json['intent'] ?? null,
                'confidence'  => $json['confidence'] ?? 0,
                'has_actions' => ! empty( $json['actions'] ),
                'phase'       => $json['phase'] ?? null,
                'questions'   => $json['questions'] ?? [],
                'plan'        => $json['plan'] ?? null,
                'code'        => $json['code'] ?? null,
            ];

            // Detect phase if not explicit
            if ( empty( $parsed['phase'] ) ) {
                $phase_detection = $this->get_phase_detector()->detect_phase( $json );
                $parsed['phase'] = $phase_detection['phase'];
            }

            // Handle code execution if in execution phase
            if ( $parsed['phase'] === PhaseDetector::PHASE_EXECUTION && ! empty( $parsed['code'] ) ) {
                $execution_result = $this->handle_code_execution( $parsed['code'] );
                $parsed['execution'] = $execution_result;

                // If execution was successful, run verification
                if ( $execution_result['success'] ) {
                    $parsed['verification'] = $this->handle_verification( $parsed );
                }
            }

            return $parsed;
        }

        // Plain text response
        return [
            'message'     => $response,
            'actions'     => [],
            'intent'      => 'conversation',
            'confidence'  => 1.0,
            'has_actions' => false,
            'phase'       => PhaseDetector::PHASE_DISCOVERY,
            'questions'   => [],
            'plan'        => null,
            'code'        => null,
        ];
    }

    /**
     * Handle code execution from AI response
     *
     * @param array $code_data Code data from AI response.
     * @return array Execution result.
     */
    private function handle_code_execution( array $code_data ): array {
        // Only execute if auto_execute is true (user confirmed)
        $auto_execute = $code_data['auto_execute'] ?? false;

        if ( ! $auto_execute ) {
            return [
                'success'  => false,
                'status'   => 'pending_confirmation',
                'message'  => 'Code requires user confirmation before execution',
            ];
        }

        // Execute the code
        $result = $this->get_code_executor()->execute( $code_data );

        // Log execution
        $this->logger->log(
            'code_execution',
            $result['success'] ? 'success' : 'failure',
            [
                'code_type'  => $code_data['type'] ?? 'unknown',
                'title'      => $code_data['title'] ?? 'Untitled',
                'result'     => $result['status'],
                'snippet_id' => $result['snippet_id'] ?? null,
            ]
        );

        return $result;
    }

    /**
     * Handle verification after code execution
     *
     * @param array $parsed_response Parsed AI response.
     * @return array Verification result.
     */
    private function handle_verification( array $parsed_response ): array {
        // Determine action type from intent or actions
        $action_type = $parsed_response['intent'] ?? 'generic';

        // Try to get action type from actions array
        if ( ! empty( $parsed_response['actions'] ) ) {
            $first_action = $parsed_response['actions'][0];
            $action_type = $first_action['type'] ?? $action_type;
        }

        // Build expected parameters from response
        $expected = [];

        // Extract expected values from plan or actions
        if ( ! empty( $parsed_response['plan'] ) ) {
            $expected = $parsed_response['plan'];
        }

        if ( ! empty( $parsed_response['actions'] ) ) {
            foreach ( $parsed_response['actions'] as $action ) {
                if ( isset( $action['params'] ) ) {
                    $expected = array_merge( $expected, $action['params'] );
                }
            }
        }

        // Add execution context
        $context = [
            'success'    => $parsed_response['execution']['success'] ?? false,
            'snippet_id' => $parsed_response['execution']['snippet_id'] ?? null,
            'result_id'  => $parsed_response['execution']['data'] ?? null,
        ];

        // Run verification
        return $this->get_execution_verifier()->verify( $action_type, $expected, $context );
    }

    /**
     * Extract JSON from AI response (handles markdown code blocks)
     *
     * @param string $response Raw AI response.
     * @return string Cleaned JSON string.
     */
    private function extract_json_from_response( string $response ): string {
        $response = trim( $response );

        // Remove markdown code blocks: ```json ... ``` or ``` ... ```
        if ( preg_match( '/```(?:json)?\s*([\s\S]*?)\s*```/', $response, $matches ) ) {
            return trim( $matches[1] );
        }

        // Try to extract JSON object from the response (in case there's text before/after)
        if ( preg_match( '/\{[\s\S]*"message"[\s\S]*\}/', $response, $matches ) ) {
            return $matches[0];
        }

        // Return as-is
        return $response;
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
