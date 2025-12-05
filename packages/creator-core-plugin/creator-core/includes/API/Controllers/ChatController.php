<?php
/**
 * Chat Controller
 *
 * Handles all chat-related REST API endpoints:
 * - CRUD operations for chats
 * - Message sending and retrieval
 * - Chat backup/export for debugging
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\API\Controllers;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Chat\ChatInterface;
use CreatorCore\Chat\ChatBackup;

/**
 * Class ChatController
 *
 * REST API controller for chat operations.
 */
class ChatController extends BaseController {

	/**
	 * Chat interface instance
	 *
	 * @var ChatInterface
	 */
	private ChatInterface $chat_interface;

	/**
	 * Constructor
	 *
	 * @param ChatInterface $chat_interface Chat interface instance.
	 */
	public function __construct( ChatInterface $chat_interface ) {
		$this->chat_interface = $chat_interface;
	}

	/**
	 * Register routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Chat list and create
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

		// Single chat operations
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

		// Messages
		register_rest_route( self::NAMESPACE, '/chats/(?P<chat_id>\d+)/messages', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_messages' ],
				'permission_callback' => [ $this, 'check_permission' ],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'send_message' ],
				'permission_callback' => [ $this, 'check_ai_permission' ],
				'args'                => [
					'content' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					],
				],
			],
		]);

		// Chat backup/logs for debugging
		register_rest_route( self::NAMESPACE, '/chats/(?P<chat_id>\d+)/logs', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_chat_logs' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
			'args'                => [
				'limit' => [
					'type'    => 'integer',
					'default' => 50,
				],
				'offset' => [
					'type'    => 'integer',
					'default' => 0,
				],
			],
		]);

		// Export chat backup
		register_rest_route( self::NAMESPACE, '/chats/(?P<chat_id>\d+)/export', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'export_chat' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		]);

		// Chat backup stats (admin only)
		register_rest_route( self::NAMESPACE, '/chats/backup-stats', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_backup_stats' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		]);
	}

	/**
	 * Check AI permission (stricter rate limiting)
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function check_ai_permission( \WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return $this->error( 'rest_forbidden', __( 'You must be logged in.', 'creator-core' ), 401 );
		}

		if ( ! $this->get_capability_checker()->can_use_creator() ) {
			return $this->error( 'rest_forbidden', __( 'Permission denied.', 'creator-core' ), 403 );
		}

		// AI-specific rate limiting
		$rate_limiter = $this->get_rate_limiter();
		if ( ! $rate_limiter->is_exempt() ) {
			$rate_check = $rate_limiter->check_rate_limit( 'ai' );
			if ( is_wp_error( $rate_check ) ) {
				return $rate_check;
			}
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

		return $this->success( $chats );
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
			return $this->error(
				'chat_creation_failed',
				__( 'Failed to create chat', 'creator-core' ),
				500
			);
		}

		$chat = $this->chat_interface->get_chat( $chat_id );

		$this->log( 'chat_created', [ 'chat_id' => $chat_id ] );

		return $this->success( [
			'success' => true,
			'chat'    => $chat,
		], 201 );
	}

	/**
	 * Get a single chat
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_chat( \WP_REST_Request $request ) {
		$chat_id = (int) $request->get_param( 'id' );
		$chat    = $this->chat_interface->get_chat( $chat_id );

		if ( ! $chat ) {
			return $this->error( 'chat_not_found', __( 'Chat not found', 'creator-core' ), 404 );
		}

		// Check ownership
		if ( (int) $chat['user_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			return $this->error(
				'rest_forbidden',
				__( 'You do not have access to this chat', 'creator-core' ),
				403
			);
		}

		return $this->success( $chat );
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
			return $this->error( 'chat_not_found', __( 'Chat not found', 'creator-core' ), 404 );
		}

		if ( $title ) {
			$this->chat_interface->update_chat_title( $chat_id, $title );
		}

		if ( $status === 'archived' ) {
			$this->chat_interface->archive_chat( $chat_id );
		}

		return $this->success( [
			'success' => true,
			'chat'    => $this->chat_interface->get_chat( $chat_id ),
		] );
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
			return $this->error(
				'delete_failed',
				__( 'Failed to delete chat', 'creator-core' ),
				500
			);
		}

		// Also delete chat backup logs
		ChatBackup::delete_chat_backup( $chat_id );

		$this->log( 'chat_deleted', [ 'chat_id' => $chat_id ] );

		return $this->success( [ 'success' => true ] );
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

		return $this->success( $messages );
	}

	/**
	 * Send a message to a chat
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function send_message( \WP_REST_Request $request ) {
		$chat_id = (int) $request->get_param( 'chat_id' );
		$content = $request->get_param( 'content' );

		if ( empty( $content ) ) {
			return $this->error(
				'empty_message',
				__( 'Message content is required', 'creator-core' ),
				400
			);
		}

		try {
			$result = $this->chat_interface->send_message( $chat_id, $content );

			if ( ! $result['success'] ) {
				// Log error to chat backup
				ChatBackup::log_error( $chat_id, 'message_failed', $result['error'] ?? 'Unknown error', [
					'user_content' => $content,
				] );

				return $this->error(
					'message_failed',
					$result['error'] ?? __( 'Failed to send message', 'creator-core' ),
					500
				);
			}

			return $this->success( $result );
		} catch ( \Throwable $e ) {
			// Log exception to chat backup
			ChatBackup::log_error( $chat_id, 'message_exception', $e->getMessage(), [
				'user_content' => $content,
				'file'         => $e->getFile(),
				'line'         => $e->getLine(),
			] );

			error_log( 'Creator send_message error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );

			return $this->error(
				'message_exception',
				sprintf( 'Error: %s (in %s:%d)', $e->getMessage(), basename( $e->getFile() ), $e->getLine() ),
				500
			);
		}
	}

	/**
	 * Get chat logs for debugging
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_chat_logs( \WP_REST_Request $request ): \WP_REST_Response {
		$chat_id = (int) $request->get_param( 'chat_id' );
		$limit   = (int) $request->get_param( 'limit' );
		$offset  = (int) $request->get_param( 'offset' );

		$logs = ChatBackup::get_chat_logs( $chat_id, $limit, $offset );

		return $this->success( [
			'chat_id' => $chat_id,
			'logs'    => $logs,
			'count'   => count( $logs ),
		] );
	}

	/**
	 * Export chat backup as JSON
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function export_chat( \WP_REST_Request $request ): \WP_REST_Response {
		$chat_id = (int) $request->get_param( 'chat_id' );

		$export = ChatBackup::export_chat_backup( $chat_id );

		return $this->success( $export );
	}

	/**
	 * Get backup statistics
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_backup_stats( \WP_REST_Request $request ): \WP_REST_Response {
		$stats = ChatBackup::get_stats();

		return $this->success( $stats );
	}
}
