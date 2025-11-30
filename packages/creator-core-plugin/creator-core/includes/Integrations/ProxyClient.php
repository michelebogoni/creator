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
     * This license bypasses the proxy and grants unlimited usage
     *
     * @var string
     */
    private const ADMIN_LICENSE_KEY = 'CREATOR-ADMIN-7f3d9c2e1a8b4f6d';

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
    private function is_admin_license(): bool {
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
        // Check for admin license (unlimited access)
        if ( $license_key === self::ADMIN_LICENSE_KEY ) {
            $response = [
                'success'    => true,
                'license_id' => 'ADMIN-LICENSE-UNLIMITED',
                'plan'       => 'admin',
                'features'   => [ 'elementor', 'acf', 'rank_math', 'woocommerce', 'development', 'unlimited' ],
                'expires_at' => gmdate( 'Y-m-d', strtotime( '+100 years' ) ),
                'site_token' => 'admin_site_token_' . wp_generate_password( 32, false ),
                'usage'      => [
                    'tokens_used'  => 0,
                    'tokens_limit' => PHP_INT_MAX,
                    'requests'     => 0,
                ],
                'admin_mode' => true,
            ];

            update_option( 'creator_site_token', $response['site_token'] );
            update_option( 'creator_license_validated', true );
            update_option( 'creator_license_key', $license_key );
            set_transient( 'creator_license_status', $response, YEAR_IN_SECONDS );

            return $response;
        }

        // Regular license validation through proxy
        $response = $this->make_request( 'POST', '/api/auth/validate-license', [
            'license_key' => $license_key,
            'site_url'    => get_site_url(),
            'site_name'   => get_bloginfo( 'name' ),
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
        // Check for admin license with direct API key
        if ( $this->is_admin_license() ) {
            $openai_api_key = get_option( 'creator_openai_api_key', '' );
            if ( ! empty( $openai_api_key ) ) {
                return $this->send_to_openai_direct( $prompt, $task_type, $options, $openai_api_key );
            }
            return [
                'success' => false,
                'error'   => __( 'Admin license requires an OpenAI API key. Please configure it in Settings.', 'creator-core' ),
            ];
        }

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
     * Send request directly to OpenAI (for admin license)
     *
     * @param string $prompt    The prompt to send.
     * @param string $task_type Task type.
     * @param array  $options   Additional options.
     * @param string $api_key   OpenAI API key.
     * @return array
     */
    private function send_to_openai_direct( string $prompt, string $task_type, array $options, string $api_key ): array {
        $context     = $this->get_site_context();
        $system_prompt = $this->build_system_prompt( $context, $task_type );

        $messages = [
            [
                'role'    => 'system',
                'content' => $system_prompt,
            ],
            [
                'role'    => 'user',
                'content' => $prompt,
            ],
        ];

        // Add conversation history if present
        if ( ! empty( $options['history'] ) ) {
            $history_messages = [];
            foreach ( $options['history'] as $msg ) {
                $history_messages[] = [
                    'role'    => $msg['role'] === 'user' ? 'user' : 'assistant',
                    'content' => $msg['content'],
                ];
            }
            // Insert history after system message
            array_splice( $messages, 1, 0, $history_messages );
        }

        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( [
                'model'       => 'gpt-4o',
                'messages'    => $messages,
                'temperature' => 0.7,
                'max_tokens'  => 4096,
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body, true );

        if ( $status_code >= 400 ) {
            $error_message = $data['error']['message'] ?? __( 'OpenAI API request failed', 'creator-core' );
            return [
                'success' => false,
                'error'   => $error_message,
            ];
        }

        $ai_response = $data['choices'][0]['message']['content'] ?? '';
        $actions     = $this->extract_actions( $ai_response );

        return [
            'success'  => true,
            'response' => $ai_response,
            'actions'  => $actions,
            'usage'    => $data['usage'] ?? [],
        ];
    }

    /**
     * Build system prompt for AI
     *
     * @param array  $context   Site context.
     * @param string $task_type Task type.
     * @return string
     */
    private function build_system_prompt( array $context, string $task_type ): string {
        $site_name = $context['site_info']['site_title'] ?? 'WordPress Site';
        $site_url  = $context['site_info']['site_url'] ?? '';

        $prompt = "You are Creator AI, an intelligent WordPress assistant for the site '{$site_name}' ({$site_url}).\n\n";
        $prompt .= "Your role is to help users manage their WordPress website by:\n";
        $prompt .= "- Creating and editing pages, posts, and content\n";
        $prompt .= "- Managing plugins and themes\n";
        $prompt .= "- Configuring settings\n";
        $prompt .= "- Providing guidance on WordPress best practices\n\n";

        $prompt .= "Available integrations:\n";
        foreach ( $context['integrations'] as $integration => $active ) {
            if ( $active ) {
                $prompt .= "- {$integration}\n";
            }
        }

        $prompt .= "\nWhen you need to perform an action, respond with a clear description of what you'll do, ";
        $prompt .= "then include an ACTION block in this format:\n";
        $prompt .= "```action\n{\"type\": \"action_type\", \"params\": {...}}\n```\n\n";

        $prompt .= "Available action types:\n";
        $prompt .= "- create_page: Create a new page (params: title, content, status)\n";
        $prompt .= "- create_post: Create a new post (params: title, content, status, categories)\n";
        $prompt .= "- edit_page: Edit an existing page (params: page_id, title, content)\n";
        $prompt .= "- edit_post: Edit an existing post (params: post_id, title, content)\n";
        $prompt .= "- install_plugin: Install a plugin (params: slug)\n";
        $prompt .= "- activate_plugin: Activate a plugin (params: slug)\n";
        $prompt .= "- update_option: Update a WordPress option (params: option_name, value)\n\n";

        $prompt .= "Always be helpful, concise, and proactive. When a user confirms an action (e.g., 'yes', 'proceed', 'ok'), ";
        $prompt .= "execute the action immediately without asking again.";

        return $prompt;
    }

    /**
     * Extract actions from AI response
     *
     * @param string $response AI response text.
     * @return array
     */
    private function extract_actions( string $response ): array {
        $actions = [];

        // Look for action blocks in the response
        if ( preg_match_all( '/```action\s*([\s\S]*?)```/m', $response, $matches ) ) {
            foreach ( $matches[1] as $action_json ) {
                $action = json_decode( trim( $action_json ), true );
                if ( $action && isset( $action['type'] ) ) {
                    $action['id']     = wp_generate_uuid4();
                    $action['status'] = 'pending';
                    $actions[]        = $action;
                }
            }
        }

        return $actions;
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
        // Check if using admin license
        if ( $this->is_admin_license() ) {
            return [
                'connected'   => true,
                'admin_mode'  => true,
                'proxy_url'   => $this->proxy_url,
                'site_token'  => get_option( 'creator_site_token' ) ? 'configured' : 'missing',
                'license'     => get_transient( 'creator_license_status' ) ?: null,
            ];
        }

        $response = $this->make_request( 'GET', '/api/health' );

        if ( is_wp_error( $response ) ) {
            return [
                'connected' => false,
                'error'     => $response->get_error_message(),
                'proxy_url' => $this->proxy_url,
            ];
        }

        return [
            'connected'  => true,
            'admin_mode' => false,
            'proxy_url'  => $this->proxy_url,
            'site_token' => get_option( 'creator_site_token' ) ? 'configured' : 'missing',
            'status'     => $response,
        ];
    }

    /**
     * Get usage statistics
     *
     * @return array
     */
    public function get_usage_stats(): array {
        // Admin license has unlimited usage
        if ( $this->is_admin_license() ) {
            return [
                'tokens_used'      => 0,
                'tokens_limit'     => PHP_INT_MAX,
                'tokens_remaining' => PHP_INT_MAX,
                'requests_today'   => 0,
                'cost_estimate'    => '$0.00',
                'admin_mode'       => true,
                'unlimited'        => true,
            ];
        }

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
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'X-Creator-Version' => CREATOR_CORE_VERSION,
            'X-Site-URL'   => get_site_url(),
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

    /**
     * Set proxy URL
     *
     * @param string $url Proxy URL.
     * @return void
     */
    public function set_proxy_url( string $url ): void {
        $this->proxy_url = $url;
        update_option( 'creator_proxy_url', $url );
    }

    /**
     * Get current proxy URL
     *
     * @return string
     */
    public function get_proxy_url(): string {
        return $this->proxy_url;
    }

    /**
     * Clear stored authentication
     *
     * @return void
     */
    public function clear_authentication(): void {
        delete_option( 'creator_site_token' );
        delete_option( 'creator_license_validated' );
        delete_option( 'creator_license_key' );
        delete_transient( 'creator_license_status' );
    }
}
