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
use CreatorCore\Development\FileSystemManager;
use CreatorCore\Development\PluginGenerator;
use CreatorCore\Development\CodeAnalyzer;
use CreatorCore\Development\DatabaseManager;

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
                    'required' => false,
                    'type'     => 'integer',
                    'default'  => 0,
                ],
                'message_id' => [
                    'required' => false,
                    'type'     => 'integer',
                    'default'  => 0,
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

        // =========================================================================
        // DEVELOPMENT ENDPOINTS
        // =========================================================================

        // File operations
        register_rest_route( self::NAMESPACE, '/files/read', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'read_file' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
            'args'                => [
                'file_path' => [
                    'required' => true,
                    'type'     => 'string',
                ],
            ],
        ]);

        register_rest_route( self::NAMESPACE, '/files/write', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'write_file' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
            'args'                => [
                'file_path' => [
                    'required' => true,
                    'type'     => 'string',
                ],
                'content' => [
                    'required' => true,
                    'type'     => 'string',
                ],
            ],
        ]);

        register_rest_route( self::NAMESPACE, '/files/list', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'list_directory' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        register_rest_route( self::NAMESPACE, '/files/search', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'search_files' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        // Plugin development
        register_rest_route( self::NAMESPACE, '/plugins/create', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'create_plugin' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        register_rest_route( self::NAMESPACE, '/plugins/list', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'list_plugins' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        register_rest_route( self::NAMESPACE, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/info', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_plugin_info' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        register_rest_route( self::NAMESPACE, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/activate', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'activate_plugin' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        register_rest_route( self::NAMESPACE, '/plugins/(?P<slug>[a-zA-Z0-9_-]+)/deactivate', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'deactivate_plugin' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        // Code analysis
        register_rest_route( self::NAMESPACE, '/analyze/file', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'analyze_file' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        register_rest_route( self::NAMESPACE, '/analyze/plugin/(?P<slug>[a-zA-Z0-9_-]+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'analyze_plugin' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        register_rest_route( self::NAMESPACE, '/analyze/theme/(?P<slug>[a-zA-Z0-9_-]+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'analyze_theme' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        register_rest_route( self::NAMESPACE, '/debug/log', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_debug_log' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        // Database
        register_rest_route( self::NAMESPACE, '/database/info', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_database_info' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        register_rest_route( self::NAMESPACE, '/database/query', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'database_query' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
        ]);

        register_rest_route( self::NAMESPACE, '/database/table/(?P<table>[a-zA-Z0-9_]+)/structure', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_table_structure' ],
            'permission_callback' => [ $this, 'check_admin_permission' ],
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
        $title    = $request->get_param( 'title' ) ?? '';
        $ai_model = $request->get_param( 'ai_model' ) ?? '';
        $chat_id  = $this->chat_interface->create_chat( $title, $ai_model );

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

    /**
     * Check admin permission for development endpoints
     *
     * @param \WP_REST_Request $request Request object.
     * @return bool|\WP_Error
     */
    public function check_admin_permission( \WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'You must be logged in.', 'creator-core' ),
                [ 'status' => 401 ]
            );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return new \WP_Error(
                'rest_forbidden',
                __( 'You need administrator privileges for this operation.', 'creator-core' ),
                [ 'status' => 403 ]
            );
        }

        return true;
    }

    // =========================================================================
    // FILE OPERATIONS
    // =========================================================================

    /**
     * Read a file
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function read_file( \WP_REST_Request $request ) {
        $file_path  = $request->get_param( 'file_path' );
        $filesystem = new FileSystemManager( $this->logger );
        $result     = $filesystem->read_file( $file_path );

        if ( ! $result['success'] ) {
            return new \WP_Error( 'file_read_failed', $result['error'], [ 'status' => 400 ] );
        }

        return rest_ensure_response( $result );
    }

    /**
     * Write a file
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function write_file( \WP_REST_Request $request ) {
        $file_path  = $request->get_param( 'file_path' );
        $content    = $request->get_param( 'content' );
        $filesystem = new FileSystemManager( $this->logger );
        $result     = $filesystem->write_file( $file_path, $content );

        if ( ! $result['success'] ) {
            return new \WP_Error( 'file_write_failed', $result['error'], [ 'status' => 400 ] );
        }

        return rest_ensure_response( $result );
    }

    /**
     * List directory contents
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function list_directory( \WP_REST_Request $request ) {
        $dir_path   = $request->get_param( 'dir_path' ) ?? WP_CONTENT_DIR;
        $recursive  = $request->get_param( 'recursive' ) ?? false;
        $filesystem = new FileSystemManager( $this->logger );
        $result     = $filesystem->list_directory( $dir_path, $recursive );

        if ( ! $result['success'] ) {
            return new \WP_Error( 'list_failed', $result['error'], [ 'status' => 400 ] );
        }

        return rest_ensure_response( $result );
    }

    /**
     * Search in files
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function search_files( \WP_REST_Request $request ) {
        $directory   = $request->get_param( 'directory' ) ?? WP_CONTENT_DIR;
        $search_term = $request->get_param( 'search_term' );
        $pattern     = $request->get_param( 'pattern' ) ?? '*.php';

        if ( empty( $search_term ) ) {
            return new \WP_Error( 'missing_param', __( 'Search term is required', 'creator-core' ), [ 'status' => 400 ] );
        }

        $filesystem = new FileSystemManager( $this->logger );
        $result     = $filesystem->search_in_files( $directory, $search_term, $pattern );

        return rest_ensure_response( $result );
    }

    // =========================================================================
    // PLUGIN OPERATIONS
    // =========================================================================

    /**
     * Create a plugin
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function create_plugin( \WP_REST_Request $request ) {
        $config    = $request->get_json_params();
        $generator = new PluginGenerator( $this->logger );
        $result    = $generator->create_plugin( $config );

        if ( ! $result['success'] ) {
            return new \WP_Error( 'plugin_creation_failed', $result['error'], [ 'status' => 400 ] );
        }

        // Auto-activate if requested
        if ( ! empty( $config['activate'] ) ) {
            $generator->activate_plugin( $result['plugin_slug'] );
            $result['activated'] = true;
        }

        return rest_ensure_response( $result );
    }

    /**
     * List all plugins
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function list_plugins( \WP_REST_Request $request ): \WP_REST_Response {
        $generator = new PluginGenerator( $this->logger );
        return rest_ensure_response( $generator->list_plugins() );
    }

    /**
     * Get plugin info
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_plugin_info( \WP_REST_Request $request ) {
        $slug      = $request->get_param( 'slug' );
        $generator = new PluginGenerator( $this->logger );
        $result    = $generator->get_plugin_info( $slug );

        if ( ! $result['success'] ) {
            return new \WP_Error( 'plugin_not_found', $result['error'], [ 'status' => 404 ] );
        }

        return rest_ensure_response( $result );
    }

    /**
     * Activate a plugin
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function activate_plugin( \WP_REST_Request $request ) {
        $slug      = $request->get_param( 'slug' );
        $generator = new PluginGenerator( $this->logger );
        $result    = $generator->activate_plugin( $slug );

        if ( ! $result['success'] ) {
            return new \WP_Error( 'activation_failed', $result['error'], [ 'status' => 400 ] );
        }

        return rest_ensure_response( $result );
    }

    /**
     * Deactivate a plugin
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function deactivate_plugin( \WP_REST_Request $request ): \WP_REST_Response {
        $slug      = $request->get_param( 'slug' );
        $generator = new PluginGenerator( $this->logger );
        return rest_ensure_response( $generator->deactivate_plugin( $slug ) );
    }

    // =========================================================================
    // CODE ANALYSIS
    // =========================================================================

    /**
     * Analyze a file
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function analyze_file( \WP_REST_Request $request ) {
        $file_path = $request->get_param( 'file_path' );

        if ( empty( $file_path ) ) {
            return new \WP_Error( 'missing_param', __( 'File path is required', 'creator-core' ), [ 'status' => 400 ] );
        }

        $analyzer = new CodeAnalyzer( $this->logger );
        return rest_ensure_response( $analyzer->analyze_file( $file_path ) );
    }

    /**
     * Analyze a plugin
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function analyze_plugin( \WP_REST_Request $request ) {
        $slug     = $request->get_param( 'slug' );
        $analyzer = new CodeAnalyzer( $this->logger );
        $result   = $analyzer->analyze_plugin( $slug );

        if ( ! $result['success'] ) {
            return new \WP_Error( 'analysis_failed', $result['error'], [ 'status' => 400 ] );
        }

        return rest_ensure_response( $result );
    }

    /**
     * Analyze a theme
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function analyze_theme( \WP_REST_Request $request ) {
        $slug     = $request->get_param( 'slug' );
        $analyzer = new CodeAnalyzer( $this->logger );
        $result   = $analyzer->analyze_theme( $slug );

        if ( ! $result['success'] ) {
            return new \WP_Error( 'analysis_failed', $result['error'], [ 'status' => 400 ] );
        }

        return rest_ensure_response( $result );
    }

    /**
     * Get debug log
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_debug_log( \WP_REST_Request $request ): \WP_REST_Response {
        $lines    = $request->get_param( 'lines' ) ?? 100;
        $analyzer = new CodeAnalyzer( $this->logger );
        return rest_ensure_response( $analyzer->get_debug_log( (int) $lines ) );
    }

    // =========================================================================
    // DATABASE OPERATIONS
    // =========================================================================

    /**
     * Get database info
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response
     */
    public function get_database_info( \WP_REST_Request $request ): \WP_REST_Response {
        $database = new DatabaseManager( $this->logger );
        return rest_ensure_response( $database->get_database_info() );
    }

    /**
     * Execute database query
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function database_query( \WP_REST_Request $request ) {
        $query  = $request->get_param( 'query' );
        $limit  = $request->get_param( 'limit' ) ?? 100;
        $offset = $request->get_param( 'offset' ) ?? 0;

        if ( empty( $query ) ) {
            return new \WP_Error( 'missing_param', __( 'Query is required', 'creator-core' ), [ 'status' => 400 ] );
        }

        $database = new DatabaseManager( $this->logger );
        $result   = $database->select( $query, (int) $limit, (int) $offset );

        if ( ! $result['success'] ) {
            return new \WP_Error( 'query_failed', $result['error'], [ 'status' => 400 ] );
        }

        return rest_ensure_response( $result );
    }

    /**
     * Get table structure
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_table_structure( \WP_REST_Request $request ) {
        global $wpdb;
        $table    = $request->get_param( 'table' );
        $database = new DatabaseManager( $this->logger );
        $result   = $database->get_table_structure( $wpdb->prefix . $table );

        if ( ! $result['success'] ) {
            return new \WP_Error( 'table_not_found', $result['error'], [ 'status' => 404 ] );
        }

        return rest_ensure_response( $result );
    }
}
