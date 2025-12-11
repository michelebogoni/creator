<?php
/**
 * WP-CLI Executor
 *
 * Executes WP-CLI commands in a safe, controlled manner.
 * Only whitelisted command patterns are allowed.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Execution;

defined( 'ABSPATH' ) || exit;

/**
 * Class WPCLIExecutor
 *
 * Provides safe execution of WP-CLI commands from within WordPress.
 */
class WPCLIExecutor {

	/**
	 * Allowed command prefixes (whitelist approach)
	 *
	 * Only commands starting with these prefixes are allowed.
	 * This prevents dangerous commands like `wp db drop` or `wp site delete`.
	 *
	 * @var array
	 */
	private const ALLOWED_PREFIXES = [
		// Content management.
		'wp post',
		'wp page',
		'wp media',
		'wp menu',
		'wp widget',
		'wp sidebar',
		'wp comment',

		// Taxonomy and terms.
		'wp term',
		'wp taxonomy',

		// Users (limited).
		'wp user list',
		'wp user get',
		'wp user meta',

		// Options.
		'wp option get',
		'wp option list',
		'wp option update',
		'wp option add',

		// Plugins (read-only and safe operations).
		'wp plugin list',
		'wp plugin get',
		'wp plugin is-installed',
		'wp plugin is-active',
		'wp plugin path',

		// Themes (read-only and safe operations).
		'wp theme list',
		'wp theme get',
		'wp theme is-installed',
		'wp theme is-active',
		'wp theme path',

		// Transients.
		'wp transient',

		// Cache (safe operations).
		'wp cache flush',
		'wp cache get',
		'wp cache set',

		// Rewrite rules.
		'wp rewrite flush',
		'wp rewrite list',

		// Cron.
		'wp cron event list',
		'wp cron event run',
		'wp cron schedule list',

		// Search-replace (with dry-run).
		'wp search-replace',

		// Export.
		'wp export',

		// Plugin-specific commands (when plugins register them).
		'wp wc',           // WooCommerce.
		'wp acf',          // ACF.
		'wp elementor',    // Elementor.
		'wp yoast',        // Yoast SEO.
		'wp wpcode',       // WPCode (if available).
		'wp code-snippets', // Code Snippets plugin.
	];

	/**
	 * Blocked command patterns (blacklist for extra safety)
	 *
	 * These patterns are NEVER allowed, even if the prefix is whitelisted.
	 *
	 * @var array
	 */
	private const BLOCKED_PATTERNS = [
		'--allow-root',
		'--skip-themes',
		'--skip-plugins',
		'eval',
		'eval-file',
		'shell',
		'db drop',
		'db reset',
		'db clean',
		'site delete',
		'site empty',
		'multisite-delete',
		'core update',
		'core download',
		'plugin install',
		'plugin delete',
		'plugin update',
		'theme install',
		'theme delete',
		'theme update',
		'user create',
		'user delete',
		'user update',
		'config',
		'package',
		'server',
		'> ',    // Output redirection.
		'>> ',   // Output append.
		'| ',    // Pipe.
		'; ',    // Command chaining.
		'&& ',   // Command chaining.
		'|| ',   // Command chaining.
		'`',     // Command substitution.
		'$(',    // Command substitution.
	];

	/**
	 * Maximum execution time for WP-CLI commands (seconds)
	 *
	 * @var int
	 */
	private const MAX_EXECUTION_TIME = 30;

