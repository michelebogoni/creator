<?php
/**
 * Base Controller
 *
 * Abstract base class for all REST API controllers.
 * Provides common functionality and enforces consistent structure.
 *
 * MVP version: Simplified without CapabilityChecker and AuditLogger.
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\API\Controllers;

defined( 'ABSPATH' ) || exit;

use CreatorCore\API\RateLimiter;

/**
 * Abstract Class BaseController
 *
 * Base class for all API controllers providing:
 * - Common permission checking
 * - Rate limiting
 * - Error response formatting
 * - Logging
 */
abstract class BaseController {

	/**
	 * API namespace
	 */
	const NAMESPACE = 'creator/v1';

	/**
	 * Rate limiter instance (lazy-loaded)
	 *
	 * @var RateLimiter|null
	 */
	protected ?RateLimiter $rate_limiter = null;

	/**
	 * Register routes for this controller
	 *
	 * @return void
	 */
	abstract public function register_routes(): void;

	/**
	 * Get the rate limit type for this controller
	 *
	 * @return string 'default', 'ai', or 'dev'
	 */
	protected function get_rate_limit_type(): string {
		return 'default';
	}

	/**
	 * Get rate limiter (lazy-loads if needed)
	 *
	 * @return RateLimiter
	 */
	protected function get_rate_limiter(): RateLimiter {
		if ( null === $this->rate_limiter ) {
			$this->rate_limiter = new RateLimiter();
		}
		return $this->rate_limiter;
	}

	/**
	 * Check permission for API access
	 *
	 * Simplified permission check: requires user to be logged in
	 * and have edit_posts capability.
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

		// MVP: Simple capability check - require edit_posts
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to use Creator.', 'creator-core' ),
				[ 'status' => 403 ]
			);
		}

		// Check rate limit (unless exempt)
		$rate_limiter = $this->get_rate_limiter();
		if ( ! $rate_limiter->is_exempt() ) {
			$rate_check = $rate_limiter->check_rate_limit( $this->get_rate_limit_type() );
			if ( is_wp_error( $rate_check ) ) {
				return $rate_check;
			}
		}

		return true;
	}

	/**
	 * Check admin permission
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
				__( 'Administrator access required.', 'creator-core' ),
				[ 'status' => 403 ]
			);
		}

		// Check dev rate limit
		$rate_limiter = $this->get_rate_limiter();
		if ( ! $rate_limiter->is_exempt() ) {
			$rate_check = $rate_limiter->check_rate_limit( 'dev' );
			if ( is_wp_error( $rate_check ) ) {
				return $rate_check;
			}
		}

		return true;
	}

	/**
	 * Create a success response
	 *
	 * @param mixed $data   Response data.
	 * @param int   $status HTTP status code.
	 * @return \WP_REST_Response
	 */
	protected function success( $data, int $status = 200 ): \WP_REST_Response {
		return new \WP_REST_Response( $data, $status );
	}

	/**
	 * Create an error response
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code.
	 * @param array  $data    Additional data.
	 * @return \WP_Error
	 */
	protected function error( string $code, string $message, int $status = 400, array $data = [] ): \WP_Error {
		return new \WP_Error( $code, $message, array_merge( [ 'status' => $status ], $data ) );
	}

	/**
	 * Log an action (simplified - uses error_log in debug mode)
	 *
	 * @param string $action  Action name.
	 * @param array  $details Action details.
	 * @return void
	 */
	protected function log( string $action, array $details = [] ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Creator API: ' . $action . ' ' . wp_json_encode( $details ) );
		}
	}
}
