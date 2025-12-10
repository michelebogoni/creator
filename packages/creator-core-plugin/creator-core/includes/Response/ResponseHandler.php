<?php
/**
 * Response Handler - Process AI responses
 *
 * Phase 3: Handles different AI response types including code execution
 *
 * @package CreatorCore
 */

namespace CreatorCore\Response;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Proxy\ProxyClient;

/**
 * Class ResponseHandler
 *
 * Parses AI responses and executes appropriate actions.
 */
class ResponseHandler {

	/**
	 * Proxy client for fetching documentation
	 *
	 * @var ProxyClient
	 */
	private ProxyClient $proxy_client;

	/**
	 * Constructor
	 *
	 * @param ProxyClient|null $proxy_client Proxy client instance.
	 */
	public function __construct( ?ProxyClient $proxy_client = null ) {
		$this->proxy_client = $proxy_client ?? new ProxyClient();
	}

	/**
	 * Handle AI response based on type
	 *
	 * @param array $ai_response Parsed AI response with step, type, message, data, etc.
	 * @param array $context     WordPress context and execution history.
	 * @return array Processed result.
	 */
	public function handle( array $ai_response, array $context = [] ): array {
		$type = $ai_response['type'] ?? 'unknown';

		switch ( $type ) {
			case 'question':
			case 'plan':
			case 'complete':
				// These types just pass through to the UI
				return [
					'type'                   => $type,
					'step'                   => $ai_response['step'] ?? 'discovery',
					'status'                 => $ai_response['status'] ?? '',
					'message'                => $ai_response['message'] ?? '',
					'data'                   => $ai_response['data'] ?? [],
					'requires_confirmation'  => $ai_response['requires_confirmation'] ?? false,
					'continue_automatically' => false,
				];

			case 'execute':
				// Execute PHP code
				$code = $ai_response['data']['code'] ?? '';
				$execution_result = $this->execute_code( $code, $context );

				return [
					'type'                   => 'execute',
					'step'                   => $ai_response['step'] ?? 'implementation',
					'status'                 => $ai_response['status'] ?? 'Executed',
					'message'                => $ai_response['message'] ?? '',
					'data'                   => $ai_response['data'] ?? [],
					'execution_result'       => $execution_result,
					'continue_automatically' => $ai_response['continue_automatically'] ?? true,
				];

			case 'verify':
				// Execute verification code
				$code = $ai_response['data']['code'] ?? '';
				$verification_result = $this->execute_code( $code, $context );

				return [
					'type'                   => 'verify',
					'step'                   => $ai_response['step'] ?? 'verification',
					'status'                 => $ai_response['status'] ?? 'Verified',
					'message'                => $ai_response['message'] ?? '',
					'verification_result'    => $verification_result,
					'continue_automatically' => $ai_response['continue_automatically'] ?? true,
				];

			case 'request_docs':
				// Fetch plugin documentation
				$plugins = $ai_response['data']['plugins_needed'] ?? [];
				$docs = $this->fetch_documentation( $plugins );

				return [
					'type'                   => 'request_docs',
					'step'                   => $ai_response['step'] ?? 'discovery',
					'status'                 => $ai_response['status'] ?? 'Fetching documentation...',
					'message'                => $ai_response['message'] ?? '',
					'documentation'          => $docs,
					'continue_automatically' => true,
				];

			case 'error':
				return [
					'type'                   => 'error',
					'step'                   => $ai_response['step'] ?? 'implementation',
					'status'                 => $ai_response['status'] ?? 'Error',
					'message'                => $ai_response['message'] ?? 'An error occurred',
					'data'                   => $ai_response['data'] ?? [],
					'continue_automatically' => $ai_response['data']['recoverable'] ?? false,
				];

			default:
				return [
					'type'                   => 'error',
					'step'                   => 'discovery',
					'status'                 => 'Unknown response',
					'message'                => 'Received unknown response type from AI: ' . $type,
					'continue_automatically' => false,
				];
		}
	}

