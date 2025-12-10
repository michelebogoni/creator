<?php
/**
 * Proxy Client - Communication with Firebase
 *
 * Phase 2: Basic chat loop - sends messages to /route-request
 *
 * @package CreatorCore
 */

namespace CreatorCore\Proxy;

defined( 'ABSPATH' ) || exit;

/**
 * Class ProxyClient
 *
 * Handles communication with Firebase Proxy API
 */
class ProxyClient {

	/**
	 * Proxy base URL
	 *
	 * @var string
	 */
	private string $base_url;

	/**
	 * Site token for authentication
	 *
	 * @var string
	 */
	private string $token;

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
		$this->base_url = get_option( 'creator_proxy_url', CREATOR_PROXY_URL );
		$this->token    = get_option( 'creator_site_token', '' );
	}

	/**
	 * Send message to AI via Firebase
	 *
	 * @param array $data Message data with prompt, context, and history.
	 * @return array Response with success status and content.
	 */
	public function send_message( array $data ): array {
		return $this->request( 'POST', '/api/ai/route-request', [
			'task_type'            => 'TEXT_GEN',
			'prompt'               => $data['prompt'] ?? '',
			'context'              => $data['context'] ?? [],
			'conversation_history' => $data['conversation_history'] ?? [],
			'system_prompt'        => $data['system_prompt'] ?? null,
			'model'                => $data['model'] ?? 'gemini',
		] );
	}

	/**
	 * Get plugin documentation
	 *
	 * @param string      $slug    Plugin slug.
	 * @param string|null $version Plugin version.
	 * @return string|null Documentation content or null.
	 */
	public function get_plugin_docs( string $slug, ?string $version = null ): ?string {
		$endpoint = '/api/plugin-docs/' . $slug;
		if ( $version ) {
			$endpoint .= '?version=' . urlencode( $version );
		}

		$response = $this->request( 'GET', $endpoint );

		return $response['documentation'] ?? null;
	}

	/**
	 * Validate license key
	 *
	 * @param string $license_key License key to validate.
	 * @return array Response with success status.
	 */
	public function validate_license( string $license_key ): array {
		$response = $this->request( 'POST', '/api/auth/validate-license', [
			'license_key' => $license_key,
			'site_url'    => get_site_url(),
		] );

		// Save token on success
		if ( ! empty( $response['success'] ) && ! empty( $response['site_token'] ) ) {
			update_option( 'creator_site_token', $response['site_token'] );
			update_option( 'creator_license_validated', true );
			update_option( 'creator_license_key', $license_key );
			$this->token = $response['site_token'];
		}

		return $response;
	}

	/**
	 * Make HTTP request to proxy
	 *
	 * @param string $method   HTTP method (GET, POST).
	 * @param string $endpoint API endpoint.
	 * @param array  $data     Request body data.
	 * @return array Response data.
	 */
	private function request( string $method, string $endpoint, array $data = [] ): array {
		$url = rtrim( $this->base_url, '/' ) . $endpoint;

		$args = [
			'method'  => $method,
			'timeout' => $this->timeout,
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
		];

		// Add Authorization header if we have a token
		if ( ! empty( $this->token ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $this->token;
		}

		// Add body for POST requests
		if ( $method === 'POST' && ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		// Handle WP_Error
		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'error'   => $response->get_error_message(),
			];
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $body, true );

		// Handle HTTP errors
		if ( $status_code >= 400 ) {
			return [
				'success'     => false,
				'error'       => $decoded['error'] ?? 'HTTP Error ' . $status_code,
				'status_code' => $status_code,
			];
		}

		// Add success flag if not present
		if ( is_array( $decoded ) && ! isset( $decoded['success'] ) ) {
			$decoded['success'] = true;
		}

		return $decoded ?? [ 'success' => false, 'error' => 'Invalid response' ];
	}
}
