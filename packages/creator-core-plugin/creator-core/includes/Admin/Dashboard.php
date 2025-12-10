<?php
/**
 * Admin Dashboard
 *
 * @package CreatorCore
 */

namespace CreatorCore\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class Dashboard
 *
 * Handles the admin dashboard display
 * MVP version: Simplified without PluginDetector and SnapshotManager.
 */
class Dashboard {

	/**
	 * Constructor
	 */
	public function __construct() {
		// No dependencies needed for MVP
	}

	/**
	 * Render the dashboard
	 *
	 * @return void
	 */
	public function render(): void {
		$data = $this->get_dashboard_data();
		include CREATOR_CORE_PATH . 'templates/admin-dashboard.php';
	}

	/**
	 * Get dashboard data
	 *
	 * @return array
	 */
	public function get_dashboard_data(): array {
		return [
			'recent_chats'    => $this->get_recent_chats(),
			'stats'           => $this->get_stats(),
			'license_status'  => $this->get_license_status(),
			'quick_actions'   => $this->get_quick_actions(),
		];
	}

	/**
	 * Get recent chats
	 *
	 * @return array
	 */
	private function get_recent_chats(): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*,
				        (SELECT COUNT(*) FROM {$wpdb->prefix}creator_messages WHERE chat_id = c.id) as message_count
				 FROM {$wpdb->prefix}creator_chats c
				 WHERE c.user_id = %d AND c.status = 'active'
				 ORDER BY c.updated_at DESC
				 LIMIT 5",
				get_current_user_id()
			),
			ARRAY_A
		);
	}

	/**
	 * Get statistics
	 *
	 * @return array
	 */
	private function get_stats(): array {
		global $wpdb;

		$user_id = get_current_user_id();

		// Token usage (from proxy or mock)
		$usage       = get_transient( 'creator_license_status' );
		$tokens_used = $usage['usage']['tokens_used'] ?? 0;

		// Chat count
		$chat_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}creator_chats WHERE user_id = %d AND status = 'active'",
				$user_id
			)
		);

		// Message count
		$message_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}creator_messages m
				 JOIN {$wpdb->prefix}creator_chats c ON m.chat_id = c.id
				 WHERE c.user_id = %d",
				$user_id
			)
		);

		return [
			'tokens_used'      => $tokens_used,
			'tokens_formatted' => number_format( $tokens_used ),
			'chat_count'       => (int) $chat_count,
			'message_count'    => (int) $message_count,
		];
	}

	/**
	 * Get license status
	 *
	 * @return array
	 */
	private function get_license_status(): array {
		$status = get_transient( 'creator_license_status' );

		if ( ! $status ) {
			return [
				'valid'      => false,
				'plan'       => 'none',
				'expires_at' => null,
			];
		}

		return [
			'valid'      => $status['success'] ?? false,
			'plan'       => $status['plan'] ?? 'unknown',
			'expires_at' => $status['expires_at'] ?? null,
			'features'   => $status['features'] ?? [],
		];
	}

	/**
	 * Get quick actions
	 *
	 * @return array
	 */
	private function get_quick_actions(): array {
		return [
			[
				'label' => __( 'New Chat', 'creator-core' ),
				'url'   => admin_url( 'admin.php?page=creator-chat' ),
				'icon'  => 'dashicons-format-chat',
			],
			[
				'label' => __( 'Create Page', 'creator-core' ),
				'url'   => admin_url( 'admin.php?page=creator-chat&action=create_page' ),
				'icon'  => 'dashicons-admin-page',
			],
			[
				'label' => __( 'Create Post', 'creator-core' ),
				'url'   => admin_url( 'admin.php?page=creator-chat&action=create_post' ),
				'icon'  => 'dashicons-admin-post',
			],
			[
				'label' => __( 'Settings', 'creator-core' ),
				'url'   => admin_url( 'admin.php?page=creator-settings' ),
				'icon'  => 'dashicons-admin-generic',
			],
		];
	}
}