	/**
	 * Path to WP-CLI executable
	 *
	 * @var string|null
	 */
	private ?string $wp_cli_path = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->wp_cli_path = $this->find_wp_cli_path();
	}

	/**
	 * Execute a WP-CLI command safely
	 *
	 * @param string $command The WP-CLI command to execute (without 'wp' prefix if present).
	 * @return array Execution result with 'success', 'output', 'error' keys.
	 */
	public function execute( string $command ): array {
		// Normalize command (ensure it starts with 'wp ').
		$command = $this->normalize_command( $command );

		// Validate the command is allowed.
		$validation = $this->validate_command( $command );
		if ( ! $validation['allowed'] ) {
			return [
				'success' => false,
				'output'  => '',
				'error'   => $validation['reason'],
				'command' => $command,
			];
		}

		// Check WP-CLI availability.
		if ( empty( $this->wp_cli_path ) ) {
			return [
				'success' => false,
				'output'  => '',
				'error'   => 'WP-CLI is not available on this server. Please install WP-CLI or use an alternative method.',
				'command' => $command,
			];
		}

		// Execute the command.
		return $this->run_command( $command );
	}

	/**
	 * Check if WP-CLI is available
	 *
	 * @return bool True if WP-CLI is available.
	 */
	public function is_available(): bool {
		return ! empty( $this->wp_cli_path );
	}

	/**
	 * Get WP-CLI version
	 *
	 * @return string|null WP-CLI version or null if not available.
	 */
	public function get_version(): ?string {
		if ( ! $this->is_available() ) {
			return null;
		}

		$result = $this->run_command( 'wp cli version' );
		if ( $result['success'] ) {
			return trim( $result['output'] );
		}

		return null;
	}

	/**
	 * Normalize command to ensure consistent format
	 *
	 * @param string $command The command to normalize.
	 * @return string Normalized command.
	 */
	private function normalize_command( string $command ): string {
		$command = trim( $command );

		// Remove 'wp ' prefix if present (we'll add it back).
		if ( str_starts_with( $command, 'wp ' ) ) {
			$command = substr( $command, 3 );
		}

		// Add 'wp ' prefix.
		return 'wp ' . $command;
	}

	/**
	 * Validate if a command is allowed to execute
	 *
	 * @param string $command The command to validate.
	 * @return array Validation result with 'allowed' and 'reason' keys.
	 */
	private function validate_command( string $command ): array {
		// Check against blocked patterns first.
		foreach ( self::BLOCKED_PATTERNS as $pattern ) {
			if ( stripos( $command, $pattern ) !== false ) {
				return [
					'allowed' => false,
					'reason'  => sprintf(
						'Command contains blocked pattern: %s. This operation is not allowed for security reasons.',
						$pattern
					),
				];
			}
		}

		// Check if command starts with an allowed prefix.
		$is_allowed = false;
		foreach ( self::ALLOWED_PREFIXES as $prefix ) {
			if ( str_starts_with( $command, $prefix ) ) {
				$is_allowed = true;
				break;
			}
		}

		if ( ! $is_allowed ) {
			return [
				'allowed' => false,
				'reason'  => sprintf(
					'Command "%s" is not in the allowed list. Only safe WP-CLI commands are permitted.',
					explode( ' ', $command )[0] . ' ' . ( explode( ' ', $command )[1] ?? '' )
				),
			];
		}

		return [
			'allowed' => true,
			'reason'  => '',
		];
	}

	/**
	 * Find the WP-CLI executable path
	 *
	 * @return string|null Path to WP-CLI or null if not found.
	 */
	private function find_wp_cli_path(): ?string {
		// Common WP-CLI locations.
		$possible_paths = [
			'/usr/local/bin/wp',
			'/usr/bin/wp',
			'/opt/wp-cli/wp',
			ABSPATH . 'wp-cli.phar',
			dirname( ABSPATH ) . '/wp-cli.phar',
		];

		// Check each path.
		foreach ( $possible_paths as $path ) {
			if ( file_exists( $path ) && is_executable( $path ) ) {
				return $path;
			}
		}

		// Try to find via 'which' command.
		$which_result = @shell_exec( 'which wp 2>/dev/null' );
		if ( ! empty( $which_result ) ) {
			$path = trim( $which_result );
			if ( file_exists( $path ) && is_executable( $path ) ) {
				return $path;
			}
		}

		return null;
	}

	/**
	 * Run the WP-CLI command
	 *
	 * @param string $command The validated command to run.
	 * @return array Execution result.
	 */
	private function run_command( string $command ): array {
		// Build the full command with path and WordPress path.
		$wp_path     = ABSPATH;
		$full_command = sprintf(
			'%s %s --path=%s --format=json 2>&1',
			escapeshellcmd( $this->wp_cli_path ),
			$this->escape_command_args( substr( $command, 3 ) ), // Remove 'wp ' prefix.
			escapeshellarg( $wp_path )
		);

		// Set execution time limit.
		$original_time_limit = ini_get( 'max_execution_time' );
		set_time_limit( self::MAX_EXECUTION_TIME + 5 );

		// Execute.
		$output      = [];
		$return_code = 0;

		exec( $full_command, $output, $return_code );

		// Restore time limit.
		set_time_limit( (int) $original_time_limit );

		// Process output.
		$output_string = implode( "\n", $output );

		// Try to decode JSON output.
		$decoded = json_decode( $output_string, true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			$output_string = $decoded;
		}

		if ( $return_code === 0 ) {
			return [
				'success' => true,
				'output'  => $output_string,
				'error'   => '',
				'command' => $command,
			];
		}

		return [
			'success' => false,
			'output'  => $output_string,
			'error'   => sprintf( 'Command failed with exit code %d', $return_code ),
			'command' => $command,
		];
	}

	/**
	 * Escape command arguments safely
	 *
	 * @param string $args The command arguments.
	 * @return string Escaped arguments.
	 */
	private function escape_command_args( string $args ): string {
		// Split by spaces, but respect quoted strings.
		preg_match_all( '/(?:[^\s"\']+|"[^"]*"|\'[^\']*\')+/', $args, $matches );
		$parts = $matches[0] ?? [];

		$escaped = [];
		foreach ( $parts as $part ) {
			// If already quoted, leave as is.
			if ( preg_match( '/^["\'].*["\']$/', $part ) ) {
				$escaped[] = $part;
			} else {
				$escaped[] = escapeshellarg( $part );
			}
		}

		return implode( ' ', $escaped );
	}

	/**
	 * Get list of allowed command prefixes (for documentation/debugging)
	 *
	 * @return array List of allowed prefixes.
	 */
	public function get_allowed_prefixes(): array {
		return self::ALLOWED_PREFIXES;
	}
}
