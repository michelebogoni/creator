<?php
/**
 * Response Handler
 *
 * Gestisce le risposte strutturate dal servizio AI.
 * Segue il formato JSON definito nelle specifiche:
 * - step: discovery | strategy | implementation | verification
 * - type: question | plan | execute | verify | complete | error | request_docs | roadmap | execute_step | checkpoint | compress_history | wp_cli
 *
 * @package CreatorCore
 */

namespace CreatorCore\Response;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Executor\CodeExecutor;
use CreatorCore\Execution\WPCLIExecutor;
use CreatorCore\Proxy\ProxyClient;

/**
 * Class ResponseHandler
 *
 * Parses and processes structured AI responses
 */
class ResponseHandler {

    /**
     * Code executor instance
     *
     * @var CodeExecutor
     */
    private CodeExecutor $code_executor;

    /**
     * WP-CLI executor instance
     *
     * @var WPCLIExecutor
     */
    private WPCLIExecutor $wp_cli_executor;

    /**
     * Proxy client for fetching documentation
     *
     * @var ProxyClient
     */
    private ProxyClient $proxy_client;

    /**
     * Constructor
     */
    public function __construct() {
        $this->code_executor   = new CodeExecutor();
        $this->wp_cli_executor = new WPCLIExecutor();
        $this->proxy_client    = new ProxyClient();
    }

    /**
     * Handle AI response from the proxy
     *
     * Parses the structured JSON response and executes actions based on type.
     *
     * @param array $proxy_response The response from the proxy (contains 'success', 'content', etc.).
     * @param array $context        The WordPress context data.
     * @return array Processed response with type, message, and any execution results.
     */
    public function handle( array $proxy_response, array $context = [] ): array {
        // Check if proxy request was successful.
        if ( ! isset( $proxy_response['success'] ) || ! $proxy_response['success'] ) {
            return $this->create_error_response(
                $proxy_response['error'] ?? __( 'Unknown error from AI service.', 'creator-core' ),
                $proxy_response
            );
        }

        // Get raw content.
        $raw_content = $proxy_response['content'] ?? '';

        // If content is empty, return error.
        if ( empty( $raw_content ) ) {
            return $this->create_error_response(
                __( 'Empty response from AI service.', 'creator-core' ),
                [ 'proxy_response' => $proxy_response ]
            );
        }

        // Parse the AI response content (should be JSON).
        $ai_response = $this->parse_ai_content( $raw_content );

        if ( ! $ai_response ) {
            // If JSON parsing failed, try to extract meaningful text and return as a simple message.
            // This handles cases where AI doesn't follow JSON format strictly.
            $clean_content = $this->extract_text_content( $raw_content );

            return [
                'type'                   => 'complete',
                'step'                   => 'discovery',
                'status'                 => __( 'Response', 'creator-core' ),
                'message'                => $clean_content,
                'data'                   => [],
                'requires_confirmation'  => false,
                'continue_automatically' => false,
            ];
        }

        // Process based on response type.
        return $this->process_response_type( $ai_response, $context );
    }

    /**
     * Extract text content from non-JSON AI response
     *
     * @param string $content The raw AI response.
     * @return string Cleaned text content.
     */
    private function extract_text_content( string $content ): string {
        // Remove markdown code blocks if present.
        $content = preg_replace( '/```[\w]*\n?/', '', $content );
        $content = str_replace( '```', '', $content );

        // Trim whitespace.
        return trim( $content );
    }

    /**
     * Parse AI content which should be JSON
     *
     * @param string $content The AI response content.
     * @return array|null Parsed JSON or null on failure.
     */
    private function parse_ai_content( string $content ): ?array {
        if ( empty( $content ) ) {
            return null;
        }

        // Try to decode as JSON directly.
        $decoded = json_decode( $content, true );

        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            return $decoded;
        }

        // Sometimes the AI wraps JSON in markdown code blocks.
        $content = $this->extract_json_from_markdown( $content );

