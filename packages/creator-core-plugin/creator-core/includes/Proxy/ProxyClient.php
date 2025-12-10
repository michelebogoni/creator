<?php
/**
 * Proxy Client
 *
 * @package CreatorCore
 */

namespace CreatorCore\Proxy;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Integrations\PluginDetector;

/**
 * Class ProxyClient
 *
 * Handles communication with the Firebase Proxy API
 */
class ProxyClient {

	/**
	 * Admin license key for unlimited access
	 *
	 * @var string
	 */
	public const ADMIN_LICENSE_KEY = 'CREATOR-2025-ADMIN-ADMIN';

	/**
	 * Proxy base URL
	 *
	 * @var string
	 */
	private string $proxy_url;

	/**
	 * Request timeout in seconds
	 *
	 * @var int
	 */
	private int $timeout = 120;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->proxy_url = get_option( 'creator_proxy_url', CREATOR_PROXY_URL );
	}

	/**
	 * Log message for debugging
	 *
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	private function log( string $message, array $context = [] ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'CreatorCore ProxyClient: ' . $message . ' ' . wp_json_encode( $context ) );
		}
	}

	/**
	 * Check if current license is admin license
	 *
	 * @return bool
	 */
	public function is_admin_license(): bool {
		$license_key = get_option( 'creator_license_key', '' );
		return $license_key === self::ADMIN_LICENSE_KEY;
	}

	/**
	 * Validate license key
	 *
	 * @param string $license_key License key to validate.
	 * @return array
	 */
	public function validate_license( string $license_key ): array {
		$response = $this->make_request( 'POST', '/api/auth/validate-license', [
			'license_key' => $license_key,
			'site_url'    => get_site_url(),
		]);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];
		}

		if ( ! empty( $response['success'] ) && ! empty( $response['site_token'] ) ) {
			update_option( 'creator_site_token', $response['site_token'] );
			update_option( 'creator_license_validated', true );
			update_option( 'creator_license_key', $license_key );
			set_transient( 'creator_license_status', $response, DAY_IN_SECONDS );
		}

		return $response;
	}

	/**
	 * Send request to AI provider through proxy
	 *
	 * @param string $prompt    The prompt to send.
	 * @param string $task_type Task type.
	 * @param array  $options   Additional options.
	 * @return array
	 */
	public function send_to_ai( string $prompt, string $task_type = 'TEXT_GEN', array $options = [] ): array {
		$site_token = get_option( 'creator_site_token' );

		if ( empty( $site_token ) ) {
			return [
				'success' => false,
				'error'   => __( 'Site not authenticated. Please validate your license.', 'creator-core' ),
			];
		}

		$context = $this->get_site_context();

		$request_body = [
			'task_type' => $task_type,
			'prompt'    => $prompt,
			'context'   => $context,
		];

		if ( ! empty( $options['model'] ) ) {
			$request_body['model'] = $options['model'];
		}

		if ( ! empty( $options['chat_id'] ) ) {
			$request_body['chat_id'] = (string) $options['chat_id'];
		}

		if ( ! empty( $options['system_prompt'] ) ) {
			$request_body['system_prompt'] = $options['system_prompt'];
		}

		$clean_options = $options;
		unset( $clean_options['model'], $clean_options['chat_id'], $clean_options['system_prompt'] );
		$request_body['options'] = $clean_options;

		$response = $this->make_request( 'POST', '/api/ai/route-request', $request_body, [
			'Authorization' => 'Bearer ' . $site_token,
		]);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();

			if ( stripos( $error_message, 'token' ) !== false && stripos( $error_message, 'expired' ) !== false ) {
				$refresh_result = $this->refresh_token();

				if ( $refresh_result['success'] ) {
					$new_token = get_option( 'creator_site_token' );
					$response  = $this->make_request( 'POST', '/api/ai/route-request', $request_body, [
						'Authorization' => 'Bearer ' . $new_token,
					]);
				} else {
					return [
						'success' => false,
						'error'   => __( 'Token expired and refresh failed.', 'creator-core' ),
					];
				}
			}
		}

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];
		}

		return $response;
	}

	/**
	 * Refresh the site token
	 *
	 * @return array
	 */
	public function refresh_token(): array {
		$license_key = get_option( 'creator_license_key', '' );

		if ( empty( $license_key ) ) {
			return [
				'success' => false,
				'error'   => __( 'No license key found.', 'creator-core' ),
			];
		}

		$result = $this->validate_license( $license_key );

		if ( ! empty( $result['success'] ) && ! empty( $result['site_token'] ) ) {
			return [ 'success' => true ];
		}

		return [
			'success' => false,
			'error'   => $result['error'] ?? __( 'Failed to refresh token.', 'creator-core' ),
		];
	}

	/**
	 * Get site context for AI requests
	 *
	 * @return array
	 */
	private function get_site_context(): array {
		$plugin_detector = new PluginDetector();

		return [
			'site_info'    => [
				'site_title'        => get_bloginfo( 'name' ),
				'site_url'          => get_site_url(),
				'wordpress_version' => get_bloginfo( 'version' ),
				'php_version'       => PHP_VERSION,
			],
			'theme_info'   => [
				'theme_name'   => wp_get_theme()->get( 'Name' ),
				'theme_author' => wp_get_theme()->get( 'Author' ),
				'theme_uri'    => wp_get_theme()->get( 'ThemeURI' ),
			],
			'integrations' => $plugin_detector->get_all_integrations(),
			'current_user' => [
				'id'    => get_current_user_id(),
				'email' => wp_get_current_user()->user_email,
				'role'  => implode( ',', wp_get_current_user()->roles ),
			],
		];
	}

	/**
	 * Check connection status
	 *
	 * @return array
	 */
	public function check_connection(): array {
		$site_token        = get_option( 'creator_site_token' );
		$license_validated = get_option( 'creator_license_validated', false );

		return [
			'connected'  => ! empty( $site_token ) && $license_validated,
			'admin_mode' => $this->is_admin_license(),
			'proxy_url'  => $this->proxy_url,
			'site_token' => $site_token ? 'configured' : 'missing',
		];
	}

	/**
	 * Get usage statistics
	 *
	 * @return array
	 */
	public function get_usage_stats(): array {
		$site_token = get_option( 'creator_site_token' );

		if ( empty( $site_token ) ) {
			return [ 'error' => __( 'Site not authenticated', 'creator-core' ) ];
		}

		$response = $this->make_request( 'GET', '/api/usage/stats', [], [
			'Authorization' => 'Bearer ' . $site_token,
		]);

		if ( is_wp_error( $response ) ) {
			return [ 'error' => $response->get_error_message() ];
		}

		$response['admin_mode'] = $this->is_admin_license();
		return $response;
	}

	/**
	 * Send a generic request to the proxy API
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint API endpoint.
	 * @param array  $body     Request body data.
	 * @return array
	 */
	public function send_request( string $method, string $endpoint, array $body = [] ): array {
		$site_token = get_option( 'creator_site_token' );

		if ( empty( $site_token ) ) {
			return [
				'success' => false,
				'error'   => __( 'Site not authenticated.', 'creator-core' ),
			];
		}

		$response = $this->make_request( $method, $endpoint, $body, [
			'Authorization' => 'Bearer ' . $site_token,
		]);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];
		}

		if ( ! isset( $response['success'] ) ) {
			$response['success'] = true;
		}

		return $response;
	}

	/**
	 * Make HTTP request to proxy
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint API endpoint.
	 * @param array  $body     Request body.
	 * @param array  $headers  Additional headers.
	 * @return array|\WP_Error
	 */
	private function make_request( string $method, string $endpoint, array $body = [], array $headers = [] ) {
		$url        = rtrim( $this->proxy_url, '/' ) . $endpoint;
		$start_time = microtime( true );

		$default_headers = [
			'Content-Type'      => 'application/json',
			'Accept'            => 'application/json',
			'X-Creator-Version' => CREATOR_CORE_VERSION,
			'X-Site-URL'        => get_site_url(),
		];

		$args = [
			'method'  => $method,
			'timeout' => $this->timeout,
			'headers' => array_merge( $default_headers, $headers ),
		];

		if ( ! empty( $body ) && in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		$duration = round( ( microtime( true ) - $start_time ) * 1000 );

		if ( is_wp_error( $response ) ) {
			$this->log( 'proxy_network_error', [
				'endpoint'    => $endpoint,
				'method'      => $method,
				'error'       => $response->get_error_message(),
				'duration_ms' => $duration,
			]);
			return $response;
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data          = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->log( 'proxy_json_decode_error', [
				'endpoint'    => $endpoint,
				'status_code' => $status_code,
				'json_error'  => json_last_error_msg(),
				'duration_ms' => $duration,
			]);
		}

		if ( $status_code >= 400 ) {
			$error_message = $data['error'] ?? $data['message'] ?? __( 'Request failed', 'creator-core' );

			$this->log( 'proxy_http_error', [
				'endpoint'    => $endpoint,
				'status_code' => $status_code,
				'error'       => $error_message,
				'duration_ms' => $duration,
			]);

			return new \WP_Error( 'proxy_error', $error_message, [ 'status' => $status_code ] );
		}

		return $data ?? [];
	}
}
