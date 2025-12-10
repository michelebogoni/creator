<?php
/**
 * Audit Logger Stub
 *
 * Placeholder class for audit logging functionality.
 * Will be reimplemented in Phase 2.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Class AuditLogger
 *
 * Stub class that provides no-op logging methods.
 */
class AuditLogger {

	/**
	 * Log a message (no-op)
	 *
	 * @param string $event   Event name.
	 * @param string $level   Log level.
	 * @param array  $context Additional context.
	 */
	public function log( string $event, string $level = 'info', array $context = [] ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "Creator [$level]: $event " . wp_json_encode( $context ) );
		}
	}

	/**
	 * Log success (no-op)
	 *
	 * @param string $event   Event name.
	 * @param array  $context Additional context.
	 */
	public function success( string $event, array $context = [] ): void {
		$this->log( $event, 'success', $context );
	}

	/**
	 * Log warning (no-op)
	 *
	 * @param string $event   Event name.
	 * @param array  $context Additional context.
	 */
	public function warning( string $event, array $context = [] ): void {
		$this->log( $event, 'warning', $context );
	}

	/**
	 * Log failure (no-op)
	 *
	 * @param string $event   Event name.
	 * @param array  $context Additional context.
	 */
	public function failure( string $event, array $context = [] ): void {
		$this->log( $event, 'failure', $context );
	}

	/**
	 * Log info (no-op)
	 *
	 * @param string $event   Event name.
	 * @param array  $context Additional context.
	 */
	public function info( string $event, array $context = [] ): void {
		$this->log( $event, 'info', $context );
	}
}
