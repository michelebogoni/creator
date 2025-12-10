<?php
/**
 * Snapshot Manager Stub
 *
 * Placeholder class for snapshot management functionality.
 * Will be reimplemented in Phase 2.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Backup;

defined( 'ABSPATH' ) || exit;

/**
 * Class SnapshotManager
 *
 * Stub class that provides no-op snapshot methods.
 */
class SnapshotManager {

	/**
	 * Constructor
	 *
	 * @param mixed $logger Optional logger instance (ignored in stub).
	 */
	public function __construct( $logger = null ) {
		// No dependencies needed for stub
	}

	/**
	 * Create a snapshot (no-op)
	 *
	 * @param int   $message_id Message ID.
	 * @param array $operations Operations to snapshot.
	 * @return int|false Always returns false.
	 */
	public function create_snapshot( int $message_id, array $operations ): int|false {
		return false;
	}

	/**
	 * Get message snapshot (returns null)
	 *
	 * @param int $message_id Message ID.
	 * @return array|null Always returns null.
	 */
	public function get_message_snapshot( int $message_id ): ?array {
		return null;
	}

	/**
	 * Cleanup old snapshots (no-op)
	 *
	 * @param int $retention_days Retention period.
	 * @return int Always returns 0.
	 */
	public function cleanup_old_snapshots( int $retention_days = 30 ): int {
		return 0;
	}

	/**
	 * Enforce size limit (no-op)
	 *
	 * @param int $max_size_mb Maximum size in MB.
	 * @return int Always returns 0.
	 */
	public function enforce_size_limit( int $max_size_mb = 500 ): int {
		return 0;
	}
}
