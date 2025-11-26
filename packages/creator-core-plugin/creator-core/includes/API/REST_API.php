<?php
/**
 * REST API
 *
 * @package CreatorCore
 */

namespace CreatorCore\API;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Chat\ChatInterface;
use CreatorCore\Permission\CapabilityChecker;
use CreatorCore\Audit\AuditLogger;
use CreatorCore\Backup\Rollback;
use CreatorCore\Executor\ActionExecutor;

/**
 * Class REST_API
 *
 * Handles REST API endpoints for Creator
 */
class REST_API {

    /**
     * API namespace
     */
    const NAMESPACE = 'creator/v1';

    /**
     * Chat interface instance
     *
     * @var ChatInterface
     */
    private ChatInterface $chat_interface;

    /**
     * Capability checker instance
     *
     * @var CapabilityChecker
     */
    private CapabilityChecker $capability_checker;

    /**
     * Audit logger instance
     *
     * @var AuditLogger
     */
    private AuditLogger $logger;

    /**
     * Constructor
     *
     * @param ChatInterface     $chat_interface     Chat interface instance.
     * @param CapabilityChecker $capability_checker Capability checker instance.
     * @param AuditLogger       $logger             Audit logger instance.
     */
    public function __construct(
        ChatInterface $chat_interface,
        CapabilityChecker $capability_checker,
        AuditLogger $logger
    ) {
        $this->chat_interface     = $chat_interface;
        $this->capability_checker = $capability_checker;
        $this->logger             = $logger;
    }

    /**
     * Register REST routes
     *
     * @return void
     */
    public function register_routes(): void {
        // Chat endpoints
        register_rest_route( self::NAMESPACE, '/chats', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_chats' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_chat' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
        ]);

