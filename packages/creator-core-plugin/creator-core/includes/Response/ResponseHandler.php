<?php
/**
 * Response Handler
 *
 * Parsa le risposte dal servizio AI.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Response;

defined( 'ABSPATH' ) || exit;

/**
 * Class ResponseHandler
 *
 * Parses and processes AI responses
 */
class ResponseHandler {

    /**
     * Parse AI response
     *
     * @param array $response The raw response from the proxy.
     * @return array{text: string, has_code: bool, code?: string, code_language?: string, actions?: array}
     */
    public function parse( array $response ): array {
        $result = [
            'text'     => '',
            'has_code' => false,
            'code'     => null,
            'actions'  => [],
        ];

        // Extract text content
        if ( isset( $response['text'] ) ) {
            $result['text'] = $response['text'];
        } elseif ( isset( $response['content'] ) ) {
            $result['text'] = $response['content'];
        } elseif ( isset( $response['message'] ) ) {
            $result['text'] = $response['message'];
        }

        // Extract code blocks
        $code_blocks = $this->extract_code_blocks( $result['text'] );
        if ( ! empty( $code_blocks ) ) {
            $result['has_code'] = true;
            $result['code'] = $code_blocks[0]['code'];
            $result['code_language'] = $code_blocks[0]['language'];
            $result['all_code_blocks'] = $code_blocks;
        }

        // Extract actions if present
        if ( isset( $response['actions'] ) && is_array( $response['actions'] ) ) {
            $result['actions'] = $this->parse_actions( $response['actions'] );
        }

        // Check for structured response format
        if ( isset( $response['structured'] ) ) {
            $result = array_merge( $result, $this->parse_structured( $response['structured'] ) );
        }

        return $result;
    }

    /**
     * Extract code blocks from text
     *
     * @param string $text The text to extract code from.
     * @return array Array of code blocks with language and code.
     */
    public function extract_code_blocks( string $text ): array {
        $blocks = [];

        // Match fenced code blocks with optional language
        $pattern = '/```(\w*)\n([\s\S]*?)```/';

        if ( preg_match_all( $pattern, $text, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $blocks[] = [
                    'language' => ! empty( $match[1] ) ? $match[1] : 'text',
                    'code'     => trim( $match[2] ),
                ];
            }
        }

        return $blocks;
    }

    /**
     * Extract only PHP code from text
     *
     * @param string $text The text to extract PHP code from.
     * @return string|null The PHP code or null if not found.
     */
    public function extract_php_code( string $text ): ?string {
        $blocks = $this->extract_code_blocks( $text );

        foreach ( $blocks as $block ) {
            if ( in_array( strtolower( $block['language'] ), [ 'php', 'php5', 'php7', 'php8' ], true ) ) {
                return $block['code'];
            }
        }

        // Try to find inline PHP code if no fenced block found
        if ( preg_match( '/<\?php([\s\S]*?)(?:\?>|$)/', $text, $match ) ) {
            return '<?php' . $match[1];
        }

        return null;
    }

    /**
     * Parse action instructions from response
     *
     * @param array $actions Raw actions from response.
     * @return array Parsed actions.
     */
    private function parse_actions( array $actions ): array {
        $parsed = [];

        foreach ( $actions as $action ) {
            if ( ! isset( $action['type'] ) ) {
                continue;
            }

            $parsed_action = [
                'type'        => sanitize_key( $action['type'] ),
                'target'      => $action['target'] ?? '',
                'description' => $action['description'] ?? '',
                'params'      => $action['params'] ?? [],
            ];

            // Validate action type
            if ( $this->is_valid_action_type( $parsed_action['type'] ) ) {
                $parsed[] = $parsed_action;
            }
        }

        return $parsed;
    }

    /**
     * Check if action type is valid
     *
     * @param string $type The action type.
     * @return bool
     */
    private function is_valid_action_type( string $type ): bool {
        $valid_types = [
            'execute_code',
            'create_file',
            'modify_file',
            'delete_file',
            'install_plugin',
            'activate_plugin',
            'deactivate_plugin',
            'update_option',
            'create_post',
            'update_post',
            'delete_post',
            'run_wp_cli',
        ];

        return in_array( $type, $valid_types, true );
    }

    /**
     * Parse structured response format
     *
     * @param array $structured The structured response data.
     * @return array Parsed structured data.
     */
    private function parse_structured( array $structured ): array {
        $result = [];

        if ( isset( $structured['explanation'] ) ) {
            $result['explanation'] = sanitize_textarea_field( $structured['explanation'] );
        }

        if ( isset( $structured['code'] ) ) {
            $result['has_code'] = true;
            $result['code'] = $structured['code'];
            $result['code_language'] = $structured['language'] ?? 'php';
        }

        if ( isset( $structured['warnings'] ) && is_array( $structured['warnings'] ) ) {
            $result['warnings'] = array_map( 'sanitize_text_field', $structured['warnings'] );
        }

        if ( isset( $structured['suggestions'] ) && is_array( $structured['suggestions'] ) ) {
            $result['suggestions'] = array_map( 'sanitize_text_field', $structured['suggestions'] );
        }

        return $result;
    }

    /**
     * Remove code blocks from text
     *
     * @param string $text The text to clean.
     * @return string Text without code blocks.
     */
    public function remove_code_blocks( string $text ): string {
        // Remove fenced code blocks
        $text = preg_replace( '/```\w*\n[\s\S]*?```/', '', $text );

        // Clean up extra whitespace
        $text = preg_replace( '/\n{3,}/', "\n\n", $text );

        return trim( $text );
    }

    /**
     * Format response for display
     *
     * @param array $parsed_response The parsed response.
     * @return string HTML formatted response.
     */
    public function format_for_display( array $parsed_response ): string {
        $html = '';

        // Add main text
        if ( ! empty( $parsed_response['text'] ) ) {
            $clean_text = $this->remove_code_blocks( $parsed_response['text'] );
            $html .= '<div class="creator-response-text">' . wp_kses_post( wpautop( $clean_text ) ) . '</div>';
        }

        // Add code blocks
        if ( ! empty( $parsed_response['all_code_blocks'] ) ) {
            foreach ( $parsed_response['all_code_blocks'] as $block ) {
                $html .= sprintf(
                    '<div class="creator-code-block" data-language="%s"><pre><code class="language-%s">%s</code></pre></div>',
                    esc_attr( $block['language'] ),
                    esc_attr( $block['language'] ),
                    esc_html( $block['code'] )
                );
            }
        }

        // Add execution result if present
        if ( isset( $parsed_response['execution_result'] ) ) {
            $result = $parsed_response['execution_result'];
            $status_class = $result['success'] ? 'success' : 'error';

            $html .= sprintf(
                '<div class="creator-execution-result %s">',
                esc_attr( $status_class )
            );

            if ( ! empty( $result['output'] ) ) {
                $html .= '<div class="output"><pre>' . esc_html( $result['output'] ) . '</pre></div>';
            }

            if ( ! empty( $result['error'] ) ) {
                $html .= '<div class="error">' . esc_html( $result['error'] ) . '</div>';
            }

            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Extract thinking/reasoning from response
     *
     * @param array $response The raw response.
     * @return string|null The thinking content or null.
     */
    public function extract_thinking( array $response ): ?string {
        if ( isset( $response['thinking'] ) ) {
            return $response['thinking'];
        }

        if ( isset( $response['reasoning'] ) ) {
            return $response['reasoning'];
        }

        return null;
    }
}
