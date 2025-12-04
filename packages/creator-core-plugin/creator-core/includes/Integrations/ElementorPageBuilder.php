<?php
/**
 * Elementor Page Builder
 *
 * Generates complete Elementor pages from natural language specifications.
 * Orchestrates page creation using ElementorSchemaLearner templates.
 *
 * @package CreatorCore
 * @since 1.0.0
 */

namespace CreatorCore\Integrations;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Context\ThinkingLogger;

/**
 * Class ElementorPageBuilder
 *
 * Creates production-ready Elementor pages from specification arrays.
 * Handles page structure, styling, responsive design, and SEO metadata.
 */
class ElementorPageBuilder {

	/**
	 * Thinking logger instance
	 *
	 * @var ThinkingLogger|null
	 */
	private ?ThinkingLogger $logger;

	/**
	 * Elementor version
	 *
	 * @var string
	 */
	private string $elementor_version = '0.0.0';

	/**
	 * Whether Elementor Pro is available
	 *
	 * @var bool
	 */
	private bool $is_pro = false;

	/**
	 * Constructor
	 *
	 * @param ThinkingLogger|null $logger Optional thinking logger.
	 * @throws \Exception If Elementor is not installed.
	 */
	public function __construct( ?ThinkingLogger $logger = null ) {
		$this->logger = $logger;
		$this->detect_elementor_setup();
	}

	/**
	 * Detect Elementor version and Pro status
	 *
	 * @throws \Exception If Elementor is not available.
	 */
	private function detect_elementor_setup(): void {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			throw new \Exception( 'Elementor is not installed or activated' );
		}

		$this->elementor_version = defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '0.0.0';
		$this->is_pro = defined( 'ELEMENTOR_PRO_VERSION' );