	/**
	 * Execute PHP code safely
	 *
	 * @param string $code    PHP code to execute.
	 * @param array  $context Execution context with last_result, etc.
	 * @return array Execution result with success, result, output, error.
	 */
	public function execute_code( string $code, array $context = [] ): array {
		if ( empty( trim( $code ) ) ) {
			return [
				'success' => false,
				'error'   => 'Empty code provided',
			];
		}

		// Security validation
		$security_check = $this->validate_code_security( $code );
		if ( ! $security_check['passed'] ) {
			return [
				'success'    => false,
				'error'      => 'Code blocked: contains forbidden functions',
				'violations' => $security_check['violations'],
			];
		}

		// Prepare code for execution
		$code = $this->prepare_code( $code );

		// Make context available to the code
		$last_result = $context['last_result'] ?? null;

		// Capture output
		ob_start();

		// Set custom error handler
		$errors = [];
		set_error_handler( function( $errno, $errstr, $errfile, $errline ) use ( &$errors ) {
			$errors[] = [
				'type'    => $errno,
				'message' => $errstr,
				'file'    => $errfile,
				'line'    => $errline,
			];
			return true;
		} );

		$success = false;
		$result  = null;

		try {
			// Execute the code
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$result = @eval( $code );
			$success = true;
		} catch ( \Throwable $e ) {
			$errors[] = [
				'type'    => E_ERROR,
				'message' => $e->getMessage(),
				'file'    => $e->getFile(),
				'line'    => $e->getLine(),
			];
		}

		// Restore error handler
		restore_error_handler();

		$output = ob_get_clean();

		// Check for errors
		if ( ! empty( $errors ) ) {
			return [
				'success' => false,
				'error'   => $errors[0]['message'] ?? 'Execution error',
				'errors'  => $errors,
				'output'  => $output,
			];
		}

		// If result is null but no errors, consider it success
		if ( $result === null && $success ) {
			$result = [ 'success' => true, 'output' => $output ];
		}

		return [
			'success' => true,
			'result'  => $result,
			'output'  => $output,
		];
	}

	/**
	 * Prepare code for execution
	 *
	 * @param string $code Raw code.
	 * @return string Prepared code.
	 */
	private function prepare_code( string $code ): string {
		// Remove opening/closing PHP tags
		$code = preg_replace( '/^<\?php\s*/', '', trim( $code ) );
		$code = preg_replace( '/\?>\s*$/', '', $code );

		return trim( $code );
	}

	/**
	 * Validate code security
	 *
	 * @param string $code PHP code.
	 * @return array Validation result with 'passed' and 'violations'.
	 */
	private function validate_code_security( string $code ): array {
		$forbidden = [
			// System execution
			'exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open',
			'pcntl_exec', 'pcntl_fork',
			// Dangerous eval (we use it controlled, but code shouldn't call it)
			'eval', 'assert', 'create_function',
			// File system dangerous
			'unlink', 'rmdir',
			// Include/require
			'include', 'include_once', 'require', 'require_once',
			// Network
			'fsockopen', 'pfsockopen', 'stream_socket_client',
			// Serialization
			'unserialize',
			// Output/exit
			'exit', 'die',
			// PHP settings
			'ini_set', 'ini_alter', 'putenv', 'set_include_path',
		];

		$violations = [];

		foreach ( $forbidden as $func ) {
			$pattern = '/\b' . preg_quote( $func, '/' ) . '\s*\(/i';
			if ( preg_match( $pattern, $code ) ) {
				$violations[] = $func;
			}
		}

		// Check for backticks (shell execution)
		if ( preg_match( '/`[^`]+`/', $code ) ) {
			$violations[] = 'backtick shell execution';
		}

		return [
			'passed'     => empty( $violations ),
			'violations' => $violations,
		];
	}

	/**
	 * Fetch plugin documentation
	 *
	 * @param array $plugin_slugs Plugin slugs to fetch docs for.
	 * @return array Documentation keyed by plugin slug.
	 */
	private function fetch_documentation( array $plugin_slugs ): array {
		$docs = [];

		foreach ( $plugin_slugs as $slug ) {
			$doc = $this->proxy_client->get_plugin_docs( $slug );
			if ( $doc ) {
				$docs[ $slug ] = $doc;
			}
		}

		return $docs;
	}
}
