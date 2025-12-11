<?php
/**
 * Debug Controller
 *
 * REST endpoints for reading and managing debug logs.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Debug;

defined( 'ABSPATH' ) || exit;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class DebugController
 *
 * Provides REST API endpoints for debug log access
 */
class DebugController {

    /**
     * REST namespace
     *
     * @var string
     */
    private string $namespace = 'creator/v1';

    /**
     * Debug logger instance
     *
     * @var DebugLogger
     */
    private DebugLogger $logger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new DebugLogger();
    }

    /**
     * Register REST routes
     *
     * @return void
     */
    public function register_routes(): void {
        // GET /creator/v1/debug/sessions - List all debug sessions.
        register_rest_route(
            $this->namespace,
            '/debug/sessions',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_sessions' ],
                'permission_callback' => [ $this, 'check_permission' ],
                'args'                => [
                    'limit' => [
                        'required'          => false,
                        'type'              => 'integer',
                        'default'           => 20,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );

        // GET /creator/v1/debug/session/{session_id} - Get logs for a specific session.
        register_rest_route(
            $this->namespace,
            '/debug/session/(?P<session_id>[a-zA-Z0-9_-]+)',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_session_logs' ],
                'permission_callback' => [ $this, 'check_permission' ],
                'args'                => [
                    'session_id' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );

        // GET /creator/v1/debug/recent - Get recent logs.
        register_rest_route(
            $this->namespace,
            '/debug/recent',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_recent_logs' ],
                'permission_callback' => [ $this, 'check_permission' ],
                'args'                => [
                    'limit' => [
                        'required'          => false,
                        'type'              => 'integer',
                        'default'           => 100,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );

        // DELETE /creator/v1/debug/clear - Clear all logs.
        register_rest_route(
            $this->namespace,
            '/debug/clear',
            [
                'methods'             => 'DELETE',
                'callback'            => [ $this, 'clear_logs' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ]
        );

        // GET /creator/v1/debug/status - Get debug status.
        register_rest_route(
            $this->namespace,
            '/debug/status',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_status' ],
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
                __( 'You do not have permission to access debug logs.', 'creator-core' ),
                [ 'status' => 403 ]
            );
        }

        return true;
    }

    /**
     * Get list of debug sessions
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response
     */
    public function get_sessions( WP_REST_Request $request ): WP_REST_Response {
        $limit    = min( $request->get_param( 'limit' ), 100 );
        $sessions = $this->logger->get_sessions( $limit );

        return new WP_REST_Response(
            [
                'success'  => true,
                'sessions' => $sessions,
                'count'    => count( $sessions ),
            ],
            200
        );
    }

    /**
     * Get logs for a specific session
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response
     */
    public function get_session_logs( WP_REST_Request $request ): WP_REST_Response {
        $session_id = $request->get_param( 'session_id' );
        $logs       = $this->logger->get_session_logs( $session_id );

        if ( empty( $logs ) ) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => __( 'No logs found for this session.', 'creator-core' ),
                ],
                404
            );
        }

        return new WP_REST_Response(
            [
                'success'    => true,
                'session_id' => $session_id,
                'logs'       => $logs,
                'count'      => count( $logs ),
            ],
            200
        );
    }

    /**
     * Get recent logs
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response
     */
    public function get_recent_logs( WP_REST_Request $request ): WP_REST_Response {
        $limit = min( $request->get_param( 'limit' ), 500 );
        $logs  = $this->logger->get_recent_logs( $limit );

        return new WP_REST_Response(
            [
                'success' => true,
                'logs'    => $logs,
                'count'   => count( $logs ),
            ],
            200
        );
    }

    /**
     * Clear all debug logs
     *
     * @return WP_REST_Response
     */
    public function clear_logs(): WP_REST_Response {
        $result = $this->logger->clear_logs();

        return new WP_REST_Response(
            [
                'success' => $result,
                'message' => $result
                    ? __( 'Debug logs cleared successfully.', 'creator-core' )
                    : __( 'Failed to clear debug logs.', 'creator-core' ),
            ],
            $result ? 200 : 500
        );
    }

    /**
     * Get debug status
     *
     * @return WP_REST_Response
     */
    public function get_status(): WP_REST_Response {
        return new WP_REST_Response(
            [
                'success'  => true,
                'enabled'  => $this->logger->is_enabled(),
                'log_file' => $this->logger->get_log_file_path(),
                'log_size' => $this->logger->get_log_size(),
            ],
            200
        );
    }
}
