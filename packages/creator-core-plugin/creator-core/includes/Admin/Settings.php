<?php
/**
 * Settings Page
 *
 * @package CreatorCore
 */

namespace CreatorCore\Admin;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Proxy\ProxyClient;
use CreatorCore\User\UserProfile;
use CreatorCore\Context\CreatorContext;
use CreatorCore\Context\ContextRefresher;

/**
 * Class Settings
 *
 * Handles the plugin settings page
 * MVP version: Simplified without PluginDetector, RoleMapper, SnapshotManager.
 */
class Settings {

	/**
	 * Proxy client instance
	 *
	 * @var ProxyClient
	 */
	private ProxyClient $proxy_client;

	/**
	 * Constructor
	 *
	 * @param ProxyClient $proxy_client Proxy client instance.
	 */
	public function __construct( ProxyClient $proxy_client ) {
		$this->proxy_client = $proxy_client;

		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'wp_ajax_creator_validate_license', [ $this, 'ajax_validate_license' ] );
		add_action( 'wp_ajax_creator_clear_cache', [ $this, 'ajax_clear_cache' ] );
		add_action( 'wp_ajax_creator_save_profile', [ $this, 'ajax_save_profile' ] );
		add_action( 'wp_ajax_creator_refresh_context', [ $this, 'ajax_refresh_context' ] );
	}

	/**
	 * Render settings page
	 *
	 * @return void
	 */
	public function render(): void {
		// Handle form submission
		if ( isset( $_POST['creator_settings_nonce'] ) ) {
			$this->handle_save();
		}

		$data = [
			'settings'       => $this->get_all_settings(),
			'connection'     => $this->proxy_client->check_connection(),
			'user_profile'   => [
				'current_level' => UserProfile::get_level(),
				'levels'        => UserProfile::get_levels_info(),
				'is_set'        => UserProfile::is_level_set(),
			],
			'context_status' => $this->get_context_status(),
		];

		include CREATOR_CORE_PATH . 'templates/settings.php';
	}

	/**
	 * Register settings
	 *
	 * @return void
	 */
	public function register_settings(): void {
		// API Settings
		register_setting( 'creator_api_settings', 'creator_license_key', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		] );
		register_setting( 'creator_api_settings', 'creator_proxy_url', [
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => CREATOR_PROXY_URL,
		] );

		// Advanced Settings
		register_setting( 'creator_advanced_settings', 'creator_debug_mode', [
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => false,
		] );
		register_setting( 'creator_advanced_settings', 'creator_delete_data_on_uninstall', [
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => false,
		] );
	}

	/**
	 * Handle settings save
	 *
	 * @return void
	 */
	private function handle_save(): void {
		if ( ! wp_verify_nonce( $_POST['creator_settings_nonce'], 'creator_save_settings' ) ) {
			add_settings_error( 'creator_settings', 'nonce_error', __( 'Security check failed.', 'creator-core' ), 'error' );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			add_settings_error( 'creator_settings', 'permission_error', __( 'Permission denied.', 'creator-core' ), 'error' );
			return;
		}

		// API Settings
		if ( isset( $_POST['creator_license_key'] ) ) {
			update_option( 'creator_license_key', sanitize_text_field( $_POST['creator_license_key'] ) );
		}
		if ( isset( $_POST['creator_proxy_url'] ) ) {
			update_option( 'creator_proxy_url', esc_url_raw( $_POST['creator_proxy_url'] ) );
		}

		// Advanced Settings
		update_option( 'creator_debug_mode', isset( $_POST['creator_debug_mode'] ) );
		update_option( 'creator_delete_data_on_uninstall', isset( $_POST['creator_delete_data_on_uninstall'] ) );

		add_settings_error( 'creator_settings', 'settings_saved', __( 'Settings saved successfully.', 'creator-core' ), 'success' );
	}

	/**
	 * Get all settings
	 *
	 * @return array
	 */
	public function get_all_settings(): array {
		return [
			'license_key'              => get_option( 'creator_license_key', '' ),
			'proxy_url'                => get_option( 'creator_proxy_url', CREATOR_PROXY_URL ),
			'debug_mode'               => get_option( 'creator_debug_mode', false ),
			'delete_data_on_uninstall' => get_option( 'creator_delete_data_on_uninstall', false ),
			'setup_completed'          => get_option( 'creator_setup_completed', false ),
		];
	}

	/**
	 * AJAX: Validate license
	 *
	 * @return void
	 */
	public function ajax_validate_license(): void {
		check_ajax_referer( 'creator_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'creator-core' ) ] );
		}

		$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( $_POST['license_key'] ) : '';

		if ( empty( $license_key ) ) {
			wp_send_json_error( [ 'message' => __( 'License key is required', 'creator-core' ) ] );
		}

		$result = $this->proxy_client->validate_license( $license_key );

		if ( $result['success'] ) {
			update_option( 'creator_license_key', $license_key );
			wp_send_json_success( [
				'message' => __( 'License validated successfully', 'creator-core' ),
				'data'    => $result,
			] );
		} else {
			wp_send_json_error( [
				'message' => $result['error'] ?? __( 'License validation failed', 'creator-core' ),
			] );
		}
	}

	/**
	 * AJAX: Clear cache
	 *
	 * @return void
	 */
	public function ajax_clear_cache(): void {
		check_ajax_referer( 'creator_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'creator-core' ) ] );
		}

		// Clear plugin caches
		delete_transient( 'creator_detected_plugins' );
		delete_transient( 'creator_site_context' );
		delete_transient( 'creator_license_status' );

		wp_cache_flush();

		wp_send_json_success( [ 'message' => __( 'Cache cleared successfully', 'creator-core' ) ] );
	}

	/**
	 * AJAX: Save user profile (competency level and default model)
	 *
	 * @return void
	 */
	public function ajax_save_profile(): void {
		check_ajax_referer( 'creator_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'creator-core' ) ] );
		}

		$level = isset( $_POST['user_level'] ) ? sanitize_key( wp_unslash( $_POST['user_level'] ) ) : '';
		$model = isset( $_POST['default_model'] ) ? sanitize_key( wp_unslash( $_POST['default_model'] ) ) : '';

		if ( empty( $level ) ) {
			wp_send_json_error( [ 'message' => __( 'Please select a competency level', 'creator-core' ) ] );
		}

		if ( ! in_array( $level, UserProfile::get_valid_levels(), true ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid competency level', 'creator-core' ) ] );
		}

		// Validate model if provided
		if ( ! empty( $model ) && ! in_array( $model, UserProfile::get_valid_models(), true ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid AI model', 'creator-core' ) ] );
		}

		$level_saved = UserProfile::set_level( $level );

		// Save default model if provided
		$model_saved = true;
		if ( ! empty( $model ) ) {
			$model_saved = UserProfile::set_default_model( $model );
		}

		if ( $level_saved && $model_saved ) {
			$levels_info = UserProfile::get_levels_info();
			$models_info = UserProfile::get_models_info();

			$response = [
				'message' => __( 'Profile updated successfully', 'creator-core' ),
				'level'   => $level,
				'label'   => $levels_info[ $level ]['label'],
			];

			if ( ! empty( $model ) ) {
				$response['model']       = $model;
				$response['model_label'] = $models_info[ $model ]['label'];
			}

			wp_send_json_success( $response );
		} else {
			wp_send_json_error( [ 'message' => __( 'Failed to save profile', 'creator-core' ) ] );
		}
	}

	/**
	 * Get context status for settings page
	 *
	 * @return array
	 */
	private function get_context_status(): array {
		try {
			$context   = new CreatorContext();
			$refresher = new ContextRefresher();

			$stored = $context->get_stored_context();

			return [
				'has_context'     => $stored !== null,
				'generated_at'    => $context->get_generated_at(),
				'is_valid'        => $context->is_context_valid(),
				'is_stale'        => $context->is_context_stale(),
				'pending_refresh' => $refresher->get_status()['pending_refresh'] ?? false,
				'plugins_count'   => count( $stored['plugins'] ?? [] ),
				'cpts_count'      => count( $stored['custom_post_types'] ?? [] ),
				'acf_groups'      => count( $stored['acf_fields'] ?? [] ),
				'sitemap_count'   => count( $stored['sitemap'] ?? [] ),
			];
		} catch ( \Exception $e ) {
			// Return safe defaults if context loading fails
			return [
				'has_context'     => false,
				'generated_at'    => null,
				'is_valid'        => false,
				'is_stale'        => true,
				'pending_refresh' => false,
				'plugins_count'   => 0,
				'cpts_count'      => 0,
				'acf_groups'      => 0,
				'sitemap_count'   => 0,
			];
		}
	}

	/**
	 * AJAX: Refresh Creator Context
	 *
	 * @return void
	 */
	public function ajax_refresh_context(): void {
		check_ajax_referer( 'creator_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied', 'creator-core' ) ] );
		}

		$start_time = microtime( true );

		try {
			$context = new CreatorContext();
			$result  = $context->refresh();

			$duration = round( ( microtime( true ) - $start_time ) * 1000 );

			wp_send_json_success( [
				'message'     => __( 'Creator Context refreshed successfully', 'creator-core' ),
				'duration_ms' => $duration,
				'timestamp'   => $context->get_generated_at(),
				'stats'       => [
					'plugins' => count( $result['plugins'] ?? [] ),
					'cpts'    => count( $result['custom_post_types'] ?? [] ),
					'acf'     => count( $result['acf_fields'] ?? [] ),
					'sitemap' => count( $result['sitemap'] ?? [] ),
				],
			] );
		} catch ( \Exception $e ) {
			wp_send_json_error( [
				'message' => sprintf(
					/* translators: %s: Error message */
					__( 'Failed to refresh context: %s', 'creator-core' ),
					$e->getMessage()
				),
			] );
		}
	}
}
