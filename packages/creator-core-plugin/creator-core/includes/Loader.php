<?php
/**
 * Plugin Loader
 *
 * Bootstrap minimo che carica solo i componenti esistenti.
 *
 * @package CreatorCore
 */

namespace CreatorCore;

defined( 'ABSPATH' ) || exit;

/**
 * Class Loader
 *
 * Main plugin loader - initializes all components
 */
class Loader {

    /**
     * Admin settings instance
     *
     * @var Admin\Settings|null
     */
    private ?Admin\Settings $settings = null;

    /**
     * Chat interface instance
     *
     * @var Chat\ChatInterface|null
     */
    private ?Chat\ChatInterface $chat_interface = null;

    /**
     * Chat controller instance
     *
     * @var Chat\ChatController|null
     */
    private ?Chat\ChatController $chat_controller = null;

    /**
     * Run the plugin
     *
     * @return void
     */
    public function run(): void {
        // Always register REST API endpoints (permission is checked per-request)
        $this->init_rest_api();

        // Only load admin UI components in admin context
        if ( ! is_admin() ) {
            return;
        }

        // Check user capability for admin UI
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Initialize admin components
        $this->init_admin();
        $this->init_chat();
    }

    /**
     * Initialize admin components
     *
     * @return void
     */
    private function init_admin(): void {
        $this->settings = new Admin\Settings();
        $this->settings->init();
    }

    /**
     * Initialize chat interface
     *
     * @return void
     */
    private function init_chat(): void {
        $this->chat_interface = new Chat\ChatInterface();
        $this->chat_interface->init();
    }

    /**
     * Initialize REST API endpoints
     *
     * @return void
     */
    private function init_rest_api(): void {
        $this->chat_controller = new Chat\ChatController();
        add_action( 'rest_api_init', [ $this->chat_controller, 'register_routes' ] );
    }

    /**
     * Get settings instance
     *
     * @return Admin\Settings|null
     */
    public function get_settings(): ?Admin\Settings {
        return $this->settings;
    }

    /**
     * Get chat interface instance
     *
     * @return Chat\ChatInterface|null
     */
    public function get_chat_interface(): ?Chat\ChatInterface {
        return $this->chat_interface;
    }

    /**
     * Get chat controller instance
     *
     * @return Chat\ChatController|null
     */
    public function get_chat_controller(): ?Chat\ChatController {
        return $this->chat_controller;
    }
}
