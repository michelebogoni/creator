<?php
/**
 * Debug Logger
 *
 * Logs the full conversation between Creator and AI for debugging purposes.
 * Saves detailed information about each step of the multi-step loop.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Debug;

defined( 'ABSPATH' ) || exit;

/**
 * Class DebugLogger
 *
 * Provides detailed logging for AI conversations and code execution
 */
class DebugLogger {

    /**
     * Log file path
     *
     * @var string
     */
    private string $log_file;

    /**
     * Current session ID
     *
     * @var string
     */
    private string $session_id;

    /**
     * Whether debug mode is enabled
     *
     * @var bool
     */
    private bool $enabled;

    /**
     * Constructor
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $debug_dir  = $upload_dir['basedir'] . '/creator-debug';

        // Create debug directory if it doesn't exist.
        if ( ! file_exists( $debug_dir ) ) {
            wp_mkdir_p( $debug_dir );
            // Add .htaccess to protect logs.
            file_put_contents( $debug_dir . '/.htaccess', 'deny from all' );
        }

        $this->log_file   = $debug_dir . '/creator-debug.log';
        $this->session_id = $this->generate_session_id();
        $this->enabled    = get_option( 'creator_debug_mode', true ); // Default enabled for now.
    }

    /**
     * Generate a unique session ID
     *
     * @return string
     */
    private function generate_session_id(): string {
        return date( 'Y-m-d_H-i-s' ) . '_' . substr( md5( uniqid() ), 0, 8 );
    }

    /**
     * Start a new debug session
     *
     * @param string $user_message The initial user message.
     * @param int    $chat_id      The chat ID.
     * @return void
     */
    public function start_session( string $user_message, int $chat_id ): void {
        if ( ! $this->enabled ) {
            return;
        }

        $this->session_id = $this->generate_session_id();

        $entry = [
            'session_id' => $this->session_id,
            'timestamp'  => current_time( 'mysql' ),
            'type'       => 'SESSION_START',
            'chat_id'    => $chat_id,
            'user_message' => $user_message,
            'user_id'    => get_current_user_id(),
        ];

        $this->write_log( $entry );
    }

    /**
     * Log the WordPress context being sent to AI
     *
     * @param array $context The WordPress context.
     * @return void
     */
    public function log_context( array $context ): void {
        if ( ! $this->enabled ) {
            return;
        }

        $entry = [
            'session_id' => $this->session_id,
            'timestamp'  => current_time( 'mysql' ),
            'type'       => 'CONTEXT',
            'context'    => $context,
        ];

        $this->write_log( $entry );
    }

    /**
     * Log an AI request (what we send to the proxy)
     *
     * @param string     $message       The message being sent.
     * @param array      $context       The context being sent.
     * @param array      $history       The conversation history.
     * @param array|null $documentation Any documentation being included.
     * @param int        $iteration     The loop iteration number.
     * @return void
     */
    public function log_ai_request( string $message, array $context, array $history, ?array $documentation, int $iteration ): void {
        if ( ! $this->enabled ) {
            return;
        }

        $entry = [
            'session_id'    => $this->session_id,
            'timestamp'     => current_time( 'mysql' ),
            'type'          => 'AI_REQUEST',
            'iteration'     => $iteration,
            'message'       => $message,
            'context_keys'  => array_keys( $context ),
            'history_count' => count( $history ),
            'documentation' => $documentation ? array_keys( $documentation ) : null,
        ];

        $this->write_log( $entry );
    }

    /**
     * Log the raw AI response from proxy
     *
     * @param mixed $response The raw response from proxy.
     * @param int   $iteration The loop iteration number.
     * @return void
     */
    public function log_ai_response( $response, int $iteration ): void {
        if ( ! $this->enabled ) {
            return;
        }

        $entry = [
            'session_id' => $this->session_id,
            'timestamp'  => current_time( 'mysql' ),
            'type'       => 'AI_RESPONSE',
            'iteration'  => $iteration,
            'response'   => $response,
            'is_error'   => is_wp_error( $response ),
        ];

        if ( is_wp_error( $response ) ) {
            $entry['error_message'] = $response->get_error_message();
            $entry['error_code']    = $response->get_error_code();
        }

        $this->write_log( $entry );
    }

