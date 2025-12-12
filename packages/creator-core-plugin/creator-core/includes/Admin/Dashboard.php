<?php
/**
 * Admin Dashboard Page
 *
 * Main landing page for Creator plugin with license status,
 * usage stats, and chat history management.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Dashboard
 *
 * Admin dashboard page for Creator plugin
 */
class Dashboard {

	/**
	 * Page slug
	 *
	 * @var string
	 */
	private string $page_slug = 'creator-dashboard';

	/**
	 * Page hook suffix
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Initialize the dashboard
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'register_page' ], 5 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_init', [ $this, 'maybe_show_license_notice' ] );
		add_action( 'update_option_creator_license_key', [ $this, 'on_license_key_updated' ], 10, 2 );
		add_action( 'wp_ajax_creator_verify_license', [ $this, 'ajax_verify_license' ] );
	}

	/**
	 * Register admin menu page
	 *
	 * @return void
	 */
	public function register_page(): void {
		$this->hook_suffix = add_menu_page(
			__( 'Creator Dashboard', 'creator-core' ),
			__( 'Creator', 'creator-core' ),
			'manage_options',
			$this->page_slug,
			[ $this, 'render_page' ],
			'dashicons-format-chat',
			30
		);

		// Add Dashboard as first submenu (same as parent).
		add_submenu_page(
			$this->page_slug,
			__( 'Dashboard', 'creator-core' ),
			__( 'Dashboard', 'creator-core' ),
			'manage_options',
			$this->page_slug,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Enqueue dashboard assets
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		// Only load on dashboard page.
		if ( $hook_suffix !== $this->hook_suffix && $hook_suffix !== 'toplevel_page_' . $this->page_slug ) {
			return;
		}

		// Enqueue CSS.
		wp_enqueue_style(
			'creator-dashboard',
			CREATOR_CORE_URL . 'assets/css/dashboard.css',
			[],
			CREATOR_CORE_VERSION
		);

		// Enqueue JS.
		wp_enqueue_script(
			'creator-dashboard',
			CREATOR_CORE_URL . 'assets/js/dashboard.js',
			[ 'jquery', 'wp-api-fetch' ],
			CREATOR_CORE_VERSION,
			true
		);

		// Prepare initial data.
		$license_data = $this->get_license_data();
		$usage_data   = $this->get_usage_data();
		$system_health = $this->get_system_health();

		// Localize script with data.
		wp_localize_script(
			'creator-dashboard',
			'creatorDashboard',
			[
				'restUrl'      => rest_url( 'creator/v1/' ),
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'chatUrl'      => admin_url( 'admin.php?page=creator-chat' ),
				'verifyNonce'  => wp_create_nonce( 'creator_verify_license' ),
				'license'      => $license_data,
				'usage'        => $usage_data,
				'systemHealth' => $system_health,
				'i18n'         => [
					'loadMore'           => __( 'Load More Conversations', 'creator-core' ),
					'loading'            => __( 'Loading...', 'creator-core' ),
					'noConversations'    => __( 'No conversations yet. Start chatting to see your history here.', 'creator-core' ),
					'startNewChat'       => __( 'Start New Chat', 'creator-core' ),
					'deleteConfirmTitle' => __( 'Delete Conversation?', 'creator-core' ),
					'deleteConfirmText'  => __( 'Are you sure you want to delete this conversation? This action cannot be undone.', 'creator-core' ),
					'cancel'             => __( 'Cancel', 'creator-core' ),
					'delete'             => __( 'Delete', 'creator-core' ),
					'deleted'            => __( 'Conversation deleted.', 'creator-core' ),
					'error'              => __( 'An error occurred. Please try again.', 'creator-core' ),
					'messages'           => __( 'messages', 'creator-core' ),
					'today'              => __( 'Today', 'creator-core' ),
					'yesterday'          => __( 'Yesterday', 'creator-core' ),
					'daysAgo'            => __( '%d days ago', 'creator-core' ),
					'verifying'          => __( 'Verifying...', 'creator-core' ),
					'verifyLicense'      => __( 'Verify License', 'creator-core' ),
					'available'          => __( 'Available', 'creator-core' ),
					'creditsUsed'        => __( 'Credits Used', 'creator-core' ),
					'resetDate'          => __( 'Reset', 'creator-core' ),
				],
			]
		);
	}

	/**
	 * Render the dashboard page
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'creator-core' ) );
		}

		// Check if license is configured.
		$license_key = get_option( 'creator_license_key', '' );
		?>
		<div class="wrap creator-dashboard-wrap">
			<h1 class="creator-dashboard-title"><?php esc_html_e( 'Creator Dashboard', 'creator-core' ); ?></h1>

			<div id="creator-dashboard-app">
				<!-- Top row: License & Usage cards -->
				<div class="creator-dashboard-top-row">
					<!-- License & Account Status Card -->
					<div class="creator-card creator-license-card">
						<div class="creator-card-header">
							<h2><?php esc_html_e( 'License & Account Status', 'creator-core' ); ?></h2>
						</div>
						<div class="creator-card-body">
							<?php if ( empty( $license_key ) ) : ?>
								<div class="creator-license-setup">
									<p><?php esc_html_e( 'Enter your license key to enable AI features.', 'creator-core' ); ?></p>
									<form id="creator-license-form" method="post" action="options.php">
										<?php settings_fields( 'creator_dashboard_settings' ); ?>
										<div class="creator-license-input-wrap">
											<input
												type="text"
												id="creator_license_key"
												name="creator_license_key"
												value=""
												class="creator-license-input"
												placeholder="CREATOR-XXXX-XXXX-XXXX"
											/>
											<button type="submit" class="button button-primary">
												<?php esc_html_e( 'Save', 'creator-core' ); ?>
											</button>
										</div>
									</form>
								</div>
							<?php else : ?>
								<div id="creator-license-status" class="creator-license-status">
									<!-- Populated by JS -->
								</div>
								<div class="creator-license-actions">
									<button type="button" id="creator-verify-license" class="button button-secondary">
										<?php esc_html_e( 'Verify License', 'creator-core' ); ?>
									</button>
									<button type="button" id="creator-change-license" class="button button-link">
										<?php esc_html_e( 'Change License Key', 'creator-core' ); ?>
									</button>
								</div>
								<div id="creator-change-license-form" class="creator-license-setup" style="display: none;">
									<form method="post" action="options.php">
										<?php settings_fields( 'creator_dashboard_settings' ); ?>
										<div class="creator-license-input-wrap">
											<input
												type="text"
												id="creator_license_key_new"
												name="creator_license_key"
												value="<?php echo esc_attr( $license_key ); ?>"
												class="creator-license-input"
												placeholder="CREATOR-XXXX-XXXX-XXXX"
											/>
											<button type="submit" class="button button-primary">
												<?php esc_html_e( 'Save', 'creator-core' ); ?>
											</button>
											<button type="button" id="creator-cancel-change" class="button button-secondary">
												<?php esc_html_e( 'Cancel', 'creator-core' ); ?>
											</button>
										</div>
									</form>
								</div>
							<?php endif; ?>

							<!-- System Health -->
							<div class="creator-system-health">
								<h3><?php esc_html_e( 'System Health', 'creator-core' ); ?></h3>
								<div id="creator-health-indicators" class="creator-health-indicators">
									<!-- Populated by JS -->
								</div>
							</div>
						</div>
					</div>

					<!-- Usage & Credits Card -->
					<div class="creator-card creator-usage-card">
						<div class="creator-card-header">
							<h2><?php esc_html_e( 'Usage & Credits', 'creator-core' ); ?></h2>
						</div>
						<div class="creator-card-body">
							<div id="creator-usage-display" class="creator-usage-display">
								<!-- Populated by JS -->
							</div>
						</div>
					</div>
				</div>

				<!-- Chat History Panel -->
				<div class="creator-card creator-chat-history-card">
					<div class="creator-card-header">
						<h2><?php esc_html_e( 'Chat History', 'creator-core' ); ?></h2>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=creator-chat' ) ); ?>" class="button button-primary creator-start-chat-btn">
							<span class="dashicons dashicons-star-filled"></span>
							<?php esc_html_e( 'Start New Chat', 'creator-core' ); ?>
						</a>
					</div>
					<div class="creator-card-body">
						<div id="creator-conversations-list" class="creator-conversations-list">
							<div class="creator-loading">
								<span class="spinner is-active"></span>
								<?php esc_html_e( 'Loading conversations...', 'creator-core' ); ?>
							</div>
						</div>
						<div id="creator-load-more-wrap" class="creator-load-more-wrap" style="display: none;">
							<button type="button" id="creator-load-more" class="button">
								<?php esc_html_e( 'Load More Conversations', 'creator-core' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>

			<!-- Delete Confirmation Modal -->
			<div id="creator-delete-modal" class="creator-modal" style="display: none;">
				<div class="creator-modal-overlay"></div>
				<div class="creator-modal-content">
					<h3><?php esc_html_e( 'Delete Conversation?', 'creator-core' ); ?></h3>
					<p><?php esc_html_e( 'Are you sure you want to delete this conversation? This action cannot be undone.', 'creator-core' ); ?></p>
					<p id="creator-delete-title" class="creator-delete-title"></p>
					<div class="creator-modal-actions">
						<button type="button" id="creator-cancel-delete" class="button">
							<?php esc_html_e( 'Cancel', 'creator-core' ); ?>
						</button>
						<button type="button" id="creator-confirm-delete" class="button button-primary creator-btn-danger">
							<?php esc_html_e( 'Delete', 'creator-core' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php

		// Register the setting.
		register_setting(
			'creator_dashboard_settings',
			'creator_license_key',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			]
		);
	}

	/**
	 * Get license data for frontend
	 *
	 * @return array
	 */
	private function get_license_data(): array {
		$license_key    = get_option( 'creator_license_key', '' );
		$license_status = get_option( 'creator_license_status', [] );
		$site_token     = get_option( 'creator_site_token', '' );

		$data = [
			'hasKey'     => ! empty( $license_key ),
			'isVerified' => ! empty( $site_token ),
			'status'     => 'unknown',
			'plan'       => '',
			'expiresAt'  => '',
			'siteUrl'    => get_site_url(),
			'daysLeft'   => null,
		];

		if ( ! empty( $license_status ) ) {
			$data['status']    = $license_status['valid'] ?? false ? 'active' : 'invalid';
			$data['plan']      = $license_status['plan'] ?? '';
			$data['expiresAt'] = $license_status['expires_at'] ?? '';

			// Calculate days left.
			if ( ! empty( $license_status['expires_at'] ) ) {
				$expires = strtotime( $license_status['expires_at'] );
				if ( $expires ) {
					$days_left = (int) floor( ( $expires - time() ) / DAY_IN_SECONDS );
					$data['daysLeft'] = max( 0, $days_left );

					// Determine status based on days left.
					if ( $days_left <= 0 ) {
						$data['status'] = 'expired';
					} elseif ( $days_left <= 30 ) {
						$data['status'] = 'expiring';
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Get usage data for frontend
	 *
	 * @return array
	 */
	private function get_usage_data(): array {
		$license_status = get_option( 'creator_license_status', [] );

		// Default values - these would normally come from the proxy/Firebase.
		$data = [
			'tokensUsed'  => 0,
			'tokensLimit' => 50000,
			'resetDate'   => '',
			'percentage'  => 0,
		];

		// If we have usage data from license verification.
		if ( ! empty( $license_status['tokens_used'] ) ) {
			$data['tokensUsed'] = (int) $license_status['tokens_used'];
		}
		if ( ! empty( $license_status['tokens_limit'] ) ) {
			$data['tokensLimit'] = (int) $license_status['tokens_limit'];
		}
		if ( ! empty( $license_status['reset_date'] ) ) {
			$data['resetDate'] = $license_status['reset_date'];
		} elseif ( ! empty( $license_status['expires_at'] ) ) {
			$data['resetDate'] = $license_status['expires_at'];
		}

		// Calculate percentage.
		if ( $data['tokensLimit'] > 0 ) {
			$data['percentage'] = round( ( $data['tokensUsed'] / $data['tokensLimit'] ) * 100, 1 );
		}

		return $data;
	}

	/**
	 * Get system health status
	 *
	 * @return array
	 */
	private function get_system_health(): array {
		$site_token = get_option( 'creator_site_token', '' );

		// Basic health status - actual check is done via AJAX.
		return [
			'firebase' => [
				'status'  => ! empty( $site_token ) ? 'connected' : 'disconnected',
				'label'   => __( 'Firebase', 'creator-core' ),
			],
			'gemini'   => [
				'status'  => ! empty( $site_token ) ? 'active' : 'inactive',
				'label'   => __( 'Gemini 2.5 Pro', 'creator-core' ),
				'model'   => 'gemini-2.5-pro',
			],
			'claude'   => [
				'status'  => ! empty( $site_token ) ? 'active' : 'inactive',
				'label'   => __( 'Claude Opus 4.5', 'creator-core' ),
				'model'   => 'claude-opus-4-5',
			],
		];
	}

	/**
	 * Show notice if license needs verification
	 *
	 * @return void
	 */
	public function maybe_show_license_notice(): void {
		$license_key = get_option( 'creator_license_key', '' );
		$site_token  = get_option( 'creator_site_token', '' );

		if ( ! empty( $license_key ) && empty( $site_token ) ) {
			add_action( 'admin_notices', [ $this, 'show_verify_license_notice' ] );
		}
	}

	/**
	 * Show admin notice to verify license
	 *
	 * @return void
	 */
	public function show_verify_license_notice(): void {
		$dashboard_url = admin_url( 'admin.php?page=creator-dashboard' );
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Creator:', 'creator-core' ); ?></strong>
				<?php esc_html_e( 'Please verify your license to enable AI features.', 'creator-core' ); ?>
				<a href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Go to Dashboard', 'creator-core' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle license key update
	 *
	 * @param mixed $old_value Old option value.
	 * @param mixed $new_value New option value.
	 * @return void
	 */
	public function on_license_key_updated( $old_value, $new_value ): void {
		if ( empty( $new_value ) ) {
			delete_option( 'creator_site_token' );
			delete_option( 'creator_license_status' );
			return;
		}

		if ( $old_value !== $new_value ) {
			delete_option( 'creator_site_token' );
			delete_option( 'creator_license_status' );
		}
	}

	/**
	 * AJAX handler for license verification
	 *
	 * @return void
	 */
	public function ajax_verify_license(): void {
		check_ajax_referer( 'creator_verify_license', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'creator-core' ) ] );
		}

		$license_key = get_option( 'creator_license_key', '' );

		if ( empty( $license_key ) ) {
			wp_send_json_error( [ 'message' => __( 'No license key configured.', 'creator-core' ) ] );
		}

		// Validate with proxy.
		$proxy  = new \CreatorCore\Proxy\ProxyClient();
		$result = $proxy->validate_license( $license_key );

		if ( $result['valid'] ) {
			// Save the JWT site_token from Firebase.
			if ( ! empty( $result['site_token'] ) ) {
				update_option( 'creator_site_token', $result['site_token'] );
			}

			// Save status for display.
			$status_data = [
				'valid'        => true,
				'status'       => __( 'License Valid', 'creator-core' ),
				'expires_at'   => $result['expires_at'] ?? '',
				'plan'         => $result['plan'] ?? 'Standard',
				'tokens_used'  => $result['tokens_used'] ?? 0,
				'tokens_limit' => $result['tokens_limit'] ?? 50000,
				'reset_date'   => $result['reset_date'] ?? '',
				'checked_at'   => current_time( 'mysql' ),
			];
			update_option( 'creator_license_status', $status_data );

			wp_send_json_success( [
				'license' => $this->get_license_data(),
				'usage'   => $this->get_usage_data(),
				'health'  => $this->get_system_health(),
			] );
		} else {
			$status_data = [
				'valid'      => false,
				'status'     => $result['message'] ?? __( 'License Invalid', 'creator-core' ),
				'checked_at' => current_time( 'mysql' ),
			];
			update_option( 'creator_license_status', $status_data );

			wp_send_json_error( [ 'message' => $result['message'] ?? __( 'License validation failed.', 'creator-core' ) ] );
		}
	}

	/**
	 * Get page slug
	 *
	 * @return string
	 */
	public function get_page_slug(): string {
		return $this->page_slug;
	}

	/**
	 * Get hook suffix
	 *
	 * @return string
	 */
	public function get_hook_suffix(): string {
		return $this->hook_suffix;
	}
}