        register_rest_route( self::NAMESPACE, '/chats/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_chat' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_chat' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_chat' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
        ]);

        // Message endpoints
        register_rest_route( self::NAMESPACE, '/chats/(?P<chat_id>\d+)/messages', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_messages' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'send_message' ],
                'permission_callback' => [ $this, 'check_permission' ],
                'args'                => [
                    'content' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field',
                    ],
                ],
            ],
        ]);

        // Action endpoints
        register_rest_route( self::NAMESPACE, '/actions/execute', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'execute_action' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args'                => [
                'action' => [
                    'required' => true,
                    'type'     => 'object',
                ],
                'chat_id' => [
                    'required' => true,
                    'type'     => 'integer',
                ],
                'message_id' => [
                    'required' => true,
                    'type'     => 'integer',
                ],
            ],
        ]);

        // Rollback endpoint
        register_rest_route( self::NAMESPACE, '/actions/(?P<action_id>\d+)/rollback', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'rollback_action' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ]);

        // Stats endpoint
        register_rest_route( self::NAMESPACE, '/stats', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_stats' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ]);

        // Health check
        register_rest_route( self::NAMESPACE, '/health', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'health_check' ],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Check permission for API access
     *
     * @param \WP_REST_Request $request Request object.
     * @return bool|\WP_Error
     */
    public function check_permission( \WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'You must be logged in to use Creator API.', 'creator-core' ),
                [ 'status' => 401 ]
            );
        }

        if ( ! $this->capability_checker->can_use_creator() ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to use Creator.', 'creator-core' ),
                [ 'status' => 403 ]
            );
        }

        return true;
    }

    /**
     * Get user's chats
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_chats( \WP_REST_Request $request ): \WP_REST_Response {
        $args = [
            'status'   => $request->get_param( 'status' ) ?? 'active',
            'per_page' => $request->get_param( 'per_page' ) ?? 20,
            'page'     => $request->get_param( 'page' ) ?? 1,
        ];

        $chats = $this->chat_interface->get_user_chats( null, $args );

        return rest_ensure_response( $chats );
    }

    /**
     * Create a new chat
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function create_chat( \WP_REST_Request $request ) {
        $title   = $request->get_param( 'title' ) ?? '';
        $chat_id = $this->chat_interface->create_chat( $title );

        if ( ! $chat_id ) {
            return new \WP_Error(
                'chat_creation_failed',
                __( 'Failed to create chat', 'creator-core' ),
                [ 'status' => 500 ]
            );
        }

        $chat = $this->chat_interface->get_chat( $chat_id );

        return rest_ensure_response( [
            'success' => true,
            'chat'    => $chat,
        ]);
    }

    /**
     * Get a specific chat
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_chat( \WP_REST_Request $request ) {
        $chat_id = (int) $request->get_param( 'id' );
        $chat    = $this->chat_interface->get_chat( $chat_id );

        if ( ! $chat ) {
            return new \WP_Error(
                'chat_not_found',
                __( 'Chat not found', 'creator-core' ),
                [ 'status' => 404 ]
            );
        }

        // Check ownership
        if ( (int) $chat['user_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'You do not have access to this chat', 'creator-core' ),
                [ 'status' => 403 ]
            );
        }

        return rest_ensure_response( $chat );
    }

    /**
     * Update a chat
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function update_chat( \WP_REST_Request $request ) {
        $chat_id = (int) $request->get_param( 'id' );
        $title   = $request->get_param( 'title' );
        $status  = $request->get_param( 'status' );

        $chat = $this->chat_interface->get_chat( $chat_id );

        if ( ! $chat ) {
            return new \WP_Error(
                'chat_not_found',
                __( 'Chat not found', 'creator-core' ),
                [ 'status' => 404 ]
            );
        }

        if ( $title ) {
            $this->chat_interface->update_chat_title( $chat_id, $title );
        }

        if ( $status === 'archived' ) {
            $this->chat_interface->archive_chat( $chat_id );
        }

        return rest_ensure_response( [
            'success' => true,
            'chat'    => $this->chat_interface->get_chat( $chat_id ),
        ]);
    }

    /**
     * Delete a chat
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function delete_chat( \WP_REST_Request $request ) {
        $chat_id = (int) $request->get_param( 'id' );
        $result  = $this->chat_interface->delete_chat( $chat_id );

        if ( ! $result ) {
            return new \WP_Error(
                'delete_failed',
                __( 'Failed to delete chat', 'creator-core' ),
                [ 'status' => 500 ]
            );
        }

        return rest_ensure_response( [ 'success' => true ] );
    }

    /**
     * Get messages for a chat
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_messages( \WP_REST_Request $request ): \WP_REST_Response {
        $chat_id  = (int) $request->get_param( 'chat_id' );
        $messages = $this->chat_interface->get_chat_messages( $chat_id );

        return rest_ensure_response( $messages );
    }

    /**
     * Send a message
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function send_message( \WP_REST_Request $request ) {
        $chat_id = (int) $request->get_param( 'chat_id' );
        $content = $request->get_param( 'content' );

        if ( empty( $content ) ) {
            return new \WP_Error(
                'empty_message',
                __( 'Message content is required', 'creator-core' ),
                [ 'status' => 400 ]
            );
        }

        $result = $this->chat_interface->send_message( $chat_id, $content );

        if ( ! $result['success'] ) {
            return new \WP_Error(
                'message_failed',
                $result['error'] ?? __( 'Failed to send message', 'creator-core' ),
                [ 'status' => 500 ]
            );
        }

        return rest_ensure_response( $result );
    }

    /**
     * Execute an action
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function execute_action( \WP_REST_Request $request ) {
        $action     = $request->get_param( 'action' );
        $chat_id    = (int) $request->get_param( 'chat_id' );
        $message_id = (int) $request->get_param( 'message_id' );

        $executor = new ActionExecutor( $this->logger, $this->capability_checker );
        $result   = $executor->execute( $action, $chat_id, $message_id );

        if ( ! $result['success'] ) {
            return new \WP_Error(
                $result['code'] ?? 'execution_failed',
                $result['error'] ?? __( 'Action execution failed', 'creator-core' ),
                [ 'status' => 500 ]
            );
        }

        return rest_ensure_response( $result );
    }

    /**
     * Rollback an action
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function rollback_action( \WP_REST_Request $request ) {
        $action_id = (int) $request->get_param( 'action_id' );

        $rollback = new Rollback();
        $result   = $rollback->rollback_action( $action_id );

        if ( ! $result['success'] ) {
            return new \WP_Error(
                'rollback_failed',
                $result['error'] ?? __( 'Rollback failed', 'creator-core' ),
                [ 'status' => 500 ]
            );
        }

        return rest_ensure_response( $result );
    }

    /**
     * Get statistics
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_stats( \WP_REST_Request $request ): \WP_REST_Response {
        $period = $request->get_param( 'period' ) ?? 'today';

        $audit_stats     = $this->logger->get_stats( $period );
        $operation_stats = ( new \CreatorCore\Audit\OperationTracker() )->get_stats( $period );

        return rest_ensure_response( [
            'audit'      => $audit_stats,
            'operations' => $operation_stats,
            'period'     => $period,
        ]);
    }

    /**
     * Health check endpoint
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function health_check( \WP_REST_Request $request ): \WP_REST_Response {
        return rest_ensure_response( [
            'status'  => 'ok',
            'version' => CREATOR_CORE_VERSION,
            'time'    => current_time( 'mysql' ),
        ]);
    }
}