    /**
     * Log processed response (after ResponseHandler)
     *
     * @param array $processed The processed response.
     * @param int   $iteration The loop iteration number.
     * @return void
     */
    public function log_processed_response( array $processed, int $iteration ): void {
        if ( ! $this->enabled ) {
            return;
        }

        $entry = [
            'session_id' => $this->session_id,
            'timestamp'  => current_time( 'mysql' ),
            'type'       => 'PROCESSED_RESPONSE',
            'iteration'  => $iteration,
            'response_type' => $processed['type'] ?? 'unknown',
            'step'       => $processed['step'] ?? '',
            'status'     => $processed['status'] ?? '',
            'message'    => $processed['message'] ?? '',
            'continue_automatically' => $processed['continue_automatically'] ?? false,
            'has_code'   => ! empty( $processed['data']['code'] ?? '' ),
            'code_preview' => isset( $processed['data']['code'] )
                ? substr( $processed['data']['code'], 0, 200 ) . '...'
                : null,
        ];

        $this->write_log( $entry );
    }

    /**
     * Log code execution
     *
     * @param string $code   The code being executed.
     * @param array  $result The execution result.
     * @param int    $iteration The loop iteration number.
     * @return void
     */
    public function log_execution( string $code, array $result, int $iteration ): void {
        if ( ! $this->enabled ) {
            return;
        }

        $entry = [
            'session_id' => $this->session_id,
            'timestamp'  => current_time( 'mysql' ),
            'type'       => 'CODE_EXECUTION',
            'iteration'  => $iteration,
            'code'       => $code,
            'result'     => $result,
            'success'    => $result['success'] ?? false,
        ];

        $this->write_log( $entry );
    }

    /**
     * Log documentation request
     *
     * @param array $plugins_requested The plugins for which docs were requested.
     * @param array $docs_received     The documentation received.
     * @param int   $iteration         The loop iteration number.
     * @return void
     */
    public function log_documentation( array $plugins_requested, array $docs_received, int $iteration ): void {
        if ( ! $this->enabled ) {
            return;
        }

        // Calculate doc sizes - handle both string and array formats.
        $doc_sizes = [];
        foreach ( $docs_received as $slug => $doc ) {
            if ( is_string( $doc ) ) {
                $doc_sizes[ $slug ] = strlen( $doc );
            } elseif ( is_array( $doc ) ) {
                $doc_sizes[ $slug ] = strlen( wp_json_encode( $doc ) );
            } else {
                $doc_sizes[ $slug ] = 0;
            }
        }

        $entry = [
            'session_id'        => $this->session_id,
            'timestamp'         => current_time( 'mysql' ),
            'type'              => 'DOCUMENTATION',
            'iteration'         => $iteration,
            'plugins_requested' => $plugins_requested,
            'docs_received'     => array_keys( $docs_received ),
            'doc_sizes'         => $doc_sizes,
            'doc_preview'       => $this->get_doc_preview( $docs_received ),
        ];

        $this->write_log( $entry );
    }

    /**
     * Get a preview of documentation content
     *
     * @param array $docs_received The documentation received.
     * @return array Preview of each doc.
     */
    private function get_doc_preview( array $docs_received ): array {
        $preview = [];
        foreach ( $docs_received as $slug => $doc ) {
            if ( is_string( $doc ) ) {
                $preview[ $slug ] = substr( $doc, 0, 200 ) . '...';
            } elseif ( is_array( $doc ) ) {
                $preview[ $slug ] = $doc;
            }
        }
        return $preview;
    }

    /**
     * Log plugin documentation HTTP request/response
     *
     * @param string $plugin_slug     The plugin slug.
     * @param string $endpoint        The endpoint URL.
     * @param array  $request_body    The request body.
     * @param mixed  $response        The HTTP response.
     * @param int    $status_code     The HTTP status code.
     * @return void
     */
    public function log_plugin_docs_http( string $plugin_slug, string $endpoint, array $request_body, $response, int $status_code ): void {
        if ( ! $this->enabled ) {
            return;
        }

        $entry = [
            'session_id'   => $this->session_id,
            'timestamp'    => current_time( 'mysql' ),
            'type'         => 'PLUGIN_DOCS_HTTP',
            'plugin_slug'  => $plugin_slug,
            'endpoint'     => $endpoint,
            'request_body' => $request_body,
            'status_code'  => $status_code,
            'response'     => is_wp_error( $response )
                ? [ 'error' => $response->get_error_message() ]
                : ( is_string( $response ) ? substr( $response, 0, 500 ) : $response ),
        ];

        $this->write_log( $entry );
    }

