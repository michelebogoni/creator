<?php
/**
 * Proxy Client
 *
 * @package CreatorCore
 */

namespace CreatorCore\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class ProxyClient
 *
 * Handles communication with the Firebase Proxy API
 */
class ProxyClient {

	/**
	 * Admin license key for unlimited access
	 * This license has unlimited tokens and 100 year expiration in Firestore
	 *
	 * @var string
	 */
	public const ADMIN_LICENSE_KEY = 'CREATOR-2024-ADMIN-ADMIN';

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
	private int $timeout = 30;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->proxy_url = get_option( 'creator_proxy_url', CREATOR_PROXY_URL );
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
		// All licenses go through the proxy - no exceptions
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
	 * @param string $task_type Task type (TEXT_GEN, CODE_GEN, ANALYSIS, etc).
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

		$response = $this->make_request( 'POST', '/api/ai/route-request', [
			'task_type' => $task_type,
			'prompt'    => $prompt,
			'context'   => $context,
			'options'   => $options,
		], [
			'Authorization' => 'Bearer ' . $site_token,
		]);

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];
		}

		return $response;
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
		$response = $this->make_request( 'GET', '/api/health' );

		if ( is_wp_error( $response ) ) {
			return [
				'connected' => false,
				'error'     => $response->get_error_message(),
				'proxy_url' => $this->proxy_url,
			];
		}

		return [
			'connected'   => true,
			'admin_mode'  => $this->is_admin_license(),
			'proxy_url'   => $this->proxy_url,
			'site_token'  => get_option( 'creator_site_token' ) ? 'configured' : 'missing',
			'status'      => $response,
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
			return [
				'error' => __( 'Site not authenticated', 'creator-core' ),
			];
		}

		$response = $this->make_request( 'GET', '/api/usage/stats', [], [
			'Authorization' => 'Bearer ' . $site_token,
		]);

		if ( is_wp_error( $response ) ) {
			return [
				'error' => $response->get_error_message(),
			];
		}

		// Add admin_mode flag for display purposes
		$response['admin_mode'] = $this->is_admin_license();

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
		$url = rtrim( $this->proxy_url, '/' ) . $endpoint;

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

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( $status_code >= 400 ) {
			$error_message = $data['error'] ?? $data['message'] ?? __( 'Request failed', 'creator-core' );
			return new \WP_Error( 'proxy_error', $error_message, [ 'status' => $status_code ] );
		}

		return $data ?? [];
	}
}
