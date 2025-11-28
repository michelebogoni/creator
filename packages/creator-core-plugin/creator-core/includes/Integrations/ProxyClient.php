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
     * Check if mock mode is enabled
     *
     * @return bool
     */
    private function is_mock_mode(): bool {
        return defined( 'CREATOR_MOCK_MODE' ) && CREATOR_MOCK_MODE === true;
    }

    /**
     * Validate license key
     *
     * @param string $license_key License key to validate.
     * @return array
     */
    public function validate_license( string $license_key ): array {
        if ( $this->is_mock_mode() ) {
            return $this->mock_validate_license( $license_key );
        }

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
            set_transient( 'creator_license_status', $response, DAY_IN_SECONDS );
        }

        return $response;
    }

    /**
     * Mock license validation for development
     *
     * @param string $license_key License key.
     * @return array
     */
    private function mock_validate_license( string $license_key ): array {
        // Simulate API delay
        usleep( 500000 ); // 0.5 seconds

        // Accept any key starting with CREATOR-
        if ( strpos( $license_key, 'CREATOR-' ) === 0 || $license_key === 'test' ) {
            $response = [
                'success'    => true,
                'license_id' => 'CREATOR-2025-MOCK-12345',
                'plan'       => 'pro',
                'features'   => [ 'elementor', 'acf', 'rank_math', 'woocommerce' ],
                'expires_at' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
                'site_token' => 'mock_site_token_' . wp_generate_password( 16, false ),
                'usage'      => [
                    'tokens_used'  => 12500,
                    'tokens_limit' => 100000,
                    'requests'     => 45,
                ],
            ];

            update_option( 'creator_site_token', $response['site_token'] );
            update_option( 'creator_license_validated', true );
            set_transient( 'creator_license_status', $response, DAY_IN_SECONDS );

            return $response;
        }

        return [
            'success' => false,
            'error'   => __( 'Invalid license key format. Use CREATOR-XXXX-XXXX format.', 'creator-core' ),
        ];
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
        if ( $this->is_mock_mode() ) {
            return $this->mock_ai_response( $prompt, $task_type, $options );
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
     * Mock AI response for development
     *
     * @param string $prompt    The prompt.
     * @param string $task_type Task type.
     * @param array  $options   Additional options including user_message.
     * @return array
     */
    private function mock_ai_response( string $prompt, string $task_type, array $options = [] ): array {
        // Simulate API delay
        usleep( 1000000 ); // 1 second

        // Use original user message for intent detection if available
        $user_message  = $options['user_message'] ?? $prompt;
        $response_text = $this->generate_mock_response( $user_message, $task_type );

        return [
            'success'  => true,
            'response' => $response_text,
            'provider' => 'mock',
            'model'    => 'mock-gpt-4',
            'usage'    => [
                'prompt_tokens'     => strlen( $prompt ) / 4,
                'completion_tokens' => strlen( $response_text ) / 4,
                'total_tokens'      => ( strlen( $prompt ) + strlen( $response_text ) ) / 4,
            ],
            'metadata' => [
                'mock_mode'    => true,
                'processed_at' => gmdate( 'c' ),
            ],
        ];
    }

    /**
     * Generate mock response based on user message
     *
     * @param string $user_message The user's message (not the full prompt).
     * @param string $task_type    Task type.
     * @return string
     */
    private function generate_mock_response( string $user_message, string $task_type ): string {
        $message_lower = strtolower( $user_message );

        // Handle greetings first
        if ( preg_match( '/^(ciao|hello|hi|hey|buongiorno|buonasera|salve)\b/i', $message_lower ) ) {
            return wp_json_encode( [
                'intent'     => 'greeting',
                'confidence' => 0.95,
                'actions'    => [],
                'message'    => 'Ciao! Sono Creator, il tuo assistente AI per WordPress. Posso aiutarti a creare pagine, post, gestire Elementor, ACF e molto altro. Come posso aiutarti oggi?',
            ]);
        }

        // Handle identity questions
        if ( preg_match( '/(chi sei|who are you|cosa sei|what are you)/i', $message_lower ) ) {
            return wp_json_encode( [
                'intent'     => 'identity',
                'confidence' => 0.95,
                'actions'    => [],
                'message'    => 'Sono Creator, un assistente AI progettato per aiutarti a gestire il tuo sito WordPress. Posso creare e modificare pagine, post, gestire plugin come Elementor, ACF, RankMath e WooCommerce. Sono qui per semplificare il tuo lavoro!',
            ]);
        }

        // Handle platform/technology questions
        if ( preg_match( '/(piattaforma|platform|basi|based|tecnologia|technology|gemini|chatgpt|claude|openai|gpt)/i', $message_lower ) ) {
            return wp_json_encode( [
                'intent'     => 'about_platform',
                'confidence' => 0.95,
                'actions'    => [],
                'message'    => 'Sono basato su tecnologie AI avanzate e comunico attraverso un proxy sicuro che gestisce le richieste al modello di linguaggio. Il mio obiettivo è aiutarti con WordPress in modo semplice e naturale.',
            ]);
        }

        // Handle help/capability questions
        if ( preg_match( '/(cosa puoi|what can you|aiutarmi|help me|funzionalit|features|capabilities)/i', $message_lower ) ) {
            return wp_json_encode( [
                'intent'     => 'capabilities',
                'confidence' => 0.95,
                'actions'    => [],
                'message'    => 'Posso aiutarti con molte attività WordPress: creare e modificare pagine e post, gestire contenuti Elementor, configurare campi ACF, ottimizzare SEO con RankMath, gestire prodotti WooCommerce, e molto altro. Dimmi cosa ti serve!',
            ]);
        }

        // Detect action intents - must be explicit requests to create/modify
        // Check for create page intent
        if ( preg_match( '/(crea|create|genera|generate|fai|make).*(pagin|page)/i', $message_lower ) ||
             preg_match( '/(pagin|page).*(crea|create|genera|generate|fai|make)/i', $message_lower ) ) {
            return wp_json_encode( [
                'intent'     => 'create_page',
                'confidence' => 0.95,
                'actions'    => [
                    [
                        'type'   => 'create_page',
                        'params' => [
                            'title'   => 'New Page',
                            'content' => 'Page content will be generated here.',
                            'status'  => 'draft',
                        ],
                    ],
                ],
                'message' => 'Sto per creare una nuova pagina. Vuoi procedere?',
            ]);
        }

        // Check for create post intent
        if ( preg_match( '/(crea|create|genera|generate|scrivi|write).*(post|articol|article)/i', $message_lower ) ||
             preg_match( '/(post|articol|article).*(crea|create|genera|generate|scrivi|write)/i', $message_lower ) ) {
            return wp_json_encode( [
                'intent'     => 'create_post',
                'confidence' => 0.92,
                'actions'    => [
                    [
                        'type'   => 'create_post',
                        'params' => [
                            'title'   => 'New Post',
                            'content' => 'Post content will be generated here.',
                            'status'  => 'draft',
                        ],
                    ],
                ],
                'message' => 'Sto per creare un nuovo post. Vuoi procedere?',
            ]);
        }

        // Default conversational response for anything else
        return wp_json_encode( [
            'intent'     => 'conversation',
            'confidence' => 0.7,
            'actions'    => [],
            'message'    => 'Capisco. Sono qui per aiutarti con il tuo sito WordPress. Puoi chiedermi di creare pagine, post, o gestire altri aspetti del tuo sito. Cosa vorresti fare?',
        ]);
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
        if ( $this->is_mock_mode() ) {
            return [
                'connected'   => true,
                'mock_mode'   => true,
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
            'mock_mode'  => false,
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
        if ( $this->is_mock_mode() ) {
            return [
                'tokens_used'     => 12500,
                'tokens_limit'    => 100000,
                'tokens_remaining' => 87500,
                'requests_today'  => 45,
                'cost_estimate'   => '$1.25',
                'mock_mode'       => true,
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
        delete_transient( 'creator_license_status' );
    }
}