        $decoded = json_decode( $content, true );

        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            return $decoded;
        }

        return null;
    }

    /**
     * Extract JSON from markdown code blocks
     *
     * @param string $content Content that may contain JSON in markdown.
     * @return string Extracted JSON or original content.
     */
    private function extract_json_from_markdown( string $content ): string {
        // Match ```json ... ``` blocks.
        if ( preg_match( '/```(?:json)?\s*\n([\s\S]*?)```/', $content, $matches ) ) {
            return trim( $matches[1] );
        }

        return $content;
    }

    /**
     * Process response based on type
     *
     * @param array $ai_response The parsed AI response.
     * @param array $context     The WordPress context.
     * @return array Processed response.
     */
    private function process_response_type( array $ai_response, array $context ): array {
        $type = $ai_response['type'] ?? 'unknown';

        switch ( $type ) {
            case 'question':
            case 'complete':
                return $this->handle_message_response( $ai_response );

            case 'plan':
                return $this->handle_plan_response( $ai_response );

            case 'roadmap':
                return $this->handle_roadmap_response( $ai_response );

            case 'execute':
                return $this->handle_execute_response( $ai_response, $context );

            case 'execute_step':
                return $this->handle_execute_step_response( $ai_response, $context );

            case 'checkpoint':
                return $this->handle_checkpoint_response( $ai_response );

            case 'verify':
                return $this->handle_verify_response( $ai_response, $context );

            case 'request_docs':
                return $this->handle_request_docs_response( $ai_response );

            case 'compress_history':
                return $this->handle_compress_history_response( $ai_response );

            case 'wp_cli':
                return $this->handle_wp_cli_response( $ai_response );

            case 'error':
                return $this->handle_error_response( $ai_response );

            default:
                return $this->create_error_response(
                    sprintf(
                        /* translators: %s: unknown response type */
                        __( 'Unknown response type: %s', 'creator-core' ),
                        $type
                    ),
                    [ 'ai_response' => $ai_response ]
                );
        }
    }

    /**
     * Handle question or complete response types
     *
     * These just display a message to the user.
     *
     * @param array $ai_response The AI response.
     * @return array Response for the frontend.
     */
    private function handle_message_response( array $ai_response ): array {
        return [
            'type'                   => $ai_response['type'],
            'step'                   => $ai_response['step'] ?? 'discovery',
            'status'                 => $ai_response['status'] ?? '',
            'message'                => $ai_response['message'] ?? '',
            'data'                   => $ai_response['data'] ?? [],
            'requires_confirmation'  => $ai_response['requires_confirmation'] ?? false,
            'continue_automatically' => false,
        ];
    }

    /**
     * Handle plan response type
     *
     * Shows the action plan to the user.
     *
     * @param array $ai_response The AI response.
     * @return array Response for the frontend.
     */
    private function handle_plan_response( array $ai_response ): array {
        return [
            'type'                   => 'plan',
            'step'                   => $ai_response['step'] ?? 'strategy',
            'status'                 => $ai_response['status'] ?? __( 'Plan ready', 'creator-core' ),
            'message'                => $ai_response['message'] ?? '',
            'data'                   => $ai_response['data'] ?? [],
            'requires_confirmation'  => $ai_response['requires_confirmation'] ?? true,
            'continue_automatically' => false,
        ];
    }

    /**
     * Handle roadmap response type
     *
     * Shows a detailed step-by-step roadmap for complex tasks.
     * Each step is atomic and can be executed independently.
     *
     * @param array $ai_response The AI response.
     * @return array Response for the frontend.
     */
    private function handle_roadmap_response( array $ai_response ): array {
        $data = $ai_response['data'] ?? [];

        // Ensure roadmap has required fields.
        if ( empty( $data['roadmap_id'] ) ) {
            $data['roadmap_id'] = 'roadmap-' . time() . '-' . wp_rand( 1000, 9999 );
        }

        return [
            'type'                   => 'roadmap',
            'step'                   => $ai_response['step'] ?? 'strategy',
            'status'                 => $ai_response['status'] ?? __( 'Roadmap ready', 'creator-core' ),
            'message'                => $ai_response['message'] ?? '',
            'data'                   => $data,
            'requires_confirmation'  => $ai_response['requires_confirmation'] ?? true,
            'continue_automatically' => false,
        ];
    }

    /**
     * Handle execute_step response type
     *
     * Executes a single atomic step from the roadmap.
     * Each step should be small and focused (< 30s execution).
     *
     * @param array $ai_response The AI response.
     * @param array $context     The WordPress context.
     * @return array Response with execution result.
     */
    private function handle_execute_step_response( array $ai_response, array $context ): array {
        $code = $ai_response['data']['code'] ?? '';

        if ( empty( $code ) ) {
            return $this->create_error_response(
                __( 'No code provided for step execution.', 'creator-core' ),
                [ 'ai_response' => $ai_response ]
            );
        }

        // Execute the PHP code with context available as $context variable.
        $execution_result = $this->code_executor->execute( $code, $context );

        $data = $ai_response['data'] ?? [];

        return [
            'type'                   => 'execute_step',
            'step'                   => $ai_response['step'] ?? 'implementation',
            'status'                 => $ai_response['status'] ?? __( 'Executing step...', 'creator-core' ),
            'message'                => $ai_response['message'] ?? '',
            'data'                   => $data,
            'execution_result'       => $execution_result,
            'requires_confirmation'  => false,
            'continue_automatically' => $ai_response['continue_automatically'] ?? true,
        ];
    }

    /**
     * Handle checkpoint response type
     *
     * Reports progress after a step completes.
     * Contains accumulated context to pass to next steps.
     *
     * @param array $ai_response The AI response.
     * @return array Response for the frontend.
     */
    private function handle_checkpoint_response( array $ai_response ): array {
        $data = $ai_response['data'] ?? [];

        // Calculate progress percentage if not provided.
        if ( empty( $data['progress_percentage'] ) && ! empty( $data['completed_step'] ) && ! empty( $data['total_steps'] ) ) {
            $data['progress_percentage'] = round( ( $data['completed_step'] / $data['total_steps'] ) * 100 );
        }

        return [
            'type'                   => 'checkpoint',
            'step'                   => $ai_response['step'] ?? 'implementation',
            'status'                 => $ai_response['status'] ?? __( 'Step complete', 'creator-core' ),
            'message'                => $ai_response['message'] ?? '',
            'data'                   => $data,
            'requires_confirmation'  => $ai_response['requires_confirmation'] ?? false,
            'continue_automatically' => $ai_response['continue_automatically'] ?? true,
        ];
    }

    /**
     * Handle compress_history response type
     *
     * Compresses old conversation history to reduce token usage.
     * Preserves key facts and recent messages.
     *
     * @param array $ai_response The AI response.
     * @return array Response for the frontend.
     */
    private function handle_compress_history_response( array $ai_response ): array {
        $data = $ai_response['data'] ?? [];

        return [
            'type'                   => 'compress_history',
            'step'                   => $ai_response['step'] ?? 'discovery',
            'status'                 => $ai_response['status'] ?? __( 'History compressed', 'creator-core' ),
            'message'                => $ai_response['message'] ?? '',
            'data'                   => $data,
            'requires_confirmation'  => false,
            'continue_automatically' => $ai_response['continue_automatically'] ?? true,
        ];
    }

    /**
     * Handle wp_cli response type
     *
     * Executes WP-CLI commands safely using the WPCLIExecutor.
     *
     * @param array $ai_response The AI response.
     * @return array Response with execution result.
     */
    private function handle_wp_cli_response( array $ai_response ): array {
        $command = $ai_response['data']['command'] ?? '';

        if ( empty( $command ) ) {
            return $this->create_error_response(
                __( 'No WP-CLI command provided.', 'creator-core' ),
                [ 'ai_response' => $ai_response ]
            );
        }

        // Check if WP-CLI is available.
        if ( ! $this->wp_cli_executor->is_available() ) {
            return [
                'type'                   => 'wp_cli',
                'step'                   => $ai_response['step'] ?? 'implementation',
                'status'                 => __( 'WP-CLI not available', 'creator-core' ),
                'message'                => __( 'WP-CLI is not available on this server. The AI will need to use an alternative method.', 'creator-core' ),
                'data'                   => $ai_response['data'] ?? [],
                'execution_result'       => [
                    'success' => false,
                    'error'   => 'WP-CLI not available',
                    'output'  => '',
                ],
                'requires_confirmation'  => false,
                'continue_automatically' => true,
            ];
        }

        // Execute the WP-CLI command.
        $execution_result = $this->wp_cli_executor->execute( $command );

        $data            = $ai_response['data'] ?? [];
        $data['command'] = $command;

        return [
            'type'                   => 'wp_cli',
            'step'                   => $ai_response['step'] ?? 'implementation',
            'status'                 => $execution_result['success']
                ? __( 'WP-CLI command executed', 'creator-core' )
                : __( 'WP-CLI command failed', 'creator-core' ),
            'message'                => $ai_response['message'] ?? '',
            'data'                   => $data,
            'execution_result'       => $execution_result,
            'requires_confirmation'  => false,
            'continue_automatically' => $ai_response['continue_automatically'] ?? true,
        ];
    }

    /**
     * Handle execute response type
     *
     * Executes PHP code and returns the result.
     *
     * @param array $ai_response The AI response.
     * @param array $context     The WordPress context.
     * @return array Response with execution result.
     */
    private function handle_execute_response( array $ai_response, array $context ): array {
        $code = $ai_response['data']['code'] ?? '';

        if ( empty( $code ) ) {
            return $this->create_error_response(
                __( 'No code provided for execution.', 'creator-core' ),
                [ 'ai_response' => $ai_response ]
            );
        }

        // Execute the PHP code with context available as $context variable.
        $execution_result = $this->code_executor->execute( $code, $context );

        return [
            'type'                   => 'execute',
            'step'                   => $ai_response['step'] ?? 'implementation',
            'status'                 => $ai_response['status'] ?? __( 'Executing...', 'creator-core' ),
            'message'                => $ai_response['message'] ?? '',
            'data'                   => $ai_response['data'] ?? [],
            'execution_result'       => $execution_result,
            'requires_confirmation'  => false,
            'continue_automatically' => $ai_response['continue_automatically'] ?? true,
        ];
    }

    /**
     * Handle verify response type
     *
     * Executes verification code.
     *
     * @param array $ai_response The AI response.
     * @param array $context     The WordPress context.
     * @return array Response with verification result.
     */
    private function handle_verify_response( array $ai_response, array $context ): array {
        $code = $ai_response['data']['code'] ?? '';

        if ( empty( $code ) ) {
            // Verification without code just passes through.
            return [
                'type'                   => 'verify',
                'step'                   => $ai_response['step'] ?? 'verification',
                'status'                 => $ai_response['status'] ?? __( 'Verified', 'creator-core' ),
                'message'                => $ai_response['message'] ?? '',
                'data'                   => $ai_response['data'] ?? [],
                'requires_confirmation'  => false,
                'continue_automatically' => $ai_response['continue_automatically'] ?? true,
            ];
        }

        // Execute verification code with context available as $context variable.
        $verification_result = $this->code_executor->execute( $code, $context );

        return [
            'type'                   => 'verify',
            'step'                   => $ai_response['step'] ?? 'verification',
            'status'                 => $ai_response['status'] ?? __( 'Verified', 'creator-core' ),
            'message'                => $ai_response['message'] ?? '',
            'data'                   => $ai_response['data'] ?? [],
            'verification_result'    => $verification_result,
            'requires_confirmation'  => false,
            'continue_automatically' => $ai_response['continue_automatically'] ?? true,
        ];
    }

    /**
     * Handle request_docs response type
     *
     * Fetches documentation for requested plugins.
     * Tries to get version info from installed plugins for better accuracy.
     *
     * @param array $ai_response The AI response.
     * @return array Response with fetched documentation.
     */
    private function handle_request_docs_response( array $ai_response ): array {
        $plugins_needed   = $ai_response['data']['plugins_needed'] ?? [];
        $documentation    = [];
        $installed_plugins = $this->get_installed_plugins_info();

        foreach ( $plugins_needed as $plugin_slug ) {
            // Try to get version from installed plugins.
            $version     = $installed_plugins[ $plugin_slug ]['version'] ?? null;
            $plugin_name = $installed_plugins[ $plugin_slug ]['name'] ?? null;

            $doc = $this->proxy_client->get_plugin_docs( $plugin_slug, $version, $plugin_name );
            if ( $doc ) {
                $documentation[ $plugin_slug ] = $doc;
            }
        }

        // Log documentation fetch results.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[Creator Debug] Documentation fetched for: ' . implode( ', ', array_keys( $documentation ) ) );
            error_log( '[Creator Debug] Documentation missing for: ' . implode( ', ', array_diff( $plugins_needed, array_keys( $documentation ) ) ) );
        }

        return [
            'type'                   => 'request_docs',
            'step'                   => $ai_response['step'] ?? 'discovery',
            'status'                 => $ai_response['status'] ?? __( 'Documentation fetched', 'creator-core' ),
            'message'                => $ai_response['message'] ?? '',
            'data'                   => $ai_response['data'] ?? [],
            'documentation'          => $documentation,
            'requires_confirmation'  => false,
            'continue_automatically' => true,
        ];
    }

    /**
     * Get installed plugins info (slug => name, version)
     *
     * @return array Plugin info indexed by slug.
     */
    private function get_installed_plugins_info(): array {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        $active  = get_option( 'active_plugins', [] );
        $info    = [];

        foreach ( $active as $plugin_file ) {
            if ( isset( $plugins[ $plugin_file ] ) ) {
                // Extract slug from plugin file path.
                $slug = dirname( $plugin_file );
                if ( '.' === $slug ) {
                    $slug = basename( $plugin_file, '.php' );
                }

                $info[ $slug ] = [
                    'name'    => $plugins[ $plugin_file ]['Name'] ?? '',
                    'version' => $plugins[ $plugin_file ]['Version'] ?? '',
                ];
            }
        }

        return $info;
    }

    /**
     * Handle error response type from AI
     *
     * @param array $ai_response The AI error response.
     * @return array Error response for the frontend.
     */
    private function handle_error_response( array $ai_response ): array {
        return [
            'type'                   => 'error',
            'step'                   => $ai_response['step'] ?? 'implementation',
            'status'                 => $ai_response['status'] ?? __( 'Error', 'creator-core' ),
            'message'                => $ai_response['message'] ?? __( 'An error occurred.', 'creator-core' ),
            'data'                   => $ai_response['data'] ?? [],
            'requires_confirmation'  => false,
            'continue_automatically' => $ai_response['data']['recoverable'] ?? false,
        ];
    }

    /**
     * Create a standardized error response
     *
     * @param string $message Error message.
     * @param array  $data    Additional error data.
     * @return array Error response.
     */
    private function create_error_response( string $message, array $data = [] ): array {
        return [
            'type'                   => 'error',
            'step'                   => 'implementation',
            'status'                 => __( 'Error', 'creator-core' ),
            'message'                => $message,
            'data'                   => $data,
            'requires_confirmation'  => false,
            'continue_automatically' => false,
        ];
    }

    /**
     * Format response for display in the chat
     *
     * @param array $response The processed response.
     * @return string HTML formatted message.
     */
    public function format_for_display( array $response ): string {
        $html = '';

        // Add status badge if present.
        if ( ! empty( $response['status'] ) ) {
            $status_class = $this->get_status_class( $response['type'] );
            $html .= sprintf(
                '<div class="creator-status-badge %s">%s</div>',
                esc_attr( $status_class ),
                esc_html( $response['status'] )
            );
        }

        // Add main message.
        if ( ! empty( $response['message'] ) ) {
            $html .= '<div class="creator-response-message">';
            $html .= wp_kses_post( $this->format_message_text( $response['message'] ) );
            $html .= '</div>';
        }

        // Add plan actions if present.
        if ( 'plan' === $response['type'] && ! empty( $response['data']['actions'] ) ) {
            $html .= $this->format_plan_actions( $response['data']['actions'] );
        }

        // Add execution result if present.
        if ( isset( $response['execution_result'] ) ) {
            $html .= $this->format_execution_result( $response['execution_result'] );
        }

        return $html;
    }

    /**
     * Get CSS class for status based on type
     *
     * @param string $type Response type.
     * @return string CSS class.
     */
    private function get_status_class( string $type ): string {
        $classes = [
            'complete'         => 'status-success',
            'error'            => 'status-error',
            'execute'          => 'status-executing',
            'execute_step'     => 'status-executing',
            'wp_cli'           => 'status-executing',
            'checkpoint'       => 'status-checkpoint',
            'verify'           => 'status-verifying',
            'plan'             => 'status-planning',
            'roadmap'          => 'status-roadmap',
            'question'         => 'status-waiting',
            'request_docs'     => 'status-fetching',
            'compress_history' => 'status-compressing',
        ];

        return $classes[ $type ] ?? 'status-default';
    }

    /**
     * Format message text with basic markdown support
     *
     * @param string $text The message text.
     * @return string Formatted HTML.
     */
    private function format_message_text( string $text ): string {
        // Convert newlines to breaks.
        $text = nl2br( esc_html( $text ) );

        // Convert **bold** to <strong>.
        $text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );

        // Convert *italic* to <em>.
        $text = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $text );

        // Convert `code` to <code>.
        $text = preg_replace( '/`(.+?)`/', '<code>$1</code>', $text );

        return $text;
    }

    /**
     * Format plan actions as HTML
     *
     * @param array $actions The plan actions.
     * @return string HTML for actions.
     */
    private function format_plan_actions( array $actions ): string {
        $html = '<div class="creator-plan-actions"><ol>';

        foreach ( $actions as $action ) {
            $html .= sprintf(
                '<li class="creator-plan-action">%s</li>',
                esc_html( $action['description'] ?? '' )
            );
        }

        $html .= '</ol></div>';

        return $html;
    }

    /**
     * Format execution result as HTML
     *
     * @param array $result The execution result.
     * @return string HTML for result.
     */
    private function format_execution_result( array $result ): string {
        $html = '<div class="creator-execution-result">';

        if ( $result['success'] ) {
            $html .= '<span class="dashicons dashicons-yes-alt creator-result-success"></span>';

            if ( ! empty( $result['output'] ) ) {
                $html .= '<pre class="creator-result-output">' . esc_html( $result['output'] ) . '</pre>';
            }
        } else {
            $html .= '<span class="dashicons dashicons-warning creator-result-error"></span>';
            $html .= '<span class="creator-error-message">' . esc_html( $result['error'] ?? 'Unknown error' ) . '</span>';
        }

        $html .= '</div>';

        return $html;
    }
}
