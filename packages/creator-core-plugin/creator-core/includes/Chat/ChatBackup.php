<?php
/**
 * Chat Backup System
 *
 * Complete backup of chat conversations for debugging AI reasoning and process errors.
 * Stores full conversation history including:
 * - User messages
 * - AI responses (full content)
 * - System context sent to AI
 * - AI model/provider used
 * - Token counts and timing
 * - Any errors or fallback events
 *
 * @package CreatorCore
 * @since 1.1.0
 */

namespace CreatorCore\Chat;

defined( 'ABSPATH' ) || exit;

/**
 * Class ChatBackup
 *
 * Handles complete chat conversation backups for debugging purposes.
 */
class ChatBackup {

	/**
	 * Backup storage directory relative to uploads
	 */
	const BACKUP_DIR = 'creator-chat-logs';

	/**
	 * Maximum age of backups in days before cleanup
	 */
	const RETENTION_DAYS = 90;

	/**
	 * Get the backup directory path
	 *
	 * @return string Full path to backup directory.
	 */
	public static function get_backup_dir(): string {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . self::BACKUP_DIR;
	}

	/**
	 * Ensure backup directory exists with proper permissions
	 *
	 * @return bool True if directory exists or was created.
	 */
	public static function ensure_backup_dir(): bool {
		$dir = self::get_backup_dir();

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );

