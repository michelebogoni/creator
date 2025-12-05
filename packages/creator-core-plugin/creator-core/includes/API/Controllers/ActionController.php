<?php
/**
 * Action Controller
 *
 * Handles action-related REST API endpoints:
 * - Execute action (context request or code execution)
 * - Rollback action
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\API\Controllers;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Chat\ChatInterface;
use CreatorCore\Backup\Rollback;
use CreatorCore\Context\ContextLoader;

/**
 * Class ActionController
 *
 * REST API controller for action operations.
 */
class ActionController extends BaseController {

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
		// Execute action
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
				],
			],
		]);

		// Rollback action
		register_rest_route( self::NAMESPACE, '/actions/(?P<action_id>\d+)/rollback', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'rollback_action' ],
			'permission_callback' => [ $this, 'check_permission' ],
		]);
	}

	/**
	 * Execute an action (context request or code execution)
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function execute_action( \WP_REST_Request $request ) {
		$action  = $request->get_param( 'action' );
		$chat_id = (int) $request->get_param( 'chat_id' );

		if ( empty( $action ) || ! is_array( $action ) ) {
			return $this->error(
				'invalid_action',
				__( 'Invalid action data', 'creator-core' ),
				400
			);
		}

		$type = $action['type'] ?? '';

		// Handle context request actions (lazy-load)
		$context_types = [
			'get_plugin_details',
			'get_acf_details',
			'get_cpt_details',
			'get_taxonomy_details',
			'get_wp_functions',
		];

		if ( in_array( $type, $context_types, true ) ) {
			try {
				$context_loader = new ContextLoader();
				$result         = $context_loader->handle_context_request( $action );

				return $this->success( [
					'success' => $result['success'] ?? false,
					'data'    => $result['data'] ?? null,
					'error'   => $result['error'] ?? null,
					'type'    => $type,
				] );
			} catch ( \Throwable $e ) {
				return $this->error(
					'context_request_failed',
					$e->getMessage(),
					500
				);
			}
		}

		// Handle code execution actions
		if ( ! empty( $action['code'] ) ) {
			try {
				$result = $this->chat_interface->execute_action_code( $action, $chat_id );

				return $this->success( $result );
			} catch ( \Throwable $e ) {
				return $this->error(
					'code_execution_failed',
					$e->getMessage(),
					500
				);
			}
		}

		return $this->error(
			'unknown_action_type',
			sprintf( __( 'Unknown action type: %s', 'creator-core' ), $type ),
			400
		);
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
			return $this->error(
				'rollback_failed',
				$result['error'] ?? __( 'Rollback failed', 'creator-core' ),
				500
			);
		}

		return $this->success( $result );
	}
}