		$this->log(
			'Elementor ' . $this->elementor_version . ( $this->is_pro ? ' Pro' : '' ) . ' detected',
			'info'
		);
	}

	/**
	 * Generate a complete page from specification
	 *
	 * @param array $spec Page specification.
	 * @return array Result with page_id, url, edit_url.
	 * @throws \Exception On validation or creation failure.
	 */
	public function generate_page( array $spec ): array {
		$this->log( 'Starting page generation...', 'info' );

		// Validate specification.
		$this->validate_spec( $spec );
		$this->log( 'Specification validated', 'debug' );

		// Build sections.
		$this->log( 'Building page sections...', 'info' );
		$sections = $this->build_sections( $spec['sections'] ?? [] );
		$this->log( count( $sections ) . ' section(s) created', 'debug' );

		// Serialize Elementor data.
		$this->log( 'Serializing Elementor data...', 'debug' );
		$elementor_data = wp_json_encode( $sections );
		$this->log( 'Elementor data ready (' . strlen( $elementor_data ) . ' bytes)', 'debug' );

		// Create WordPress page.
		$this->log( 'Creating WordPress page...', 'info' );
		$page_id = $this->create_page( $spec, $elementor_data );
		$this->log( 'Page created (ID: ' . $page_id . ')', 'info' );

		// Add SEO metadata.
		if ( ! empty( $spec['seo'] ) ) {
			$this->log( 'Adding SEO metadata...', 'info' );
			$this->add_seo_metadata( $page_id, $spec['seo'] );
			$this->log( 'SEO metadata added', 'debug' );
		}

		// Set featured image if provided.
		if ( ! empty( $spec['featured_image'] ) ) {
			$this->log( 'Setting featured image...', 'debug' );
			$this->set_featured_image( $page_id, $spec['featured_image'] );
		}

		// Verify rendering.
		$this->log( 'Verifying page rendering...', 'debug' );
		$verified = $this->verify_rendering( $page_id );
		$this->log( 'Verification: ' . ( $verified ? 'PASS' : 'WARNING' ), $verified ? 'success' : 'warning' );

		// Clear Elementor cache.
		$this->clear_elementor_cache();

		$result = [
			'success'  => true,
			'page_id'  => $page_id,
			'url'      => get_permalink( $page_id ),
			'edit_url' => $this->get_elementor_edit_url( $page_id ),
			'wp_edit'  => get_edit_post_link( $page_id, 'raw' ),
		];

		$this->log( 'Page generation complete: ' . $result['url'], 'success' );

		return $result;
	}

	/**
	 * Build sections from specification
	 *
	 * @param array $section_specs Section specifications.
	 * @return array Elementor-compatible sections array.
	 */
	private function build_sections( array $section_specs ): array {
		$sections = [];

		foreach ( $section_specs as $spec ) {
			$section_type = $spec['type'] ?? 'custom';

			switch ( $section_type ) {
				case 'hero':
					$sections[] = ElementorSchemaLearner::build_hero_section( $spec );
					break;

				case 'features':
					$result = ElementorSchemaLearner::build_features_section( $spec );
					// Features can return multiple sections (header + features grid).
					if ( isset( $result[0] ) && is_array( $result[0] ) ) {
						$sections = array_merge( $sections, $result );
					} else {
						$sections[] = $result;
					}
					break;

				case 'cta':
					$sections[] = ElementorSchemaLearner::build_cta_section( $spec );
					break;

				default:
					$sections[] = $this->build_custom_section( $spec );
					break;
			}
		}

		return $sections;
	}

	/**
	 * Build a custom section from specification
	 *
	 * @param array $spec Section specification.
	 * @return array Elementor section structure.
	 */
	private function build_custom_section( array $spec ): array {
		$section = ElementorSchemaLearner::get_section_template( [
			'background_color' => $spec['background_color'] ?? '',
			'background_image' => $spec['background_image'] ?? '',
			'min_height'       => $spec['min_height'] ?? 0,
			'padding'          => $spec['padding'] ?? [ 'top' => 50, 'right' => 20, 'bottom' => 50, 'left' => 20 ],
		] );

		// Build columns.
		if ( ! empty( $spec['columns'] ) ) {
			$section['elements'] = $this->build_columns( $spec['columns'] );
		}

		return $section;
	}

	/**
	 * Build columns from specification
	 *
	 * @param array $column_specs Column specifications.
	 * @return array Elementor-compatible columns array.
	 */
	private function build_columns( array $column_specs ): array {
		$columns = [];
		$column_count = count( $column_specs );
		$column_width = (int) floor( 100 / max( 1, $column_count ) );

		foreach ( $column_specs as $spec ) {
			$width = $spec['width'] ?? $column_width;

			$column = ElementorSchemaLearner::get_column_template( $width, [
				'background_color' => $spec['background_color'] ?? '',
				'padding'          => $spec['padding'] ?? [ 'top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10 ],
				'vertical_align'   => $spec['vertical_align'] ?? 'top',
			] );

			// Build widgets.
			if ( ! empty( $spec['widgets'] ) ) {
				$column['elements'] = $this->build_widgets( $spec['widgets'] );
			}

			$columns[] = $column;
		}

		return $columns;
	}

	/**
	 * Build widgets from specification
	 *
	 * @param array $widget_specs Widget specifications.
	 * @return array Elementor-compatible widgets array.
	 */
	private function build_widgets( array $widget_specs ): array {
		$widgets = [];

		foreach ( $widget_specs as $spec ) {
			$widget_type = $spec['type'] ?? '';
			$widget = null;

			switch ( $widget_type ) {
				case 'heading':
					$widget = ElementorSchemaLearner::get_heading_widget(
						$spec['text'] ?? 'Heading',
						$spec['level'] ?? 'h2',
						[
							'color'       => $spec['color'] ?? '',
							'align'       => $spec['align'] ?? 'left',
							'font_size'   => $spec['font_size'] ?? 0,
							'font_weight' => $spec['font_weight'] ?? '',
						]
					);
					break;

				case 'text':
				case 'paragraph':
					$content = $spec['text'] ?? $spec['content'] ?? 'Text content';
					if ( strpos( $content, '<' ) === false ) {
						$content = '<p>' . $content . '</p>';
					}
					$widget = ElementorSchemaLearner::get_text_widget( $content, [
						'color'     => $spec['color'] ?? '',
						'align'     => $spec['align'] ?? 'left',
						'font_size' => $spec['font_size'] ?? 16,
					] );
					break;

				case 'button':
					$widget = ElementorSchemaLearner::get_button_widget(
						$spec['text'] ?? 'Button',
						$spec['url'] ?? '#',
						[
							'bg_color'      => $spec['bg_color'] ?? $spec['background_color'] ?? '#2563EB',
							'text_color'    => $spec['text_color'] ?? '#ffffff',
							'align'         => $spec['align'] ?? 'left',
							'size'          => $spec['size'] ?? 'md',
							'border_radius' => $spec['border_radius'] ?? 4,
						]
					);
					break;

				case 'image':
					$widget = ElementorSchemaLearner::get_image_widget(
						$spec['url'] ?? $spec['src'] ?? '',
						[
							'alt'        => $spec['alt'] ?? '',
							'caption'    => $spec['caption'] ?? '',
							'align'      => $spec['align'] ?? 'center',
							'width'      => $spec['width'] ?? 100,
							'width_unit' => $spec['width_unit'] ?? '%',
						]
					);
					break;

				case 'spacer':
					$widget = ElementorSchemaLearner::get_spacer_widget( $spec['height'] ?? 50 );
					break;

				case 'divider':
					$widget = ElementorSchemaLearner::get_divider_widget( [
						'style'  => $spec['style'] ?? 'solid',
						'weight' => $spec['weight'] ?? 1,
						'color'  => $spec['color'] ?? '#e0e0e0',
						'width'  => $spec['width'] ?? 100,
					] );
					break;

				case 'icon':
					$widget = ElementorSchemaLearner::get_icon_widget(
						$spec['icon'] ?? 'fas fa-star',
						[
							'color' => $spec['color'] ?? '#2563EB',
							'size'  => $spec['size'] ?? 50,
							'align' => $spec['align'] ?? 'center',
						]
					);
					break;

				case 'icon_box':
				case 'icon-box':
					$widget = ElementorSchemaLearner::get_icon_box_widget(
						$spec['icon'] ?? 'fas fa-check',
						$spec['title'] ?? 'Feature',
						$spec['description'] ?? '',
						[
							'icon_color' => $spec['icon_color'] ?? '#2563EB',
							'position'   => $spec['position'] ?? 'top',
						]
					);
					break;
			}

			if ( $widget ) {
				$widgets[] = $widget;
			}
		}

		return $widgets;
	}

	/**
	 * Create WordPress page with Elementor data
	 *
	 * @param array  $spec           Page specification.
	 * @param string $elementor_data JSON-encoded Elementor data.
	 * @return int Page ID.
	 * @throws \Exception On page creation failure.
	 */
	private function create_page( array $spec, string $elementor_data ): int {
		$page_args = [
			'post_type'    => 'page',
			'post_title'   => $spec['title'] ?? 'New Page',
			'post_status'  => $spec['status'] ?? 'draft',
			'post_content' => '',
			'post_excerpt' => $spec['excerpt'] ?? '',
		];

		// Set parent page if specified.
		if ( ! empty( $spec['parent_id'] ) ) {
			$page_args['post_parent'] = absint( $spec['parent_id'] );
		}

		// Set page template if specified.
		if ( ! empty( $spec['template'] ) ) {
			$page_args['page_template'] = $spec['template'];
		}

		$page_id = wp_insert_post( $page_args, true );

		if ( is_wp_error( $page_id ) ) {
			throw new \Exception( 'Failed to create page: ' . $page_id->get_error_message() );
		}

		// Save Elementor data.
		update_post_meta( $page_id, '_elementor_data', $elementor_data );
		update_post_meta( $page_id, '_elementor_version', $this->elementor_version );
		update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );

		// Set Elementor page template for proper rendering.
		update_post_meta( $page_id, '_wp_page_template', 'elementor_canvas' );

		return $page_id;
	}

	/**
	 * Add SEO metadata using RankMath or Yoast
	 *
	 * @param int   $page_id Page ID.
	 * @param array $seo     SEO configuration.
	 */
	private function add_seo_metadata( int $page_id, array $seo ): void {
		// RankMath SEO.
		if ( class_exists( '\RankMath' ) ) {
			if ( ! empty( $seo['title'] ) ) {
				update_post_meta( $page_id, 'rank_math_title', $seo['title'] );
			}
			if ( ! empty( $seo['description'] ) ) {
				update_post_meta( $page_id, 'rank_math_description', $seo['description'] );
			}
			if ( ! empty( $seo['focus_keyword'] ) ) {
				update_post_meta( $page_id, 'rank_math_focus_keyword', $seo['focus_keyword'] );
			}
			if ( ! empty( $seo['robots'] ) ) {
				update_post_meta( $page_id, 'rank_math_robots', $seo['robots'] );
			}
			$this->log( 'RankMath metadata saved', 'debug' );
			return;
		}

		// Yoast SEO.
		if ( defined( 'WPSEO_VERSION' ) ) {
			if ( ! empty( $seo['title'] ) ) {
				update_post_meta( $page_id, '_yoast_wpseo_title', $seo['title'] );
			}
			if ( ! empty( $seo['description'] ) ) {
				update_post_meta( $page_id, '_yoast_wpseo_metadesc', $seo['description'] );
			}
			if ( ! empty( $seo['focus_keyword'] ) ) {
				update_post_meta( $page_id, '_yoast_wpseo_focuskw', $seo['focus_keyword'] );
			}
			$this->log( 'Yoast metadata saved', 'debug' );
			return;
		}

		$this->log( 'No SEO plugin detected, skipping metadata', 'warning' );
	}

	/**
	 * Set featured image from URL
	 *
	 * @param int    $page_id   Page ID.
	 * @param string $image_url Image URL.
	 */
	private function set_featured_image( int $page_id, string $image_url ): void {
		// If it's an attachment ID.
		if ( is_numeric( $image_url ) ) {
			set_post_thumbnail( $page_id, absint( $image_url ) );
			return;
		}

		// If it's a URL, try to find existing attachment.
		global $wpdb;
		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment' LIMIT 1",
				$image_url
			)
		);

		if ( $attachment_id ) {
			set_post_thumbnail( $page_id, $attachment_id );
		} else {
			$this->log( 'Featured image not found in media library: ' . $image_url, 'warning' );
		}
	}

	/**
	 * Verify page rendering
	 *
	 * @param int $page_id Page ID.
	 * @return bool True if rendering appears valid.
	 */
	private function verify_rendering( int $page_id ): bool {
		$elementor_data = get_post_meta( $page_id, '_elementor_data', true );

		if ( empty( $elementor_data ) ) {
			return false;
		}

		$data = json_decode( $elementor_data, true );

		if ( ! is_array( $data ) || empty( $data ) ) {
			return false;
		}

		// Check that each section has the required structure.
		foreach ( $data as $section ) {
			if ( ! isset( $section['elType'] ) || 'section' !== $section['elType'] ) {
				return false;
			}
			if ( ! isset( $section['elements'] ) || ! is_array( $section['elements'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get Elementor edit URL
	 *
	 * @param int $page_id Page ID.
	 * @return string Edit URL.
	 */
	private function get_elementor_edit_url( int $page_id ): string {
		return add_query_arg(
			[
				'post'   => $page_id,
				'action' => 'elementor',
			],
			admin_url( 'post.php' )
		);
	}

	/**
	 * Clear Elementor cache
	 */
	private function clear_elementor_cache(): void {
		if ( class_exists( '\Elementor\Plugin' ) && method_exists( \Elementor\Plugin::$instance, 'files_manager' ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}

	/**
	 * Validate page specification
	 *
	 * @param array $spec Page specification.
	 * @throws \Exception On validation failure.
	 */
	private function validate_spec( array $spec ): void {
		if ( empty( $spec['title'] ) ) {
			throw new \Exception( 'Page title is required' );
		}

		if ( empty( $spec['sections'] ) || ! is_array( $spec['sections'] ) ) {
			throw new \Exception( 'At least one section is required' );
		}

		if ( count( $spec['sections'] ) > 10 ) {
			throw new \Exception( 'Maximum 10 sections allowed per page for performance' );
		}
	}

	/**
	 * Log a message
	 *
	 * @param string $message Message to log.
	 * @param string $level   Log level (info, debug, warning, error, success).
	 */
	private function log( string $message, string $level = 'info' ): void {
		if ( $this->logger ) {
			$this->logger->log( '[Elementor] ' . $message, $level );
		}
	}

	/**
	 * Get Elementor version
	 *
	 * @return string
	 */
	public function get_elementor_version(): string {
		return $this->elementor_version;
	}

	/**
	 * Check if Elementor Pro is available
	 *
	 * @return bool
	 */
	public function has_pro(): bool {
		return $this->is_pro;
	}

	/**
	 * Get available page templates
	 *
	 * @return array Template options.
	 */
	public static function get_available_templates(): array {
		return [
			'default'               => 'Default Template',
			'elementor_canvas'      => 'Elementor Canvas (Full Width)',
			'elementor_header_footer' => 'Elementor Header & Footer',
		];
	}

	/**
	 * Generate page from simple prompt-style specification
	 *
	 * This method provides a simpler interface for AI-generated content.
	 *
	 * @param string $title       Page title.
	 * @param string $description Page description/purpose.
	 * @param array  $sections    Simple section definitions.
	 * @return array Result with page_id, url, edit_url.
	 */
	public function generate_from_prompt( string $title, string $description, array $sections ): array {
		$spec = [
			'title'    => $title,
			'status'   => 'draft',
			'sections' => $sections,
			'seo'      => [
				'title'       => $title,
				'description' => $description,
			],
		];

		return $this->generate_page( $spec );
	}
}