			// Add .htaccess to prevent direct access
			$htaccess = $dir . '/.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				file_put_contents( $htaccess, "Deny from all\n" );
			}

			// Add index.php for extra security
			$index = $dir . '/index.php';
			if ( ! file_exists( $index ) ) {
				file_put_contents( $index, "<?php\n// Silence is golden.\n" );
			}
		}

		return is_dir( $dir ) && is_writable( $dir );
	}

	/**
	 * Log a complete message exchange to backup
	 *
	 * @param int    $chat_id     Chat ID.
	 * @param int    $message_id  Message ID.
	 * @param array  $user_message User message data.
	 * @param array  $ai_response  AI response data.
	 * @param array  $context      Context sent to AI.
	 * @param array  $metadata     Additional metadata (model, tokens, timing, etc).
	 * @return bool True on success.
	 */
	public static function log_exchange(
		int $chat_id,
		int $message_id,
		array $user_message,
		array $ai_response,
		array $context = [],
		array $metadata = []
	): bool {
		if ( ! self::ensure_backup_dir() ) {
			return false;
		}

		$exchange = [
			'chat_id'      => $chat_id,
			'message_id'   => $message_id,
			'timestamp'    => current_time( 'mysql' ),
			'timestamp_gmt' => current_time( 'mysql', true ),
			'user_id'      => get_current_user_id(),

			// User message
			'user_message' => [
				'content'    => $user_message['content'] ?? '',
				'role'       => 'user',
				'created_at' => $user_message['created_at'] ?? current_time( 'mysql' ),
			],

			// AI response
			'ai_response'  => [
				'content'      => $ai_response['content'] ?? '',
				'role'         => 'assistant',
				'actions'      => $ai_response['actions'] ?? [],
				'thinking'     => $ai_response['thinking'] ?? null,
				'created_at'   => $ai_response['created_at'] ?? current_time( 'mysql' ),
			],

			// Context sent to AI
			'context'      => [
				'system_prompt'  => $context['system_prompt'] ?? '',
				'site_context'   => $context['site_context'] ?? '',
				'chat_history'   => $context['chat_history'] ?? [],
				'context_size'   => strlen( json_encode( $context ) ),
			],

			// Metadata
			'metadata'     => [
				'provider'       => $metadata['provider'] ?? 'unknown',
				'model'          => $metadata['model'] ?? 'unknown',
				'input_tokens'   => $metadata['input_tokens'] ?? 0,
				'output_tokens'  => $metadata['output_tokens'] ?? 0,
				'total_tokens'   => $metadata['total_tokens'] ?? 0,
				'duration_ms'    => $metadata['duration_ms'] ?? 0,
				'fallback_used'  => $metadata['fallback_used'] ?? false,
				'fallback_reason' => $metadata['fallback_reason'] ?? null,
				'error'          => $metadata['error'] ?? null,
				'request_id'     => $metadata['request_id'] ?? wp_generate_uuid4(),
			],

			// Environment info for debugging
			'environment'  => [
				'wp_version'     => get_bloginfo( 'version' ),
				'php_version'    => PHP_VERSION,
				'plugin_version' => defined( 'CREATOR_CORE_VERSION' ) ? CREATOR_CORE_VERSION : 'unknown',
				'site_url'       => get_site_url(),
				'memory_usage'   => memory_get_peak_usage( true ),
			],
		];

		// Write to daily log file
		$filename = self::get_log_filename( $chat_id );
		$filepath = self::get_backup_dir() . '/' . $filename;

		// Append to existing file or create new
		$existing = [];
		if ( file_exists( $filepath ) ) {
			$content = file_get_contents( $filepath );
			$existing = json_decode( $content, true ) ?: [];
		}

		$existing[] = $exchange;

		$result = file_put_contents(
			$filepath,
			wp_json_encode( $existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
			LOCK_EX
		);

		return $result !== false;
	}

	/**
	 * Log an error event
	 *
	 * @param int    $chat_id    Chat ID.
	 * @param string $error_type Error type (api_error, parse_error, execution_error, etc).
	 * @param string $message    Error message.
	 * @param array  $details    Additional details.
	 * @return bool True on success.
	 */
	public static function log_error(
		int $chat_id,
		string $error_type,
		string $message,
		array $details = []
	): bool {
		if ( ! self::ensure_backup_dir() ) {
			return false;
		}

		$error_log = [
			'chat_id'       => $chat_id,
			'timestamp'     => current_time( 'mysql' ),
			'timestamp_gmt' => current_time( 'mysql', true ),
			'user_id'       => get_current_user_id(),
			'type'          => 'error',
			'error_type'    => $error_type,
			'message'       => $message,
			'details'       => $details,
			'stack_trace'   => debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 10 ),
		];

		$filename = self::get_log_filename( $chat_id );
		$filepath = self::get_backup_dir() . '/' . $filename;

		$existing = [];
		if ( file_exists( $filepath ) ) {
			$content = file_get_contents( $filepath );
			$existing = json_decode( $content, true ) ?: [];
		}

		$existing[] = $error_log;

		$result = file_put_contents(
			$filepath,
			wp_json_encode( $existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
			LOCK_EX
		);

		return $result !== false;
	}

	/**
	 * Get chat backup logs
	 *
	 * @param int      $chat_id Chat ID.
	 * @param int|null $limit   Maximum entries to return (null for all).
	 * @param int      $offset  Offset for pagination.
	 * @return array Array of log entries.
	 */
	public static function get_chat_logs( int $chat_id, ?int $limit = null, int $offset = 0 ): array {
		$filename = self::get_log_filename( $chat_id );
		$filepath = self::get_backup_dir() . '/' . $filename;

		if ( ! file_exists( $filepath ) ) {
			return [];
		}

		$content = file_get_contents( $filepath );
		$logs = json_decode( $content, true ) ?: [];

		// Sort by timestamp descending (newest first)
		usort( $logs, function( $a, $b ) {
			return strtotime( $b['timestamp'] ?? 0 ) - strtotime( $a['timestamp'] ?? 0 );
		});

		// Apply pagination
		if ( $limit !== null ) {
			$logs = array_slice( $logs, $offset, $limit );
		}

		return $logs;
	}

	/**
	 * Get all backup files for a chat
	 *
	 * @param int $chat_id Chat ID.
	 * @return array Array of backup file info.
	 */
	public static function get_chat_backup_files( int $chat_id ): array {
		$dir = self::get_backup_dir();
		$pattern = $dir . "/chat-{$chat_id}-*.json";
		$files = glob( $pattern );

		$result = [];
		foreach ( $files as $file ) {
			$result[] = [
				'filename'   => basename( $file ),
				'filepath'   => $file,
				'size'       => filesize( $file ),
				'modified'   => filemtime( $file ),
				'entries'    => count( json_decode( file_get_contents( $file ), true ) ?: [] ),
			];
		}

		// Sort by modified date descending
		usort( $result, function( $a, $b ) {
			return $b['modified'] - $a['modified'];
		});

		return $result;
	}

	/**
	 * Export chat backup as downloadable JSON
	 *
	 * @param int $chat_id Chat ID.
	 * @return array Export data with content and filename.
	 */
	public static function export_chat_backup( int $chat_id ): array {
		$logs = self::get_chat_logs( $chat_id );

		// Get chat metadata
		global $wpdb;
		$chat = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}creator_chats WHERE id = %d",
				$chat_id
			),
			ARRAY_A
		);

		$export = [
			'export_version' => '1.0',
			'export_date'    => current_time( 'mysql' ),
			'chat'           => $chat,
			'logs'           => $logs,
			'summary'        => [
				'total_exchanges' => count( array_filter( $logs, fn( $l ) => ( $l['type'] ?? 'exchange' ) !== 'error' ) ),
				'total_errors'    => count( array_filter( $logs, fn( $l ) => ( $l['type'] ?? '' ) === 'error' ) ),
				'providers_used'  => array_unique( array_column( array_column( $logs, 'metadata' ), 'provider' ) ),
				'total_tokens'    => array_sum( array_column( array_column( $logs, 'metadata' ), 'total_tokens' ) ),
			],
		];

		return [
			'content'  => wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
			'filename' => "creator-chat-{$chat_id}-export-" . gmdate( 'Y-m-d-His' ) . '.json',
			'mimetype' => 'application/json',
		];
	}

	/**
	 * Delete chat backup
	 *
	 * @param int $chat_id Chat ID.
	 * @return bool True if deleted.
	 */
	public static function delete_chat_backup( int $chat_id ): bool {
		$files = self::get_chat_backup_files( $chat_id );

		foreach ( $files as $file ) {
			if ( file_exists( $file['filepath'] ) ) {
				unlink( $file['filepath'] );
			}
		}

		return true;
	}

	/**
	 * Cleanup old backups based on retention policy
	 *
	 * @param int $days Number of days to retain (default: RETENTION_DAYS).
	 * @return array Cleanup statistics.
	 */
	public static function cleanup_old_backups( int $days = self::RETENTION_DAYS ): array {
		$dir = self::get_backup_dir();

		if ( ! is_dir( $dir ) ) {
			return [ 'deleted' => 0, 'freed_bytes' => 0 ];
		}

		$cutoff = time() - ( $days * DAY_IN_SECONDS );
		$deleted = 0;
		$freed_bytes = 0;

		$files = glob( $dir . '/chat-*.json' );

		foreach ( $files as $file ) {
			if ( filemtime( $file ) < $cutoff ) {
				$size = filesize( $file );
				if ( unlink( $file ) ) {
					$deleted++;
					$freed_bytes += $size;
				}
			}
		}

		return [
			'deleted'     => $deleted,
			'freed_bytes' => $freed_bytes,
			'freed_mb'    => round( $freed_bytes / 1024 / 1024, 2 ),
		];
	}

	/**
	 * Get backup statistics
	 *
	 * @return array Statistics array.
	 */
	public static function get_stats(): array {
		$dir = self::get_backup_dir();

		if ( ! is_dir( $dir ) ) {
			return [
				'total_files'   => 0,
				'total_size'    => 0,
				'total_size_mb' => 0,
				'oldest_file'   => null,
				'newest_file'   => null,
			];
		}

		$files = glob( $dir . '/chat-*.json' );
		$total_size = 0;
		$oldest = PHP_INT_MAX;
		$newest = 0;

		foreach ( $files as $file ) {
			$total_size += filesize( $file );
			$mtime = filemtime( $file );
			$oldest = min( $oldest, $mtime );
			$newest = max( $newest, $mtime );
		}

		return [
			'total_files'   => count( $files ),
			'total_size'    => $total_size,
			'total_size_mb' => round( $total_size / 1024 / 1024, 2 ),
			'oldest_file'   => $oldest < PHP_INT_MAX ? gmdate( 'Y-m-d H:i:s', $oldest ) : null,
			'newest_file'   => $newest > 0 ? gmdate( 'Y-m-d H:i:s', $newest ) : null,
			'retention_days' => self::RETENTION_DAYS,
		];
	}

	/**
	 * Get log filename for a chat
	 *
	 * @param int $chat_id Chat ID.
	 * @return string Filename.
	 */
	private static function get_log_filename( int $chat_id ): string {
		// One file per chat per month for manageable file sizes
		$month = gmdate( 'Y-m' );
		return "chat-{$chat_id}-{$month}.json";
	}

	/**
	 * Search logs for debugging
	 *
	 * @param array $criteria Search criteria (chat_id, error_type, provider, date_from, date_to).
	 * @param int   $limit    Maximum results.
	 * @return array Matching log entries.
	 */
	public static function search_logs( array $criteria, int $limit = 100 ): array {
		$dir = self::get_backup_dir();

		if ( ! is_dir( $dir ) ) {
			return [];
		}

		$results = [];
		$files = glob( $dir . '/chat-*.json' );

		// Filter by chat_id if specified
		if ( ! empty( $criteria['chat_id'] ) ) {
			$chat_id = (int) $criteria['chat_id'];
			$files = array_filter( $files, fn( $f ) => strpos( $f, "chat-{$chat_id}-" ) !== false );
		}

		foreach ( $files as $file ) {
			$content = file_get_contents( $file );
			$logs = json_decode( $content, true ) ?: [];

			foreach ( $logs as $log ) {
				// Apply filters
				if ( ! empty( $criteria['error_type'] ) && ( $log['error_type'] ?? '' ) !== $criteria['error_type'] ) {
					continue;
				}

				if ( ! empty( $criteria['provider'] ) && ( $log['metadata']['provider'] ?? '' ) !== $criteria['provider'] ) {
					continue;
				}

				if ( ! empty( $criteria['date_from'] ) ) {
					if ( strtotime( $log['timestamp'] ?? '' ) < strtotime( $criteria['date_from'] ) ) {
						continue;
					}
				}

				if ( ! empty( $criteria['date_to'] ) ) {
					if ( strtotime( $log['timestamp'] ?? '' ) > strtotime( $criteria['date_to'] ) ) {
						continue;
					}
				}

				$results[] = $log;

				if ( count( $results ) >= $limit ) {
					break 2;
				}
			}
		}

		// Sort by timestamp descending
		usort( $results, function( $a, $b ) {
			return strtotime( $b['timestamp'] ?? 0 ) - strtotime( $a['timestamp'] ?? 0 );
		});

		return $results;
	}
}