    /**
     * Log retry attempt
     *
     * @param array  $error       The error that caused retry.
     * @param int    $retry_count Current retry count.
     * @param int    $iteration   The loop iteration number.
     * @return void
     */
    public function log_retry( array $error, int $retry_count, int $iteration ): void {
        if ( ! $this->enabled ) {
            return;
        }

        $entry = [
            'session_id'  => $this->session_id,
            'timestamp'   => current_time( 'mysql' ),
            'type'        => 'RETRY',
            'iteration'   => $iteration,
            'retry_count' => $retry_count,
            'error'       => $error,
        ];

        $this->write_log( $entry );
    }

    /**
     * Log session end
     *
     * @param array  $final_response The final response.
     * @param int    $total_iterations Total iterations in the loop.
     * @return void
     */
    public function end_session( array $final_response, int $total_iterations ): void {
        if ( ! $this->enabled ) {
            return;
        }

        $entry = [
            'session_id'       => $this->session_id,
            'timestamp'        => current_time( 'mysql' ),
            'type'             => 'SESSION_END',
            'total_iterations' => $total_iterations,
            'final_type'       => $final_response['type'] ?? 'unknown',
            'final_status'     => $final_response['status'] ?? '',
            'steps_summary'    => $final_response['steps'] ?? [],
        ];

        $this->write_log( $entry );
    }

    /**
     * Write a log entry to the file
     *
     * @param array $entry The log entry.
     * @return void
     */
    private function write_log( array $entry ): void {
        $json = wp_json_encode( $entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        $line = $json . "\n\n" . str_repeat( '=', 80 ) . "\n\n";

        file_put_contents( $this->log_file, $line, FILE_APPEND | LOCK_EX );
    }

    /**
     * Get recent log entries
     *
     * @param int    $limit      Maximum number of entries to return.
     * @param string $session_id Optional session ID to filter by.
     * @return array
     */
    public function get_recent_logs( int $limit = 100, ?string $session_id = null ): array {
        if ( ! file_exists( $this->log_file ) ) {
            return [];
        }

        $content = file_get_contents( $this->log_file );
        $entries = explode( "\n\n" . str_repeat( '=', 80 ) . "\n\n", $content );
        $entries = array_filter( $entries );

        $logs = [];
        foreach ( $entries as $entry ) {
            $decoded = json_decode( $entry, true );
            if ( $decoded ) {
                // Filter by session if specified.
                if ( $session_id && ( $decoded['session_id'] ?? '' ) !== $session_id ) {
                    continue;
                }
                $logs[] = $decoded;
            }
        }

        // Return most recent entries.
        return array_slice( $logs, -$limit );
    }

    /**
     * Get all sessions
     *
     * @param int $limit Maximum number of sessions.
     * @return array
     */
    public function get_sessions( int $limit = 20 ): array {
        $logs = $this->get_recent_logs( 1000 );

        $sessions = [];
        foreach ( $logs as $log ) {
            if ( 'SESSION_START' === ( $log['type'] ?? '' ) ) {
                $sessions[] = [
                    'session_id'   => $log['session_id'],
                    'timestamp'    => $log['timestamp'],
                    'chat_id'      => $log['chat_id'] ?? 0,
                    'user_message' => substr( $log['user_message'] ?? '', 0, 100 ),
                ];
            }
        }

        return array_slice( array_reverse( $sessions ), 0, $limit );
    }

    /**
     * Get logs for a specific session
     *
     * @param string $session_id The session ID.
     * @return array
     */
    public function get_session_logs( string $session_id ): array {
        return $this->get_recent_logs( 1000, $session_id );
    }

    /**
     * Clear all logs
     *
     * @return bool
     */
    public function clear_logs(): bool {
        if ( file_exists( $this->log_file ) ) {
            return unlink( $this->log_file );
        }
        return true;
    }

    /**
     * Get the current session ID
     *
     * @return string
     */
    public function get_session_id(): string {
        return $this->session_id;
    }

    /**
     * Get log file path
     *
     * @return string
     */
    public function get_log_file_path(): string {
        return $this->log_file;
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    public function is_enabled(): bool {
        return $this->enabled;
    }

    /**
     * Get log file size in human readable format
     *
     * @return string
     */
    public function get_log_size(): string {
        if ( ! file_exists( $this->log_file ) ) {
            return '0 B';
        }

        $bytes = filesize( $this->log_file );
        $units = [ 'B', 'KB', 'MB', 'GB' ];

        for ( $i = 0; $bytes > 1024 && $i < count( $units ) - 1; $i++ ) {
            $bytes /= 1024;
        }

        return round( $bytes, 2 ) . ' ' . $units[ $i ];
    }
}
