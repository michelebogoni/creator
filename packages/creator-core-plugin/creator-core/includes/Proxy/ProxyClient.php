<?php
/**
 * Proxy Client
 *
 * Gestisce la comunicazione con il servizio Firebase proxy.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Proxy;

defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * Class ProxyClient
 *
 * Handles communication with the Firebase AI proxy service
 */
class ProxyClient {

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
        $this->proxy_url = defined( 'CREATOR_PROXY_URL' )
            ? CREATOR_PROXY_URL
            : 'https://creator-ai-proxy.web.app';
    }

    /**
     * Send a chat message to the AI proxy
     *
     * Uses the /api/ai/route-request endpoint which returns structured JSON responses.
     *
     * @param string      $message              The user message.
     * @param array       $context              The WordPress context data.
     * @param array       $conversation_history Previous messages in the conversation.
     * @param array|null  $documentation        Optional plugin documentation.
     * @return array|WP_Error The response data or error.
     */
    public function send_message( string $message, array $context, array $conversation_history = [], ?array $documentation = null ) {
        $site_token = get_option( 'creator_site_token', '' );

        if ( empty( $site_token ) ) {
            return new WP_Error(
                'no_site_token',
                __( 'Site token not configured. Please validate your license.', 'creator-core' )
            );
        }

        $endpoint = $this->proxy_url . '/api/ai/route-request';

        // Build request body following the Firebase routeRequest format.
        $body = [
            'task_type' => 'CODE_GEN',
            'prompt'    => $message,
            'context'   => $context,
            'model'     => get_option( 'creator_default_model', 'gemini' ),
        ];

        // Debug: Log context being sent to Firebase.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Creator Debug] Context keys being sent: ' . implode( ', ', array_keys( $context ) ) );
            error_log( '[Creator Debug] WordPress version in context: ' . ( $context['wordpress']['version'] ?? 'NOT SET' ) );
            error_log( '[Creator Debug] PHP version in context: ' . ( $context['environment']['php_version'] ?? 'NOT SET' ) );
            error_log( '[Creator Debug] Theme in context: ' . ( $context['theme']['name'] ?? 'NOT SET' ) );
            error_log( '[Creator Debug] Plugins count in context: ' . ( is_array( $context['plugins'] ) ? count( $context['plugins'] ) : 'NOT SET' ) );
        }

        // Add conversation history if present.
        if ( ! empty( $conversation_history ) ) {
            $body['conversation_history'] = $conversation_history;
        }

        // Add documentation if present.
        if ( ! empty( $documentation ) ) {
            $body['documentation'] = $documentation;
        }

        $response = wp_remote_post(
            $endpoint,
            [
                'timeout'     => $this->timeout,
                'headers'     => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $site_token,
                    'X-Site-URL'    => get_site_url(),
                ],
                'body'        => wp_json_encode( $body ),
                'data_format' => 'body',
            ]
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body_raw    = wp_remote_retrieve_body( $response );

        if ( $status_code !== 200 ) {
            $error_data = json_decode( $body_raw, true );
            $error_msg  = $error_data['error'] ?? sprintf(
                /* translators: %d: HTTP status code */
                __( 'Proxy returned error status: %d', 'creator-core' ),
                $status_code
            );

            return new WP_Error(
                'proxy_error',
                $error_msg,
                [ 'status' => $status_code, 'body' => $body_raw ]
            );
        }

        $data = json_decode( $body_raw, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error(
                'invalid_json',
                __( 'Invalid JSON response from proxy.', 'creator-core' )
            );
        }

        return $data;
    }

    /**
     * Get plugin documentation from the proxy
     *
     * @param string      $plugin_slug The plugin slug.
     * @param string|null $version     Optional plugin version.
     * @return string|null The documentation or null if not found.
     */
    public function get_plugin_docs( string $plugin_slug, ?string $version = null ): ?string {
        $site_token = get_option( 'creator_site_token', '' );

        if ( empty( $site_token ) ) {
            return null;
        }

        $endpoint = $this->proxy_url . '/getPluginDocs?slug=' . urlencode( $plugin_slug );
        if ( $version ) {
            $endpoint .= '&version=' . urlencode( $version );
        }

        $response = wp_remote_get(
            $endpoint,
            [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $site_token,
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        return $data['documentation'] ?? null;
    }

    /**
     * Validate a license key with the proxy
     *
     * @param string $license_key The license key to validate.
     * @return array{valid: bool, message: string, site_token?: string}
     */
    public function validate_license( string $license_key ): array {
        $endpoint = $this->proxy_url . '/api/auth/validate-license';

        $body = [
            'license_key' => $license_key,
            'site_url'    => get_site_url(),
            'site_name'   => get_bloginfo( 'name' ),
            'wp_version'  => get_bloginfo( 'version' ),
            'php_version' => PHP_VERSION,
        ];

        $response = wp_remote_post(
            $endpoint,
            [
                'timeout'     => 30,
                'headers'     => [
                    'Content-Type' => 'application/json',
                ],
                'body'        => wp_json_encode( $body ),
                'data_format' => 'body',
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [
                'valid'   => false,
                'message' => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body, true );

        if ( $status_code !== 200 ) {
            return [
                'valid'   => false,
                'message' => $data['message'] ?? __( 'License validation failed.', 'creator-core' ),
            ];
        }

        // Firebase returns success: true/false, not valid: true/false
        if ( empty( $data['success'] ) ) {
            return [
                'valid'   => false,
                'message' => $data['error'] ?? $data['message'] ?? __( 'Invalid license key.', 'creator-core' ),
            ];
        }

        return [
            'valid'      => true,
            'message'    => $data['message'] ?? __( 'License validated successfully.', 'creator-core' ),
            'site_token' => $data['site_token'] ?? '',
            'expires_at' => $data['reset_date'] ?? '',
            'plan'       => $data['plan'] ?? 'standard',
        ];
    }

    /**
     * Check proxy service health
     *
     * @return array{healthy: bool, latency_ms: int, message: string}
     */
    public function health_check(): array {
        $endpoint   = $this->proxy_url . '/api/health';
        $start_time = microtime( true );

        $response = wp_remote_get(
            $endpoint,
            [
                'timeout' => 10,
            ]
        );

        $latency = (int) ( ( microtime( true ) - $start_time ) * 1000 );

        if ( is_wp_error( $response ) ) {
            return [
                'healthy'    => false,
                'latency_ms' => $latency,
                'message'    => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( $status_code !== 200 ) {
            return [
                'healthy'    => false,
                'latency_ms' => $latency,
                'message'    => sprintf(
                    /* translators: %d: HTTP status code */
                    __( 'Proxy returned status %d', 'creator-core' ),
                    $status_code
                ),
            ];
        }

        return [
            'healthy'    => true,
            'latency_ms' => $latency,
            'message'    => __( 'Proxy service is healthy.', 'creator-core' ),
        ];
    }

    /**
     * Get proxy URL
     *
     * @return string
     */
    public function get_proxy_url(): string {
        return $this->proxy_url;
    }

    /**
     * Set request timeout
     *
     * @param int $timeout Timeout in seconds.
     * @return void
     */
    public function set_timeout( int $timeout ): void {
        $this->timeout = $timeout;
    }

    /**
     * Get request timeout
     *
     * @return int
     */
    public function get_timeout(): int {
        return $this->timeout;
    }
}
