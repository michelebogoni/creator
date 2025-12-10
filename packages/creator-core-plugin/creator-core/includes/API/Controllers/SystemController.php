<?php
/**
 * System Controller
 *
 * Handles system-related REST API endpoints:
 * - Statistics
 * - Health check
 * - Thinking logs
 * - Debug information
 *
 * MVP version: Simplified without OperationTracker, CapabilityChecker, AuditLogger.
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\API\Controllers;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Context\ThinkingLogger;

/**
 * Class SystemController
 *
 * REST API controller for system operations.
 */
class SystemController extends BaseController {

	/**
	 * Register routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Statistics
		register_rest_route( self::NAMESPACE, '/stats', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_stats' ],
			'permission_callback' => [ $this, 'check_permission' ],
		]);

		// Health check (public but rate-limited)
		register_rest_route( self::NAMESPACE, '/health', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'health_check' ],
			'permission_callback' => [ $this, 'check_health_rate_limit' ],
		]);

		// Thinking logs
		register_rest_route( self::NAMESPACE, '/thinking/(?P<chat_id>\d+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_thinking_log' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'after_index' => [
					'type'    => 'integer',
					'default' => 0,
				],
			],
		]);

		// Thinking stream (SSE)
		register_rest_route( self::NAMESPACE, '/thinking/stream/(?P<chat_id>\d+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'stream_thinking' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'last_index' => [
					'type'    => 'integer',
					'default' => 0,
				],
			],
		]);

		// Debug log
		register_rest_route( self::NAMESPACE, '/debug/log', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_debug_log' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		]);
	}

	/**
	 * Get usage statistics
	 *
	 * MVP version: Returns basic stats from database.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_stats( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$user_id = get_current_user_id();

		$stats = [
			'chats' => [
				'total'  => (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}creator_chats WHERE user_id = %d",
						$user_id
					)
				),
				'active' => (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}creator_chats WHERE user_id = %d AND status = 'active'",
						$user_id
					)
				),
			],
			'messages' => [
				'total' => (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}creator_messages m
						 JOIN {$wpdb->prefix}creator_chats c ON m.chat_id = c.id
						 WHERE c.user_id = %d",
						$user_id
					)
				),
			],
		];

		return $this->success( $stats );
	}

	/**
	 * Health check endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function health_check( \WP_REST_Request $request ): \WP_REST_Response {
		return $this->success( [
			'status'    => 'healthy',
			'version'   => CREATOR_CORE_VERSION,
			'timestamp' => current_time( 'mysql' ),
		] );
	}

	/**
	 * Check health endpoint rate limit (public)
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function check_health_rate_limit( \WP_REST_Request $request ) {
		$rate_limiter = $this->get_rate_limiter();
		$rate_check   = $rate_limiter->check_rate_limit( 'default' );

		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		return true;
	}

	/**
	 * Get thinking log for a chat
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_thinking_log( \WP_REST_Request $request ): \WP_REST_Response {
		$chat_id     = (int) $request->get_param( 'chat_id' );
		$after_index = (int) $request->get_param( 'after_index' );

		$logger = new ThinkingLogger( $chat_id );
		$logs   = $logger->get_logs( $after_index );

		return $this->success( [
			'logs'       => $logs,
			'chat_id'    => $chat_id,
			'last_index' => count( $logs ) > 0 ? $after_index + count( $logs ) : $after_index,
		] );
	}

	/**
	 * Stream thinking logs via SSE
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function stream_thinking( \WP_REST_Request $request ): \WP_REST_Response {
		$chat_id    = (int) $request->get_param( 'chat_id' );
		$last_index = (int) $request->get_param( 'last_index' );

		// Get new logs since last index
		$logger = new ThinkingLogger( $chat_id );
		$logs   = $logger->get_logs( $last_index );

		return $this->success( [
			'logs'       => $logs,
			'chat_id'    => $chat_id,
			'last_index' => $last_index + count( $logs ),
			'has_more'   => count( $logs ) > 0,
		] );
	}

	/**
	 * Get debug log
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_debug_log( \WP_REST_Request $request ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return $this->error( 'debug_disabled', __( 'Debug mode is disabled', 'creator-core' ), 400 );
		}

		$debug_log_path = WP_CONTENT_DIR . '/debug.log';

		if ( ! file_exists( $debug_log_path ) ) {
			return $this->success( [ 'log' => '' ] );
		}

		// Get last 100 lines
		$log = file_get_contents( $debug_log_path );
		$lines = explode( "\n", $log );
		$last_lines = array_slice( $lines, -100 );

		return $this->success( [
			'log'   => implode( "\n", $last_lines ),
			'lines' => count( $last_lines ),
		] );
	}
}
