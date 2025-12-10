<?php
/**
 * Capability Checker Stub
 *
 * Placeholder class for capability checking functionality.
 * Will be reimplemented in Phase 2.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Permission;

defined( 'ABSPATH' ) || exit;

/**
 * Class CapabilityChecker
 *
 * Stub class that uses simple WordPress capability checks.
 */
class CapabilityChecker {

	/**
	 * Check if current user can use Creator
	 *
	 * @return bool
	 */
	public function can_use_creator(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if current user can execute code
	 *
	 * @return bool
	 */
	public function can_execute_code(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if current user can manage files
	 *
	 * @return bool
	 */
	public function can_manage_files(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if current user can manage database
	 *
	 * @return bool
	 */
	public function can_manage_database(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Register capabilities (no-op for stub)
	 */
	public function register_capabilities(): void {
		// No custom capabilities in stub
	}
}
