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
     * @param array  $options   Additional options including user_message, pending_actions, conversation.
     * @return array
     */
    private function mock_ai_response( string $prompt, string $task_type, array $options = [] ): array {
        // Simulate API delay
        usleep( 1000000 ); // 1 second

        // Use original user message for intent detection if available
        $user_message    = $options['user_message'] ?? $prompt;
        $pending_actions = $options['pending_actions'] ?? [];
        $conversation    = $options['conversation'] ?? [];

        $response_text = $this->generate_mock_response( $user_message, $task_type, $pending_actions, $conversation );

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
     * @param string $user_message    The user's message (not the full prompt).
     * @param string $task_type       Task type.
     * @param array  $pending_actions Pending actions from previous messages.
     * @param array  $conversation    Conversation history for context.
     * @return string
     */
    private function generate_mock_response( string $user_message, string $task_type, array $pending_actions = [], array $conversation = [] ): string {
        $message_lower = strtolower( $user_message );

        // Check for confirmation patterns FIRST when there are pending actions
        if ( ! empty( $pending_actions ) && $this->is_confirmation_message( $message_lower ) ) {
            return $this->generate_action_execution_response( $pending_actions, $conversation );
        }

        // Check for rejection/cancellation patterns when there are pending actions
        if ( ! empty( $pending_actions ) && $this->is_rejection_message( $message_lower ) ) {
            return wp_json_encode( [
                'intent'     => 'cancellation',
                'confidence' => 0.95,
                'actions'    => [],
                'message'    => 'Va bene, ho annullato l\'operazione. Come posso aiutarti?',
            ]);
        }

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
                'message'    => 'Sono Creator, un agente di sviluppo AI per WordPress. Posso creare plugin personalizzati su richiesta e installarli automaticamente, analizzare il codice per trovare e risolvere bug, accedere ai file del sito e al database, debuggare errori, e gestire contenuti con Elementor, ACF, WooCommerce e altri plugin. Sono come un assistente sviluppatore integrato direttamente nel tuo WordPress!',
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
                'message'    => "Sono un agente di sviluppo WordPress completo. Posso:\n\n**Sviluppo Plugin:**\n- Creare plugin personalizzati su richiesta\n- Installare e attivare plugin automaticamente\n- Aggiungere file e funzionalità ai plugin\n\n**Analisi e Debug:**\n- Analizzare codice PHP per errori e vulnerabilità\n- Leggere i log di debug di WordPress\n- Diagnosticare e risolvere problemi\n\n**Gestione File:**\n- Leggere e modificare file del tema e dei plugin\n- Cercare codice nel sito\n- Creare backup automatici\n\n**Database:**\n- Eseguire query di lettura\n- Gestire tabelle personalizzate\n- Analizzare la struttura del database\n\n**Contenuti WordPress:**\n- Creare pagine, post, prodotti WooCommerce\n- Gestire Elementor, ACF, RankMath\n- Ottimizzare SEO\n\nDimmi cosa ti serve e lo realizzo!",
            ]);
        }

        // Handle plugin creation requests
        if ( preg_match( '/(crea|create|genera|generate|fai|make|sviluppa|develop).*(plugin)/i', $message_lower ) ) {
            return wp_json_encode( [
                'intent'     => 'create_plugin',
                'confidence' => 0.95,
                'actions'    => [
                    [
                        'type'   => 'create_plugin',
                        'params' => [
                            'name'        => 'Custom Plugin',
                            'description' => 'Plugin personalizzato creato da Creator',
                            'features'    => [ 'admin_menu', 'settings' ],
                            'activate'    => true,
                        ],
                    ],
                ],
                'message' => 'Sto per creare un nuovo plugin WordPress. Descrivimi le funzionalità che desideri e lo creerò per te!',
            ]);
        }

        // Handle code analysis requests
        if ( preg_match( '/(analizza|analyze|controlla|check|verifica|verify).*(codice|code|plugin|tema|theme)/i', $message_lower ) ) {
            return wp_json_encode( [
                'intent'     => 'analyze_code',
                'confidence' => 0.90,
                'actions'    => [],
                'message'    => 'Posso analizzare il codice per te. Dimmi quale plugin o tema vuoi che analizzi, oppure forniscimi il percorso del file da controllare.',
            ]);
        }

        // Handle debug requests
        if ( preg_match( '/(debug|errore|error|problema|problem|bug|fix|risolvi|solve)/i', $message_lower ) ) {
            return wp_json_encode( [
                'intent'     => 'debug',
                'confidence' => 0.85,
                'actions'    => [],
                'message'    => 'Sono pronto ad aiutarti con il debug! Descrivimi l\'errore che stai riscontrando, oppure posso controllare il log di debug di WordPress per te.',
            ]);
        }

        // Handle file operations
        if ( preg_match( '/(leggi|read|mostra|show|apri|open).*(file|codice|code)/i', $message_lower ) ) {
            return wp_json_encode( [
                'intent'     => 'read_file',
                'confidence' => 0.85,
                'actions'    => [],
                'message'    => 'Posso leggere i file del tuo sito WordPress. Dimmi quale file vuoi che apra (es. functions.php del tema, un file di un plugin, ecc.).',
            ]);
        }

        // Handle database queries
        if ( preg_match( '/(database|db|tabella|table|query|dati|data)/i', $message_lower ) ) {
            return wp_json_encode( [
                'intent'     => 'database',
                'confidence' => 0.80,
                'actions'    => [],
                'message'    => 'Posso accedere al database WordPress. Dimmi cosa vuoi sapere o quale operazione vuoi eseguire (visualizzare tabelle, eseguire query, ecc.).',
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
     * Check if message is a confirmation
     *
     * @param string $message_lower Lowercase message.
     * @return bool
     */
    private function is_confirmation_message( string $message_lower ): bool {
        $confirmation_patterns = [
            '/^(s[iì]|yes|yeah|yep|ok|okay|va bene|procedi|conferma|confermo|esegui|fallo|fai|certo|assolutamente|perfetto|d\'accordo|daccordo)\b/i',
            '/\b(s[iì]\s*(,\s*)?(procedi|conferma|esegui|fallo|fai|grazie)?)\b/i',
            '/\b(procedi|conferma|esegui|vai|avanti|continua)\b/i',
            '/^(👍|✅|✓|💪|🚀)/u',
        ];

        foreach ( $confirmation_patterns as $pattern ) {
            if ( preg_match( $pattern, $message_lower ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if message is a rejection/cancellation
     *
     * @param string $message_lower Lowercase message.
     * @return bool
     */
    private function is_rejection_message( string $message_lower ): bool {
        $rejection_patterns = [
            '/^(no|nope|nah|annulla|cancella|stop|ferma|non)\b/i',
            '/\b(non\s+(lo\s+)?fare|annulla|cancella|lascia\s+(stare|perdere))\b/i',
        ];

        foreach ( $rejection_patterns as $pattern ) {
            if ( preg_match( $pattern, $message_lower ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate action execution response
     *
     * @param array $pending_actions Pending actions.
     * @param array $conversation    Conversation history.
     * @return string
     */
    private function generate_action_execution_response( array $pending_actions, array $conversation ): string {
        // Extract details from conversation for better action parameters
        $context_details = $this->extract_context_from_conversation( $conversation );

        $actions_to_execute = [];

        foreach ( $pending_actions as $action ) {
            $enhanced_action = $this->enhance_action_with_context( $action, $context_details );
            $enhanced_action['status'] = 'ready';
            $actions_to_execute[] = $enhanced_action;
        }

        $action_type = $actions_to_execute[0]['type'] ?? 'unknown';
        $message = $this->get_execution_message( $action_type, $context_details );

        return wp_json_encode( [
            'intent'     => 'execute_' . $action_type,
            'confidence' => 0.98,
            'actions'    => $actions_to_execute,
            'message'    => $message,
        ]);
    }

    /**
     * Extract context details from conversation history
     *
     * @param array $conversation Conversation history.
     * @return array
     */
    private function extract_context_from_conversation( array $conversation ): array {
        $details = [
            'title'           => '',
            'description'     => '',
            'business_name'   => '',
            'business_type'   => '',
            'services'        => [],
            'use_elementor'   => false,
            'include_form'    => false,
            'use_lorem'       => false,
            'raw_request'     => '',
        ];

        foreach ( $conversation as $msg ) {
            if ( $msg['role'] !== 'user' ) {
                continue;
            }

            $content = $msg['content'];
            $details['raw_request'] .= ' ' . $content;

            // Check for Elementor mention
            if ( preg_match( '/\belementor\b/i', $content ) ) {
                $details['use_elementor'] = true;
            }

            // Check for contact form mention
            if ( preg_match( '/\b(form|modulo|contatt|contact)\b/i', $content ) ) {
                $details['include_form'] = true;
            }

            // Check for lorem ipsum / placeholder text
            if ( preg_match( '/\b(lorem|ipsum|segnaposto|placeholder|prova|test)\b/i', $content ) ) {
                $details['use_lorem'] = true;
            }

            // Extract business/person name (look for patterns like "per X" or "di X")
            if ( preg_match( '/\b(?:per|di|del|della|dello)\s+(?:il\s+)?(?:la\s+)?(?:lo\s+)?(?:dentista|dott\.?|dottor|dottoressa|professionista|avvocato|studio|azienda|negozio|ristorante|hotel|medico)?\s*([A-Z][a-zàèéìòù]+(?:\s+[A-Z][a-zàèéìòù]+)*)/i', $content, $matches ) ) {
                $details['business_name'] = trim( $matches[1] );
            }

            // Extract business type
            if ( preg_match( '/\b(dentista|avvocato|medico|ristorante|hotel|negozio|studio|azienda|professionista|fotografo|architetto|commercialista)\b/i', $content, $matches ) ) {
                $details['business_type'] = strtolower( $matches[1] );
            }

            // Extract services mentioned
            if ( preg_match( '/\bservizi?\b/i', $content ) ) {
                // Look for service-related content
                $details['services'][] = 'services_requested';
            }
        }

        // Generate a title based on extracted info
        if ( ! empty( $details['business_name'] ) ) {
            $details['title'] = $details['business_name'];
            if ( ! empty( $details['business_type'] ) ) {
                $details['title'] = ucfirst( $details['business_type'] ) . ' ' . $details['business_name'];
            }
        }

        return $details;
    }

    /**
     * Enhance action with context details
     *
     * @param array $action          Original action.
     * @param array $context_details Extracted context.
     * @return array
     */
    private function enhance_action_with_context( array $action, array $context_details ): array {
        $type = $action['type'] ?? '';

        switch ( $type ) {
            case 'create_page':
                $action['params'] = $this->build_page_params( $context_details );
                break;

            case 'create_post':
                $action['params'] = $this->build_post_params( $context_details );
                break;
        }

        return $action;
    }

    /**
     * Build page parameters from context
     *
     * @param array $context Context details.
     * @return array
     */
    private function build_page_params( array $context ): array {
        $title = $context['title'] ?: 'Nuova Pagina';
        $use_elementor = $context['use_elementor'];
        $include_form = $context['include_form'];
        $use_lorem = $context['use_lorem'];

        // Build content based on context
        $content = $this->generate_page_content( $context );

        $params = [
            'title'   => $title,
            'content' => $content,
            'status'  => 'draft',
        ];

        if ( $use_elementor ) {
            $params['use_elementor'] = true;
            $params['elementor_data'] = $this->generate_elementor_template( $context );
        }

        return $params;
    }

    /**
     * Build post parameters from context
     *
     * @param array $context Context details.
     * @return array
     */
    private function build_post_params( array $context ): array {
        return [
            'title'   => $context['title'] ?: 'Nuovo Articolo',
            'content' => $this->generate_post_content( $context ),
            'status'  => 'draft',
        ];
    }

    /**
     * Generate page content based on context
     *
     * @param array $context Context details.
     * @return string
     */
    private function generate_page_content( array $context ): string {
        $business_name = $context['business_name'] ?: '[Nome Professionista]';
        $business_type = $context['business_type'] ?: 'professionista';
        $use_lorem = $context['use_lorem'];

        $lorem_short = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.';
        $lorem_long = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.';

        $content = "<!-- wp:heading {\"level\":1} -->\n";
        $content .= "<h1>" . esc_html( $business_name ) . "</h1>\n";
        $content .= "<!-- /wp:heading -->\n\n";

        $content .= "<!-- wp:paragraph -->\n";
        $content .= "<p>" . ( $use_lorem ? $lorem_long : "[Inserisci qui la descrizione del {$business_type}]" ) . "</p>\n";
        $content .= "<!-- /wp:paragraph -->\n\n";

        // Services section
        $content .= "<!-- wp:heading -->\n";
        $content .= "<h2>I Nostri Servizi</h2>\n";
        $content .= "<!-- /wp:heading -->\n\n";

        $services = $this->get_default_services( $business_type );
        foreach ( $services as $service ) {
            $content .= "<!-- wp:heading {\"level\":3} -->\n";
            $content .= "<h3>" . esc_html( $service['name'] ) . "</h3>\n";
            $content .= "<!-- /wp:heading -->\n\n";

            $content .= "<!-- wp:paragraph -->\n";
            $content .= "<p>" . ( $use_lorem ? $lorem_short : $service['description'] ) . "</p>\n";
            $content .= "<!-- /wp:paragraph -->\n\n";
        }

        // Contact form section
        if ( $context['include_form'] ) {
            $content .= "<!-- wp:heading -->\n";
            $content .= "<h2>Contattaci</h2>\n";
            $content .= "<!-- /wp:heading -->\n\n";

            $content .= "<!-- wp:paragraph -->\n";
            $content .= "<p>" . ( $use_lorem ? $lorem_short : "[Inserisci qui le informazioni di contatto]" ) . "</p>\n";
            $content .= "<!-- /wp:paragraph -->\n\n";

            // Check for WPForms or Contact Form 7
            if ( is_plugin_active( 'wpforms-lite/wpforms.php' ) || is_plugin_active( 'wpforms/wpforms.php' ) ) {
                $content .= "<!-- wp:wpforms/form-selector /-->\n\n";
            } elseif ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {
                $content .= "<!-- wp:contact-form-7/contact-form-selector /-->\n\n";
            } else {
                $content .= "<!-- wp:html -->\n";
                $content .= "<form class=\"contact-form\">\n";
                $content .= "  <p><label>Nome<br><input type=\"text\" name=\"name\" required></label></p>\n";
                $content .= "  <p><label>Email<br><input type=\"email\" name=\"email\" required></label></p>\n";
                $content .= "  <p><label>Telefono<br><input type=\"tel\" name=\"phone\"></label></p>\n";
                $content .= "  <p><label>Messaggio<br><textarea name=\"message\" rows=\"5\" required></textarea></label></p>\n";
                $content .= "  <p><button type=\"submit\">Invia Messaggio</button></p>\n";
                $content .= "</form>\n";
                $content .= "<!-- /wp:html -->\n\n";
            }
        }

        return $content;
    }

    /**
     * Generate post content based on context
     *
     * @param array $context Context details.
     * @return string
     */
    private function generate_post_content( array $context ): string {
        $lorem = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.';

        return "<!-- wp:paragraph -->\n<p>" . $lorem . "</p>\n<!-- /wp:paragraph -->";
    }

    /**
     * Get default services based on business type
     *
     * @param string $business_type Business type.
     * @return array
     */
    private function get_default_services( string $business_type ): array {
        $services_map = [
            'dentista' => [
                [ 'name' => 'Igiene Dentale', 'description' => '[Descrizione del servizio di igiene dentale professionale]' ],
                [ 'name' => 'Sbiancamento', 'description' => '[Descrizione del servizio di sbiancamento dentale]' ],
                [ 'name' => 'Ortodonzia', 'description' => '[Descrizione del servizio di ortodonzia]' ],
                [ 'name' => 'Implantologia', 'description' => '[Descrizione del servizio di implantologia]' ],
            ],
            'avvocato' => [
                [ 'name' => 'Consulenza Legale', 'description' => '[Descrizione del servizio di consulenza legale]' ],
                [ 'name' => 'Diritto Civile', 'description' => '[Descrizione del servizio di diritto civile]' ],
                [ 'name' => 'Diritto Penale', 'description' => '[Descrizione del servizio di diritto penale]' ],
                [ 'name' => 'Diritto del Lavoro', 'description' => '[Descrizione del servizio di diritto del lavoro]' ],
            ],
            'medico' => [
                [ 'name' => 'Visite Specialistiche', 'description' => '[Descrizione delle visite specialistiche]' ],
                [ 'name' => 'Check-up Completo', 'description' => '[Descrizione del servizio di check-up]' ],
                [ 'name' => 'Ecografie', 'description' => '[Descrizione del servizio di ecografia]' ],
                [ 'name' => 'Consulenze Online', 'description' => '[Descrizione delle consulenze online]' ],
            ],
            'fotografo' => [
                [ 'name' => 'Servizi Fotografici', 'description' => '[Descrizione dei servizi fotografici]' ],
                [ 'name' => 'Fotografia di Matrimonio', 'description' => '[Descrizione del servizio matrimoni]' ],
                [ 'name' => 'Fotografia Aziendale', 'description' => '[Descrizione della fotografia aziendale]' ],
                [ 'name' => 'Post-produzione', 'description' => '[Descrizione del servizio di post-produzione]' ],
            ],
            'default' => [
                [ 'name' => 'Servizio 1', 'description' => '[Descrizione del primo servizio offerto]' ],
                [ 'name' => 'Servizio 2', 'description' => '[Descrizione del secondo servizio offerto]' ],
                [ 'name' => 'Servizio 3', 'description' => '[Descrizione del terzo servizio offerto]' ],
                [ 'name' => 'Servizio 4', 'description' => '[Descrizione del quarto servizio offerto]' ],
            ],
        ];

        return $services_map[ $business_type ] ?? $services_map['default'];
    }

    /**
     * Generate Elementor template data
     *
     * @param array $context Context details.
     * @return array
     */
    private function generate_elementor_template( array $context ): array {
        $business_name = $context['business_name'] ?: '[Nome Professionista]';
        $business_type = $context['business_type'] ?: 'professionista';
        $use_lorem = $context['use_lorem'];
        $include_form = $context['include_form'];

        $lorem_short = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.';
        $lorem_long = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.';

        $elements = [];

        // Hero Section
        $elements[] = [
            'elType' => 'section',
            'settings' => [
                'layout' => 'full_width',
                'min_height' => [ 'size' => 500, 'unit' => 'px' ],
                'background_background' => 'classic',
                'background_color' => '#f8f9fa',
            ],
            'elements' => [
                [
                    'elType' => 'column',
                    'settings' => [ 'content_position' => 'center' ],
                    'elements' => [
                        [
                            'elType' => 'widget',
                            'widgetType' => 'heading',
                            'settings' => [
                                'title' => $business_name,
                                'header_size' => 'h1',
                                'align' => 'center',
                            ],
                        ],
                        [
                            'elType' => 'widget',
                            'widgetType' => 'text-editor',
                            'settings' => [
                                'editor' => $use_lorem ? $lorem_long : "[Descrizione introduttiva del {$business_type}]",
                                'align' => 'center',
                            ],
                        ],
                        [
                            'elType' => 'widget',
                            'widgetType' => 'button',
                            'settings' => [
                                'text' => 'Contattaci',
                                'align' => 'center',
                                'link' => [ 'url' => '#contatti' ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        // Services Section
        $services = $this->get_default_services( $business_type );
        $service_columns = [];

        foreach ( $services as $service ) {
            $service_columns[] = [
                'elType' => 'column',
                'settings' => [ '_column_size' => 25 ],
                'elements' => [
                    [
                        'elType' => 'widget',
                        'widgetType' => 'icon-box',
                        'settings' => [
                            'selected_icon' => [ 'value' => 'fas fa-check-circle', 'library' => 'fa-solid' ],
                            'title_text' => $service['name'],
                            'description_text' => $use_lorem ? $lorem_short : $service['description'],
                        ],
                    ],
                ],
            ];
        }

        $elements[] = [
            'elType' => 'section',
            'settings' => [
                'layout' => 'boxed',
                'gap' => 'extended',
            ],
            'elements' => [
                [
                    'elType' => 'column',
                    'settings' => [],
                    'elements' => [
                        [
                            'elType' => 'widget',
                            'widgetType' => 'heading',
                            'settings' => [
                                'title' => 'I Nostri Servizi',
                                'header_size' => 'h2',
                                'align' => 'center',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $elements[] = [
            'elType' => 'section',
            'settings' => [
                'layout' => 'boxed',
                'structure' => '40',
            ],
            'elements' => $service_columns,
        ];

        // Contact Section
        if ( $include_form ) {
            $contact_elements = [
                [
                    'elType' => 'widget',
                    'widgetType' => 'heading',
                    'settings' => [
                        'title' => 'Contattaci',
                        'header_size' => 'h2',
                        'align' => 'center',
                    ],
                ],
                [
                    'elType' => 'widget',
                    'widgetType' => 'text-editor',
                    'settings' => [
                        'editor' => $use_lorem ? $lorem_short : '[Inserisci qui le informazioni di contatto e gli orari di apertura]',
                        'align' => 'center',
                    ],
                ],
            ];

            // Add form widget if Elementor Pro is available
            $contact_elements[] = [
                'elType' => 'widget',
                'widgetType' => 'form',
                'settings' => [
                    'form_name' => 'Contatto',
                    'form_fields' => [
                        [
                            'field_type' => 'text',
                            'field_label' => 'Nome',
                            'placeholder' => 'Il tuo nome',
                            'required' => 'yes',
                        ],
                        [
                            'field_type' => 'email',
                            'field_label' => 'Email',
                            'placeholder' => 'La tua email',
                            'required' => 'yes',
                        ],
                        [
                            'field_type' => 'tel',
                            'field_label' => 'Telefono',
                            'placeholder' => 'Il tuo numero',
                        ],
                        [
                            'field_type' => 'textarea',
                            'field_label' => 'Messaggio',
                            'placeholder' => 'Come possiamo aiutarti?',
                            'required' => 'yes',
                        ],
                    ],
                    'button_text' => 'Invia Messaggio',
                ],
            ];

            $elements[] = [
                'elType' => 'section',
                'settings' => [
                    'layout' => 'boxed',
                    'background_background' => 'classic',
                    'background_color' => '#f8f9fa',
                ],
                'isInner' => false,
                'elements' => [
                    [
                        'elType' => 'column',
                        'settings' => [],
                        'elements' => $contact_elements,
                    ],
                ],
            ];
        }

        return $elements;
    }

    /**
     * Get execution message based on action type
     *
     * @param string $action_type     Action type.
     * @param array  $context_details Context details.
     * @return string
     */
    private function get_execution_message( string $action_type, array $context_details ): string {
        $title = $context_details['title'] ?: 'il contenuto richiesto';

        $messages = [
            'create_page' => "Perfetto! Sto creando la pagina \"{$title}\" con tutti gli elementi richiesti. La troverai come bozza nel pannello Pagine.",
            'create_post' => "Perfetto! Sto creando l'articolo \"{$title}\". Lo troverai come bozza nel pannello Articoli.",
            'update_page' => "Sto aggiornando la pagina come richiesto.",
            'update_post' => "Sto aggiornando l'articolo come richiesto.",
            'create_plugin' => "Sto creando il plugin personalizzato. Lo troverai attivo nel pannello Plugin.",
        ];

        return $messages[ $action_type ] ?? "Sto eseguendo l'operazione richiesta.";
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
