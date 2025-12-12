<?php
/**
 * Code Executor
 *
 * Esegue codice PHP in modo sicuro via eval().
 *
 * @package CreatorCore
 */

namespace CreatorCore\Executor;

defined( 'ABSPATH' ) || exit;

/**
 * Class CodeExecutor
 *
 * Safely executes PHP code from AI responses
 */
class CodeExecutor {

    /**
     * Forbidden functions that cannot be executed
     *
     * @var array
     */
    private array $forbidden_functions = [
        'exec',
        'shell_exec',
        'system',
        'passthru',
        'popen',
        'proc_open',
        'pcntl_exec',
        'eval',         // Prevent nested eval
        'assert',
        'create_function',
        'call_user_func',
        'call_user_func_array',
        'file_put_contents',
        'file_get_contents',
        'fopen',
        'fwrite',
        'fputs',
        'unlink',
        'rmdir',
        'mkdir',
        'rename',
        'copy',
        'move_uploaded_file',
        'curl_init',
        'curl_exec',
        'fsockopen',
        'pfsockopen',
        'stream_socket_client',
        'mail',
        'header',
        'setcookie',
    ];

    /**
     * Execute PHP code safely
     *
     * @param string $code The PHP code to execute.
     * @return array{success: bool, output: string, error?: string}
     */
    public function execute( string $code ): array {
        // Validate code before execution
        $validation = $this->validate_code( $code );
        if ( ! $validation['valid'] ) {
            return [
                'success' => false,
                'output'  => '',
                'error'   => $validation['error'],
            ];
        }

        // Prepare code for execution
        $code = $this->prepare_code( $code );

        // Execute with output buffering
        ob_start();
        $error = null;

        try {
            // Set custom error handler
            set_error_handler( function( $severity, $message, $file, $line ) {
                throw new \ErrorException( $message, 0, $severity, $file, $line );
            });

            // Execute the code
            $result = eval( $code ); // phpcs:ignore Squiz.PHP.Eval.Discouraged

            restore_error_handler();

        } catch ( \Throwable $e ) {
            restore_error_handler();
            $error = $e->getMessage();
        }

        $output = ob_get_clean();

        if ( $error !== null ) {
            return [
                'success' => false,
                'output'  => $output,
                'error'   => $error,
            ];
        }

        return [
            'success' => true,
            'output'  => $output,
            'result'  => $result,
        ];
    }

    /**
     * Validate code for security
     *
     * @param string $code The code to validate.
     * @return array{valid: bool, error?: string}
     */
    public function validate_code( string $code ): array {
        // Check for forbidden functions
        foreach ( $this->forbidden_functions as $func ) {
            // Match function calls with various patterns
            $pattern = '/\b' . preg_quote( $func, '/' ) . '\s*\(/i';
            if ( preg_match( $pattern, $code ) ) {
                return [
                    'valid' => false,
                    'error' => sprintf(
                        /* translators: %s: forbidden function name */
                        __( 'Forbidden function detected: %s', 'creator-core' ),
                        $func
                    ),
                ];
            }
        }

        // Check for backtick execution
        if ( preg_match( '/`[^`]+`/', $code ) ) {
            return [
                'valid' => false,
                'error' => __( 'Backtick execution is not allowed.', 'creator-core' ),
            ];
        }

        // Check for base64 encoded content (potential obfuscation)
        if ( preg_match( '/base64_decode\s*\(/i', $code ) ) {
            return [
                'valid' => false,
                'error' => __( 'Base64 decode is not allowed for security reasons.', 'creator-core' ),
            ];
        }

        // Check for superglobals manipulation
        $superglobals = [ '\$_GET', '\$_POST', '\$_REQUEST', '\$_SERVER', '\$_FILES', '\$_COOKIE', '\$_SESSION', '\$GLOBALS' ];
        foreach ( $superglobals as $sg ) {
            if ( preg_match( '/' . $sg . '\s*\[/', $code ) ) {
                return [
                    'valid' => false,
                    'error' => __( 'Direct superglobal access is not allowed.', 'creator-core' ),
                ];
            }
        }

        return [ 'valid' => true ];
    }

    /**
     * Prepare code for safe execution
     *
     * @param string $code The code to prepare.
     * @return string The prepared code.
     */
    private function prepare_code( string $code ): string {
        // Remove opening PHP tags if present
        $code = preg_replace( '/^<\?php\s*/i', '', $code );
        $code = preg_replace( '/^<\?\s*/i', '', $code );

        // Remove closing PHP tags
        $code = preg_replace( '/\s*\?>$/', '', $code );

        // Trim whitespace
        $code = trim( $code );

        return $code;
    }

    /**
     * Execute code with timeout protection
     *
     * @param string $code    The code to execute.
     * @param int    $timeout Timeout in seconds (default 30).
     * @return array{success: bool, output: string, error?: string}
     */
    public function execute_with_timeout( string $code, int $timeout = 30 ): array {
        $original_time_limit = ini_get( 'max_execution_time' );

        // Set temporary time limit
        set_time_limit( $timeout );

        $result = $this->execute( $code );

        // Restore original time limit
        set_time_limit( (int) $original_time_limit );

        return $result;
    }

    /**
     * Get list of forbidden functions
     *
     * @return array
     */
    public function get_forbidden_functions(): array {
        return $this->forbidden_functions;
    }

    /**
     * Check if a specific function is allowed
     *
     * @param string $function The function name to check.
     * @return bool
     */
    public function is_function_allowed( string $function ): bool {
        return ! in_array( strtolower( $function ), array_map( 'strtolower', $this->forbidden_functions ), true );
    }
}
