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
					'required'          => true,
					'type'              => 'object',
					'validate_callback' => [ $this, 'validate_action_object' ],
					'sanitize_callback' => [ $this, 'sanitize_action_object' ],
				],
				'chat_id' => [
					'required'          => false,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
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

	/**
	 * Validate action object structure
	 *
	 * @param mixed            $value   The action value.
	 * @param \WP_REST_Request $request Request object.
	 * @param string           $key     Parameter key.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_action_object( $value, $request, $key ) {
		if ( ! is_array( $value ) ) {
			return new \WP_Error(
				'invalid_action_type',
				__( 'Action must be an object', 'creator-core' ),
				[ 'status' => 400 ]
			);
		}

		// Must have a type
		if ( empty( $value['type'] ) || ! is_string( $value['type'] ) ) {
			return new \WP_Error(
				'missing_action_type',
				__( 'Action must have a valid type', 'creator-core' ),
				[ 'status' => 400 ]
			);
		}

		// Validate type is from allowed list
		$allowed_types = [
			// Context request types
			'get_plugin_details',
			'get_acf_details',
			'get_cpt_details',
			'get_taxonomy_details',
			'get_wp_functions',
			// Code execution types
			'execute_code',
			'create_snippet',
			'modify_file',
			// Elementor types
			'create_elementor_page',
			'update_elementor_page',
		];

		if ( ! in_array( $value['type'], $allowed_types, true ) ) {
			return new \WP_Error(
				'invalid_action_type',
				sprintf(
					/* translators: %s: action type */
					__( 'Unknown action type: %s', 'creator-core' ),
					sanitize_text_field( $value['type'] )
				),
				[ 'status' => 400 ]
			);
		}

		return true;
	}

	/**
	 * Sanitize action object
	 *
	 * Recursively sanitize all string values in the action object.
	 *
	 * @param mixed $value The action value.
	 * @return array Sanitized action object.
	 */
	public function sanitize_action_object( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		return $this->sanitize_recursive( $value );
	}

	/**
	 * Recursively sanitize an array
	 *
	 * @param array $data Array to sanitize.
	 * @param int   $depth Current depth (max 10 to prevent infinite recursion).
	 * @return array Sanitized array.
	 */
	private function sanitize_recursive( array $data, int $depth = 0 ): array {
		// Prevent infinite recursion
		if ( $depth > 10 ) {
			return [];
		}

		$sanitized = [];

		foreach ( $data as $key => $value ) {
			// Sanitize key
			$clean_key = is_string( $key ) ? sanitize_key( $key ) : $key;

			// Sanitize value based on type
			if ( is_array( $value ) ) {
				$sanitized[ $clean_key ] = $this->sanitize_recursive( $value, $depth + 1 );
			} elseif ( is_string( $value ) ) {
				// For code content, preserve the value but remove null bytes
				if ( in_array( $key, [ 'code', 'content', 'file_content' ], true ) ) {
					$sanitized[ $clean_key ] = str_replace( "\0", '', $value );
				} else {
					// For other strings, use standard sanitization
					$sanitized[ $clean_key ] = sanitize_text_field( $value );
				}
			} elseif ( is_bool( $value ) ) {
				$sanitized[ $clean_key ] = (bool) $value;
			} elseif ( is_int( $value ) ) {
				$sanitized[ $clean_key ] = (int) $value;
			} elseif ( is_float( $value ) ) {
				$sanitized[ $clean_key ] = (float) $value;
			} elseif ( is_null( $value ) ) {
				$sanitized[ $clean_key ] = null;
			}
			// Skip other types (objects, resources, etc.)
		}

		return $sanitized;
	}
}
