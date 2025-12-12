<?php
/**
 * Dashboard REST API
 *
 * REST API endpoints for the Creator Dashboard.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Admin;

defined( 'ABSPATH' ) || exit;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class DashboardAPI
 *
 * REST API endpoints for dashboard functionality
 */
class DashboardAPI {

	/**
	 * API namespace
	 *
	 * @var string
	 */
	private string $namespace = 'creator/v1';

	/**
	 * Register REST routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /dashboard/conversations.
		register_rest_route(
			$this->namespace,
			'/dashboard/conversations',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_conversations' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'page'     => [
						'default'           => 1,
						'sanitize_callback' => 'absint',
					],
					'per_page' => [
						'default'           => 10,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		// DELETE /dashboard/conversations/:id.
		register_rest_route(
			$this->namespace,
			'/dashboard/conversations/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_conversation' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		// POST /dashboard/conversations/:id/generate-title.
		register_rest_route(
			$this->namespace,
			'/dashboard/conversations/(?P<id>\d+)/generate-title',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'generate_title' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		// GET /dashboard/system-health.
		register_rest_route(
			$this->namespace,
			'/dashboard/system-health',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_system_health' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Check if user has permission
	 *
	 * @return bool|WP_Error
	 */
	public function check_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this resource.', 'creator-core' ),
				[ 'status' => 403 ]
			);
		}
		return true;
	}

	/**
	 * Get conversations list
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_conversations( WP_REST_Request $request ) {
		global $wpdb;

		$page     = max( 1, $request->get_param( 'page' ) );
		$per_page = min( 50, max( 1, $request->get_param( 'per_page' ) ) );
		$offset   = ( $page - 1 ) * $per_page;
		$user_id  = get_current_user_id();

		$chats_table    = $wpdb->prefix . 'creator_chats';
		$messages_table = $wpdb->prefix . 'creator_messages';

		// Get conversations with message count.
		$conversations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					c.id,
					c.title,
					c.created_at,
					c.updated_at,
					(SELECT COUNT(*) FROM {$messages_table} WHERE chat_id = c.id) as message_count,
					(SELECT content FROM {$messages_table} WHERE chat_id = c.id AND role = 'user' ORDER BY created_at ASC LIMIT 1) as first_message,
					(SELECT content FROM {$messages_table} WHERE chat_id = c.id AND role = 'assistant' ORDER BY created_at DESC LIMIT 1) as last_response
				FROM {$chats_table} c
				WHERE c.user_id = %d AND c.status = 'active'
				ORDER BY c.updated_at DESC
				LIMIT %d OFFSET %d",
				$user_id,
				$per_page + 1, // Get one extra to check if there's more.
				$offset
			),
			ARRAY_A
		);

		if ( ! $conversations ) {
			$conversations = [];
		}

		// Check if there are more conversations.
		$has_more = count( $conversations ) > $per_page;
		if ( $has_more ) {
			array_pop( $conversations ); // Remove the extra item.
		}

		// Format conversations.
		$formatted = array_map( [ $this, 'format_conversation' ], $conversations );

		return new WP_REST_Response(
			[
				'success'       => true,
				'conversations' => $formatted,
				'has_more'      => $has_more,
				'page'          => $page,
				'per_page'      => $per_page,
			],
			200
		);
	}

	/**
	 * Format a conversation for the response
	 *
	 * @param array $conversation Raw conversation data.
	 * @return array Formatted conversation.
	 */
	private function format_conversation( array $conversation ): array {
		$title = $conversation['title'];

		// If title is default, use first message.
		if ( empty( $title ) || $title === __( 'New Chat', 'creator-core' ) ) {
			$title = $this->truncate_text( $conversation['first_message'] ?? '', 60 );
			if ( empty( $title ) ) {
				$title = __( 'New Conversation', 'creator-core' );
			}
		}

		// Generate summary from last response.
		$summary = '';
		if ( ! empty( $conversation['last_response'] ) ) {
			$summary = $this->truncate_text( $conversation['last_response'], 150 );
		}

		// Format date.
		$date_relative = $this->get_relative_date( $conversation['updated_at'] );

		return [
			'id'            => (int) $conversation['id'],
			'title'         => $title,
			'summary'       => $summary,
			'message_count' => (int) $conversation['message_count'],
			'date_relative' => $date_relative,
			'created_at'    => $conversation['created_at'],
			'updated_at'    => $conversation['updated_at'],
			'needs_title'   => empty( $conversation['title'] ) || $conversation['title'] === __( 'New Chat', 'creator-core' ),
		];
	}

	/**
	 * Truncate text to specified length
	 *
	 * @param string $text   Text to truncate.
	 * @param int    $length Maximum length.
	 * @return string Truncated text.
	 */
	private function truncate_text( string $text, int $length ): string {
		// Remove any HTML/markdown.
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		if ( mb_strlen( $text ) <= $length ) {
			return $text;
		}

		return mb_substr( $text, 0, $length - 3 ) . '...';
	}

	/**
	 * Get relative date string
	 *
	 * @param string $date MySQL datetime string.
	 * @return string Relative date.
	 */
	private function get_relative_date( string $date ): string {
		$timestamp = strtotime( $date );
		$now       = current_time( 'timestamp' );
		$diff      = $now - $timestamp;

		$today_start     = strtotime( 'today', $now );
		$yesterday_start = strtotime( 'yesterday', $now );

		if ( $timestamp >= $today_start ) {
			// Today - show time.
			return sprintf(
				/* translators: %s: time */
				__( 'Today, %s', 'creator-core' ),
				date_i18n( get_option( 'time_format' ), $timestamp )
			);
		} elseif ( $timestamp >= $yesterday_start ) {
			return sprintf(
				/* translators: %s: time */
				__( 'Yesterday, %s', 'creator-core' ),
				date_i18n( get_option( 'time_format' ), $timestamp )
			);
		} elseif ( $diff < WEEK_IN_SECONDS ) {
			$days = (int) floor( $diff / DAY_IN_SECONDS );
			return sprintf(
				/* translators: %d: number of days */
				_n( '%d day ago', '%d days ago', $days, 'creator-core' ),
				$days
			);
		} else {
			// Show full date.
			return date_i18n( get_option( 'date_format' ), $timestamp );
		}
	}

	/**
	 * Delete a conversation
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_conversation( WP_REST_Request $request ) {
		global $wpdb;

		$chat_id = $request->get_param( 'id' );
		$user_id = get_current_user_id();

		$chats_table     = $wpdb->prefix . 'creator_chats';
		$messages_table  = $wpdb->prefix . 'creator_messages';
		$actions_table   = $wpdb->prefix . 'creator_actions';
		$snapshots_table = $wpdb->prefix . 'creator_snapshots';
		$backups_table   = $wpdb->prefix . 'creator_backups';

		// Verify ownership.
		$chat = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, title FROM {$chats_table} WHERE id = %d AND user_id = %d",
				$chat_id,
				$user_id
			),
			ARRAY_A
		);

		if ( ! $chat ) {
			return new WP_Error(
				'not_found',
				__( 'Conversation not found.', 'creator-core' ),
				[ 'status' => 404 ]
			);
		}

		// Start transaction for atomic delete.
		$wpdb->query( 'START TRANSACTION' );

		try {
			// Get message IDs for this chat to delete related actions.
			$message_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$messages_table} WHERE chat_id = %d",
					$chat_id
				)
			);

			// Delete related actions (linked via message_id).
			if ( ! empty( $message_ids ) ) {
				$ids_placeholder = implode( ',', array_fill( 0, count( $message_ids ), '%d' ) );
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$actions_table} WHERE message_id IN ({$ids_placeholder})",
						...$message_ids
					)
				);
			}

			// Delete snapshots (has chat_id).
			$wpdb->delete(
				$snapshots_table,
				[ 'chat_id' => $chat_id ],
				[ '%d' ]
			);

			// Delete backups (has chat_id).
			$wpdb->delete(
				$backups_table,
				[ 'chat_id' => $chat_id ],
				[ '%d' ]
			);

			// Delete messages.
			$wpdb->delete(
				$messages_table,
				[ 'chat_id' => $chat_id ],
				[ '%d' ]
			);

			// Delete chat.
			$result = $wpdb->delete(
				$chats_table,
				[ 'id' => $chat_id ],
				[ '%d' ]
			);

			if ( $result === false ) {
				throw new \Exception( 'Failed to delete conversation' );
			}

			$wpdb->query( 'COMMIT' );

			return new WP_REST_Response(
				[
					'success' => true,
					'message' => __( 'Conversation deleted successfully.', 'creator-core' ),
				],
				200
			);

		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );

			return new WP_Error(
				'delete_failed',
				__( 'Failed to delete conversation.', 'creator-core' ),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Generate AI title for a conversation
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function generate_title( WP_REST_Request $request ) {
		global $wpdb;

		$chat_id = $request->get_param( 'id' );
		$user_id = get_current_user_id();

		$chats_table    = $wpdb->prefix . 'creator_chats';
		$messages_table = $wpdb->prefix . 'creator_messages';

		// Verify ownership.
		$chat = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, title FROM {$chats_table} WHERE id = %d AND user_id = %d",
				$chat_id,
				$user_id
			),
			ARRAY_A
		);

		if ( ! $chat ) {
			return new WP_Error(
				'not_found',
				__( 'Conversation not found.', 'creator-core' ),
				[ 'status' => 404 ]
			);
		}

		// Get last 5 messages.
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role, content FROM {$messages_table}
				WHERE chat_id = %d
				ORDER BY created_at DESC
				LIMIT 5",
				$chat_id
			),
			ARRAY_A
		);

		if ( empty( $messages ) ) {
			return new WP_Error(
				'no_messages',
				__( 'No messages in this conversation.', 'creator-core' ),
				[ 'status' => 400 ]
			);
		}

		// Reverse to get chronological order.
		$messages = array_reverse( $messages );

		// Try to generate title via AI.
		$title   = '';
		$summary = '';

		$site_token = get_option( 'creator_site_token', '' );

		if ( ! empty( $site_token ) ) {
			// Build context for AI.
			$conversation_text = '';
			foreach ( $messages as $msg ) {
				$role = $msg['role'] === 'user' ? 'User' : 'Assistant';
				$conversation_text .= "{$role}: {$msg['content']}\n\n";
			}

			// Call Firebase AI to generate title.
			$proxy    = new \CreatorCore\Proxy\ProxyClient();
			$response = $proxy->send_message(
				'Generate a title and summary for this conversation.',
				[
					'task'         => 'generate_title',
					'conversation' => $conversation_text,
				],
				[],
				null
			);

			// Parse AI response for title/summary.
			if ( ! is_wp_error( $response ) && ! empty( $response['response'] ) ) {
				$ai_text = $response['response'];

				// Try to extract JSON.
				if ( preg_match( '/\{[^}]+\}/', $ai_text, $matches ) ) {
					$json = json_decode( $matches[0], true );
					if ( $json ) {
						$title   = $json['title'] ?? '';
						$summary = $json['summary'] ?? '';
					}
				}

				// Fallback: use first line as title.
				if ( empty( $title ) ) {
					$lines = explode( "\n", trim( $ai_text ) );
					$title = trim( $lines[0] );
				}
			}
		}

		// Fallback if AI failed.
		if ( empty( $title ) ) {
			$first_user_message = '';
			foreach ( $messages as $msg ) {
				if ( $msg['role'] === 'user' ) {
					$first_user_message = $msg['content'];
					break;
				}
			}
			$title = $this->truncate_text( $first_user_message, 60 );
			if ( empty( $title ) ) {
				$title = sprintf(
					/* translators: %s: date */
					__( 'Conversation - %s', 'creator-core' ),
					date_i18n( get_option( 'date_format' ) )
				);
			}
		}

		// Save title to database.
		$wpdb->update(
			$chats_table,
			[ 'title' => $title ],
			[ 'id' => $chat_id ],
			[ '%s' ],
			[ '%d' ]
		);

		return new WP_REST_Response(
			[
				'success' => true,
				'title'   => $title,
				'summary' => $summary,
			],
			200
		);
	}

	/**
	 * Get system health status
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_system_health( WP_REST_Request $request ) {
		$site_token = get_option( 'creator_site_token', '' );
		$health     = [];

		// Check Firebase/Proxy health.
		$proxy        = new \CreatorCore\Proxy\ProxyClient();
		$proxy_health = $proxy->health_check();

		$health['firebase'] = [
			'status'     => $proxy_health['healthy'] ? 'connected' : 'disconnected',
			'label'      => __( 'Firebase', 'creator-core' ),
			'latency_ms' => $proxy_health['latency_ms'] ?? 0,
			'message'    => $proxy_health['message'] ?? '',
		];

		// AI Models status.
		$ai_active = ! empty( $site_token ) && $proxy_health['healthy'];

		$health['gemini'] = [
			'status'  => $ai_active ? 'active' : 'inactive',
			'label'   => __( 'Gemini 2.5 Pro', 'creator-core' ),
			'model'   => 'gemini-2.5-pro',
			'version' => '2.5',
		];

		$health['claude'] = [
			'status'  => $ai_active ? 'active' : 'inactive',
			'label'   => __( 'Claude Opus 4.5', 'creator-core' ),
			'model'   => 'claude-opus-4-5',
			'version' => '4.5',
		];

		// System info.
		$health['system'] = [
			'wp_version'     => get_bloginfo( 'version' ),
			'php_version'    => PHP_VERSION,
			'plugin_version' => CREATOR_CORE_VERSION,
		];

		return new WP_REST_Response(
			[
				'success' => true,
				'health'  => $health,
			],
			200
		);
	}
}
