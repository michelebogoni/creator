<?php
/**
 * Plugin Loader
 *
 * MVP version: Simplified loader with minimal components.
 *
 * @package CreatorCore
 */

namespace CreatorCore;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Admin\Settings;
use CreatorCore\Chat\ChatInterface;
use CreatorCore\Chat\ChatController;
use CreatorCore\Proxy\ProxyClient;

/**
 * Class Loader
 *
 * Main plugin loader that initializes all components
 */
class Loader {

	/**
	 * Settings instance
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Chat interface instance
	 *
	 * @var ChatInterface
	 */
	private ChatInterface $chat_interface;

	/**
	 * Chat controller instance
	 *
	 * @var ChatController
	 */
	private ChatController $chat_controller;

	/**
	 * Proxy client instance
	 *
	 * @var ProxyClient
	 */
	private ProxyClient $proxy_client;

	/**
	 * Run the loader
	 *
	 * @return void
	 */
	public function run(): void {
		$this->init_components();
		$this->register_hooks();
	}

	/**
	 * Initialize all components
	 *
	 * @return void
	 */
	private function init_components(): void {
		// Core services
		$this->proxy_client = new ProxyClient();

		// Admin components
		$this->settings = new Settings( $this->proxy_client );

		// Chat system
		$this->chat_interface  = new ChatInterface( $this->proxy_client );
		$this->chat_controller = new ChatController( $this->chat_interface, $this->proxy_client );
	}

	/**
	 * Register WordPress hooks
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Admin menu
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );

		// Admin assets
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

		// REST API
		add_action( 'rest_api_init', [ $this->chat_controller, 'register_routes' ] );

		// Plugin action links
		add_filter( 'plugin_action_links_' . CREATOR_CORE_BASENAME, [ $this, 'add_action_links' ] );
	}

	/**
	 * Register admin menu
	 *
	 * @return void
	 */
	public function register_admin_menu(): void {
		// Main menu - Chat page
		add_menu_page(
			__( 'Creator', 'creator-core' ),
			__( 'Creator', 'creator-core' ),
			'edit_posts',
			'creator-chat',
			[ $this->chat_interface, 'render' ],
			'dashicons-superhero-alt',
			30
		);

		// Chat submenu (same as main)
		add_submenu_page(
			'creator-chat',
			__( 'Chat', 'creator-core' ),
			__( 'Chat', 'creator-core' ),
			'edit_posts',
			'creator-chat',
			[ $this->chat_interface, 'render' ]
		);

		// Settings
		add_submenu_page(
			'creator-chat',
			__( 'Settings', 'creator-core' ),
			__( 'Settings', 'creator-core' ),
			'manage_options',
			'creator-settings',
			[ $this->settings, 'render' ]
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		$hook = (string) $hook;
		if ( $hook === '' ) {
			return;
		}

		// Only load on Creator pages
		if ( strpos( $hook, 'creator' ) === false ) {
			return;
		}

		// Common styles
		wp_enqueue_style(
			'creator-admin-common',
			CREATOR_CORE_URL . 'assets/css/admin-common.css',
			[],
			CREATOR_CORE_VERSION
		);

		// Chat page assets
		if ( strpos( $hook, 'creator-chat' ) !== false ) {
			wp_enqueue_style(
				'creator-chat-interface',
				CREATOR_CORE_URL . 'assets/css/chat-interface.css',
				[ 'creator-admin-common' ],
				CREATOR_CORE_VERSION
			);
			wp_enqueue_script(
				'creator-chat-interface',
				CREATOR_CORE_URL . 'assets/js/chat-interface.js',
				[ 'jquery', 'wp-util' ],
				CREATOR_CORE_VERSION,
				true
			);
			$current_user = wp_get_current_user();
			$chat_id      = isset( $_GET['chat_id'] ) ? absint( $_GET['chat_id'] ) : null;
			wp_localize_script( 'creator-chat-interface', 'creatorChat', [
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'restUrl'     => rest_url( 'creator/v1/' ),
				'adminUrl'    => admin_url( 'admin.php' ),
				'settingsUrl' => admin_url( 'admin.php?page=creator-settings' ),
				'nonce'       => wp_create_nonce( 'creator_chat_nonce' ),
				'restNonce'   => wp_create_nonce( 'wp_rest' ),
				'chatId'      => $chat_id,
				'userName'    => $current_user->display_name,
				'userAvatar'  => get_avatar_url( $current_user->ID, [ 'size' => 32 ] ),
				'i18n'        => [
					'sending'    => __( 'Sending...', 'creator-core' ),
					'error'      => __( 'An error occurred. Please try again.', 'creator-core' ),
					'processing' => __( 'Processing...', 'creator-core' ),
				],
			]);
		}

		// Settings page assets
		if ( strpos( $hook, 'creator-settings' ) !== false ) {
			wp_enqueue_style(
				'creator-settings',
				CREATOR_CORE_URL . 'assets/css/settings.css',
				[ 'creator-admin-common' ],
				CREATOR_CORE_VERSION
			);
			wp_enqueue_script(
				'creator-settings',
				CREATOR_CORE_URL . 'assets/js/settings.js',
				[ 'jquery' ],
				CREATOR_CORE_VERSION,
				true
			);
			wp_localize_script( 'creator-settings', 'creatorSettings', [
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'restUrl'   => rest_url( 'creator/v1/' ),
				'nonce'     => wp_create_nonce( 'creator_settings_nonce' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'      => [
					'saving'       => __( 'Saving...', 'creator-core' ),
					'saved'        => __( 'Settings saved', 'creator-core' ),
					'error'        => __( 'An error occurred', 'creator-core' ),
					'validating'   => __( 'Validating...', 'creator-core' ),
					'clearing'     => __( 'Clearing cache...', 'creator-core' ),
					'cacheCleared' => __( 'Cache cleared', 'creator-core' ),
				],
			]);
		}
	}

	/**
	 * Add plugin action links
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function add_action_links( array $links ): array {
		$plugin_links = [
			'<a href="' . admin_url( 'admin.php?page=creator-settings' ) . '">' . __( 'Settings', 'creator-core' ) . '</a>',
			'<a href="' . admin_url( 'admin.php?page=creator-chat' ) . '">' . __( 'Chat', 'creator-core' ) . '</a>',
		];

		return array_merge( $plugin_links, $links );
	}
}
