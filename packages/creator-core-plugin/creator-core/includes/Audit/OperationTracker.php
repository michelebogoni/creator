<?php
/**
 * Operation Tracker Stub
 *
 * Placeholder class for operation tracking functionality.
 * Will be reimplemented in Phase 2.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Class OperationTracker
 *
 * Stub class that provides no-op tracking methods.
 */
class OperationTracker {

	/**
	 * Track an operation (no-op)
	 *
	 * @param string $operation Operation name.
	 * @param array  $data      Operation data.
	 * @return int Always returns 0.
	 */
	public function track( string $operation, array $data = [] ): int {
		return 0;
	}

	/**
	 * Get recent operations (returns empty array)
	 *
	 * @param int $limit Number of operations to return.
	 * @return array Empty array.
	 */
	public function get_recent( int $limit = 10 ): array {
		return [];
	}
}
