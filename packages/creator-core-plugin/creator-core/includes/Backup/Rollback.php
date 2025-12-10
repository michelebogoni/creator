<?php
/**
 * Rollback Stub
 *
 * Placeholder class for rollback functionality.
 * Will be reimplemented in Phase 2.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Backup;

defined( 'ABSPATH' ) || exit;

/**
 * Class Rollback
 *
 * Stub class that provides no-op rollback methods.
 */
class Rollback {

	/**
	 * Constructor
	 *
	 * @param SnapshotManager|null $snapshot_manager Snapshot manager instance (ignored).
	 * @param mixed                $logger           Logger instance (ignored).
	 */
	public function __construct( ?SnapshotManager $snapshot_manager = null, $logger = null ) {
		// No dependencies needed for stub
	}

	/**
	 * Rollback a snapshot (no-op)
	 *
	 * @param int $snapshot_id Snapshot ID.
	 * @return array Result indicating feature unavailable.
	 */
	public function rollback_snapshot( int $snapshot_id ): array {
		return [
			'success' => false,
			'message' => __( 'Rollback functionality is temporarily unavailable.', 'creator-core' ),
			'errors'  => [],
		];
	}
}
