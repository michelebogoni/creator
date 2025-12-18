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
     * @param array       $files                Optional file attachments.
     * @return array|WP_Error The response data or error.
     */
    public function send_message( string $message, array $context, array $conversation_history = [], ?array $documentation = null, array $files = [] ) {
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

        // Add file attachments if present.
        if ( ! empty( $files ) ) {
            $body['files'] = $files;
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

        // Debug: Log what Firebase received (from response headers).
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $debug_context_keys = wp_remote_retrieve_header( $response, 'x-debug-context-keys' );
            $debug_wp_version   = wp_remote_retrieve_header( $response, 'x-debug-wp-version' );
            $debug_php_version  = wp_remote_retrieve_header( $response, 'x-debug-php-version' );
            $debug_theme        = wp_remote_retrieve_header( $response, 'x-debug-theme' );

            error_log( '[Creator Debug] === FIREBASE RECEIVED ===' );
            error_log( '[Creator Debug] Context keys: ' . ( $debug_context_keys ?: 'HEADER NOT PRESENT' ) );
            error_log( '[Creator Debug] WP version: ' . ( $debug_wp_version ?: 'HEADER NOT PRESENT' ) );
            error_log( '[Creator Debug] PHP version: ' . ( $debug_php_version ?: 'HEADER NOT PRESENT' ) );
            error_log( '[Creator Debug] Theme: ' . ( $debug_theme ?: 'HEADER NOT PRESENT' ) );
            error_log( '[Creator Debug] === END FIREBASE RECEIVED ===' );
        }

        if ( $status_code !== 200 ) {
            $error_data = json_decode( $body_raw, true );
            $error_msg  = $error_data['error'] ?? sprintf(
                /* translators: %d: HTTP status code */
                __( 'Proxy returned error status: %d', 'creator-core' ),
                $status_code
            );

            // Check if token expired - try to refresh automatically.
            if ( $status_code === 401 || stripos( $error_msg, 'token' ) !== false && stripos( $error_msg, 'expir' ) !== false ) {
                $refresh_result = $this->refresh_token();
                if ( $refresh_result ) {
                    // Retry the request with the new token.
                    return $this->send_message( $message, $context, $conversation_history, $documentation );
                }
            }

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
     * Uses the /api/plugin-docs/research endpoint which:
     * 1. Checks the cache first
     * 2. Uses AI to research if not cached
     * 3. Saves to cache for future use
     *
     * @param string      $plugin_slug The plugin slug.
     * @param string|null $version     Optional plugin version.
     * @param string|null $plugin_name Optional plugin name for better research.
     * @return array|null The documentation data or null if not found.
     */
    public function get_plugin_docs( string $plugin_slug, ?string $version = null, ?string $plugin_name = null ): ?array {
        $site_token = get_option( 'creator_site_token', '' );

        if ( empty( $site_token ) ) {
            $this->log_plugin_docs_debug( $plugin_slug, 'No site token configured' );
            return null;
        }

        // Use the research endpoint which handles cache + AI research.
        $endpoint = $this->proxy_url . '/api/plugin-docs/research';

        $body = [
            'plugin_slug'    => $plugin_slug,
            'plugin_version' => $version ?? 'latest',
        ];

        if ( $plugin_name ) {
            $body['plugin_name'] = $plugin_name;
        }

        $this->log_plugin_docs_debug( $plugin_slug, 'Requesting docs', [
            'endpoint' => $endpoint,
            'body'     => $body,
        ] );

        $response = wp_remote_post(
            $endpoint,
            [
                'timeout'     => 60, // Longer timeout for AI research.
                'headers'     => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $site_token,
                ],
                'body'        => wp_json_encode( $body ),
                'data_format' => 'body',
            ]
        );

        if ( is_wp_error( $response ) ) {
            $this->log_plugin_docs_debug( $plugin_slug, 'HTTP error: ' . $response->get_error_message() );
            return null;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body_raw    = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body_raw, true );

        $this->log_plugin_docs_debug( $plugin_slug, 'Response received', [
            'status_code'        => $status_code,
            'body_length'        => strlen( $body_raw ),
            'body_preview'       => substr( $body_raw, 0, 500 ),
            'parsed_success'     => $data['success'] ?? 'NOT_SET',
            'parsed_data_exists' => isset( $data['data'] ) ? 'YES' : 'NO',
        ] );

        if ( $status_code !== 200 && $status_code !== 201 ) {
            $this->log_plugin_docs_debug( $plugin_slug, 'Non-success status code: ' . $status_code );
            return null;
        }

        if ( empty( $data['success'] ) ) {
            $this->log_plugin_docs_debug( $plugin_slug, 'Response success=false', [
                'error' => $data['error'] ?? 'No error message',
            ] );
            return null;
        }

        if ( empty( $data['data'] ) ) {
            $this->log_plugin_docs_debug( $plugin_slug, 'Response data is empty' );
            return null;
        }

        $this->log_plugin_docs_debug( $plugin_slug, 'Documentation fetched successfully', [
            'docs_url'        => $data['data']['docs_url'] ?? 'N/A',
            'functions_count' => count( $data['data']['main_functions'] ?? [] ),
        ] );

        // Return the full documentation data.
        return $data['data'] ?? null;
    }

    /**
     * Log plugin docs debug information
     *
     * Writes to both error_log and a dedicated plugin-docs debug file.
     *
     * @param string $plugin_slug Plugin slug.
     * @param string $message     Debug message.
     * @param array  $data        Additional data.
     * @return void
     */
    private function log_plugin_docs_debug( string $plugin_slug, string $message, array $data = [] ): void {
        $log_entry = sprintf(
            '[Creator Plugin Docs] [%s] %s%s',
            $plugin_slug,
            $message,
            $data ? ' | ' . wp_json_encode( $data ) : ''
        );

        // Always log to error_log.
        error_log( $log_entry );

        // Also write to dedicated debug file.
        $upload_dir = wp_upload_dir();
        $debug_dir  = $upload_dir['basedir'] . '/creator-debug';
        $debug_file = $debug_dir . '/plugin-docs-debug.log';

        if ( ! file_exists( $debug_dir ) ) {
            wp_mkdir_p( $debug_dir );
        }

        $timestamp = current_time( 'mysql' );
        $line      = "[{$timestamp}] {$log_entry}\n";
        file_put_contents( $debug_file, $line, FILE_APPEND | LOCK_EX );
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
            'valid'        => true,
            'message'      => $data['message'] ?? __( 'License validated successfully.', 'creator-core' ),
            'site_token'   => $data['site_token'] ?? '',
            'expires_at'   => $data['reset_date'] ?? '',
            'plan'         => $data['plan'] ?? 'standard',
            'tokens_used'  => $data['tokens_used'] ?? 0,
            'tokens_limit' => $data['tokens_limit'] ?? 50000,
        ];
    }

    /**
     * Attempt to refresh an expired token
     *
     * Uses the stored license key to get a new site_token.
     *
     * @return bool True if token was refreshed successfully.
     */
    public function refresh_token(): bool {
        // Prevent infinite recursion.
        static $is_refreshing = false;
        if ( $is_refreshing ) {
            return false;
        }
        $is_refreshing = true;

        $license_key = get_option( 'creator_license_key', '' );

        if ( empty( $license_key ) ) {
            $is_refreshing = false;
            return false;
        }

        // Re-validate license to get new token.
        $result = $this->validate_license( $license_key );

        $is_refreshing = false;

        if ( $result['valid'] && ! empty( $result['site_token'] ) ) {
            update_option( 'creator_site_token', $result['site_token'] );

            // Also update license status.
            update_option( 'creator_license_status', [
                'valid'      => true,
                'plan'       => $result['plan'] ?? 'standard',
                'expires_at' => $result['expires_at'] ?? '',
            ] );

            return true;
        }

        return false;
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
