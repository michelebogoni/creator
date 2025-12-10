<?php
/**
 * Chat Controller
 *
 * Handles REST API endpoints for chat operations.
 * MVP version: Standalone controller without BaseController dependency.
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\Chat;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Proxy\ProxyClient;
use CreatorCore\Context\ContextLoader;
use CreatorCore\Response\ResponseHandler;
use CreatorCore\Executor\CodeExecutor;

/**
 * Class ChatController
 *
 * REST API controller for chat operations.
 */
class ChatController {

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
	 * Proxy client instance
	 *
	 * @var ProxyClient
	 */
	private ProxyClient $proxy_client;

	/**
	 * Constructor
	 *
	 * @param ChatInterface $chat_interface Chat interface instance.
	 * @param ProxyClient   $proxy_client   Proxy client instance.
	 */
	public function __construct( ChatInterface $chat_interface, ProxyClient $proxy_client ) {
		$this->chat_interface = $chat_interface;
		$this->proxy_client   = $proxy_client;
	}

	/**
	 * Register routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// Main chat endpoint (POST /creator/v1/chat)
		register_rest_route( self::NAMESPACE, '/chat', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'handle_chat' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'message' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_textarea_field',
				],
				'conversation_id' => [
					'required' => false,
					'type'     => 'string',
					'default'  => '',
				],
				'conversation_history' => [
					'required' => false,
					'type'     => 'array',
					'default'  => [],
				],
			],
		]);

		// Chat list
		register_rest_route( self::NAMESPACE, '/chats', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_chats' ],
			'permission_callback' => [ $this, 'check_permission' ],
		]);

		// Single chat
		register_rest_route( self::NAMESPACE, '/chats/(?P<id>\d+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_chat' ],
			'permission_callback' => [ $this, 'check_permission' ],
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
				__( 'You must be logged in.', 'creator-core' ),
				[ 'status' => 401 ]
			);
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Permission denied.', 'creator-core' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Handle chat message
	 *
	 * Main endpoint for chat interactions:
	 * 1. Receives user message
	 * 2. Sends to Firebase proxy with context
	 * 3. Handles response (including code execution)
	 * 4. Returns result
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_chat( \WP_REST_Request $request ) {
		$message         = $request->get_param( 'message' );
		$conversation_id = $request->get_param( 'conversation_id' );
		$history         = $request->get_param( 'conversation_history' );

		if ( empty( trim( $message ) ) ) {
			return new \WP_Error(
				'empty_message',
				__( 'Message cannot be empty', 'creator-core' ),
				[ 'status' => 400 ]
			);
		}

		try {
			// Get WordPress context
			$context_loader = new ContextLoader();
			$context        = $context_loader->get_context();

			// Send to Firebase proxy
			$ai_response = $this->proxy_client->send_message( [
				'prompt'               => $message,
				'context'              => $context,
				'conversation_history' => $history,
			] );

			if ( ! $ai_response['success'] ) {
				return new \WP_Error(
					'ai_request_failed',
					$ai_response['error'] ?? __( 'AI request failed', 'creator-core' ),
					[ 'status' => 500 ]
				);
			}

			// Parse and handle response
			$parsed = $this->parse_response( $ai_response );
			$result = $this->handle_ai_response( $parsed, $context );

			return new \WP_REST_Response( [
				'success'         => true,
				'response'        => $result,
				'conversation_id' => $conversation_id,
				'tokens_used'     => $ai_response['tokens_used'] ?? 0,
				'model'           => $ai_response['model'] ?? 'unknown',
			], 200 );

		} catch ( \Throwable $e ) {
			error_log( 'Creator chat error: ' . $e->getMessage() );

			return new \WP_Error(
				'chat_exception',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Handle AI response (including code execution)
	 *
	 * @param array $parsed  Parsed AI response.
	 * @param array $context WordPress context.
	 * @return array Result.
	 */
	private function handle_ai_response( array $parsed, array $context ): array {
		// If response contains code to execute
		if ( $parsed['type'] === 'execute' && ! empty( $parsed['data']['code'] ) ) {
			$executor = new CodeExecutor();
			$result   = $executor->execute( $parsed['data']['code'], $context );

			return [
				'type'             => 'execution_result',
				'message'          => $parsed['message'],
				'execution_result' => $result,
				'success'          => $result['success'],
			];
		}

		// Return as-is for other response types
		return $parsed;
	}

	/**
	 * Parse AI response
	 *
	 * @param array $ai_response Raw AI response.
	 * @return array Parsed response.
	 */
	private function parse_response( array $ai_response ): array {
		$content = $ai_response['content'] ?? '';

		// Try to parse as JSON
		$json = json_decode( $content, true );

		if ( json_last_error() === JSON_ERROR_NONE && is_array( $json ) ) {
			return [
				'type'    => $json['type'] ?? 'message',
				'message' => $json['message'] ?? $content,
				'data'    => $json['data'] ?? [],
			];
		}

		// Plain text response
		return [
			'type'    => 'message',
			'message' => $content,
			'data'    => [],
		];
	}

	/**
	 * Get user's chats
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_chats( \WP_REST_Request $request ): \WP_REST_Response {
		$chats = $this->chat_interface->get_user_chats();
		return new \WP_REST_Response( $chats, 200 );
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
			return new \WP_Error(
				'chat_not_found',
				__( 'Chat not found', 'creator-core' ),
				[ 'status' => 404 ]
			);
		}

		return new \WP_REST_Response( $chat, 200 );
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
		return new \WP_REST_Response( $messages, 200 );
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
			return new \WP_Error(
				'empty_message',
				__( 'Message content is required', 'creator-core' ),
				[ 'status' => 400 ]
			);
		}

		try {
			$result = $this->chat_interface->send_message( $chat_id, $content );

			if ( ! $result['success'] ) {
				return new \WP_Error(
					'message_failed',
					$result['error'] ?? __( 'Failed to send message', 'creator-core' ),
					[ 'status' => 500 ]
				);
			}

			return new \WP_REST_Response( $result, 200 );
		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'message_exception',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}
}
