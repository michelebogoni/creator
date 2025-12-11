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
    private int $timeout = 60;

    /**
     * Constructor
     */
    public function __construct() {
        $this->proxy_url = defined( 'CREATOR_PROXY_URL' ) ? CREATOR_PROXY_URL : 'https://creator-ai-proxy.firebaseapp.com';
    }

    /**
     * Send a chat message to the AI proxy
     *
     * @param string   $message  The user message.
     * @param array    $context  The WordPress context data.
     * @param int|null $chat_id  Optional chat ID for conversation continuity.
     * @return array|WP_Error The response data or error.
     */
    public function send_message( string $message, array $context, ?int $chat_id = null ) {
        $site_token = get_option( 'creator_site_token', '' );

        if ( empty( $site_token ) ) {
            return new WP_Error(
                'no_site_token',
                __( 'Site token not configured. Please validate your license.', 'creator-core' )
            );
        }

        $endpoint = $this->proxy_url . '/api/chat';

        $body = [
            'message'    => $message,
            'context'    => $context,
            'site_token' => $site_token,
            'chat_id'    => $chat_id,
            'model'      => get_option( 'creator_default_model', 'gemini' ),
        ];

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
        $body        = wp_remote_retrieve_body( $response );

        if ( $status_code !== 200 ) {
            return new WP_Error(
                'proxy_error',
                sprintf(
                    /* translators: %d: HTTP status code */
                    __( 'Proxy returned error status: %d', 'creator-core' ),
                    $status_code
                ),
                [ 'status' => $status_code, 'body' => $body ]
            );
        }

        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error(
                'invalid_json',
                __( 'Invalid JSON response from proxy.', 'creator-core' )
            );
        }

        return $data;
    }

    /**
     * Validate a license key with the proxy
     *
     * @param string $license_key The license key to validate.
     * @return array{valid: bool, message: string, site_token?: string}
     */
    public function validate_license( string $license_key ): array {
        $endpoint = $this->proxy_url . '/api/license/validate';

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

        if ( ! isset( $data['valid'] ) || ! $data['valid'] ) {
            return [
                'valid'   => false,
                'message' => $data['message'] ?? __( 'Invalid license key.', 'creator-core' ),
            ];
        }

        return [
            'valid'      => true,
            'message'    => $data['message'] ?? __( 'License validated successfully.', 'creator-core' ),
            'site_token' => $data['site_token'] ?? '',
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
