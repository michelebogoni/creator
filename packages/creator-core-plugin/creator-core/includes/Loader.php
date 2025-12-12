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
     * Admin dashboard instance
     *
     * @var Admin\Dashboard|null
     */
    private ?Admin\Dashboard $dashboard = null;

    /**
     * Dashboard API instance
     *
     * @var Admin\DashboardAPI|null
     */
    private ?Admin\DashboardAPI $dashboard_api = null;

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
     * Debug controller instance
     *
     * @var Debug\DebugController|null
     */
    private ?Debug\DebugController $debug_controller = null;

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
        $this->dashboard = new Admin\Dashboard();
        $this->dashboard->init();
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
        $this->chat_controller  = new Chat\ChatController();
        $this->debug_controller = new Debug\DebugController();
        $this->dashboard_api    = new Admin\DashboardAPI();

        add_action( 'rest_api_init', [ $this->chat_controller, 'register_routes' ] );
        add_action( 'rest_api_init', [ $this->debug_controller, 'register_routes' ] );
        add_action( 'rest_api_init', [ $this->dashboard_api, 'register_routes' ] );
    }

    /**
     * Get dashboard instance
     *
     * @return Admin\Dashboard|null
     */
    public function get_dashboard(): ?Admin\Dashboard {
        return $this->dashboard;
    }

    /**
     * Get dashboard API instance
     *
     * @return Admin\DashboardAPI|null
     */
    public function get_dashboard_api(): ?Admin\DashboardAPI {
        return $this->dashboard_api;
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
