<?php
/**
 * Context Loader Simple - Basic WordPress context collection
 *
 * Phase 2: Collects essential WordPress information for AI
 *
 * @package CreatorCore
 */

namespace CreatorCore\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Class ContextLoaderSimple
 *
 * Collects basic WordPress environment info
 */
class ContextLoaderSimple {

	/**
	 * Get complete WordPress context
	 *
	 * @return array Context data for AI.
	 */
	public function get_context(): array {
		return [
			'wordpress' => $this->get_wordpress_info(),
			'php'       => $this->get_php_info(),
			'mysql'     => $this->get_mysql_info(),
			'theme'     => $this->get_theme_info(),
			'plugins'   => $this->get_plugins_list(),
		];
	}

	/**
	 * Get WordPress information
	 *
	 * @return array WordPress info.
	 */
	private function get_wordpress_info(): array {
		global $wp_version;

		return [
			'version'   => $wp_version,
			'locale'    => get_locale(),
			'multisite' => is_multisite(),
			'site_url'  => get_site_url(),
			'home_url'  => get_home_url(),
		];
	}

	/**
	 * Get PHP information
	 *
	 * @return array PHP info.
	 */
	private function get_php_info(): array {
		return [
			'version' => phpversion(),
		];
	}

	/**
	 * Get MySQL information
	 *
	 * @return array MySQL info.
	 */
	private function get_mysql_info(): array {
		global $wpdb;

		return [
			'version' => $wpdb->db_version(),
		];
	}

	/**
	 * Get active theme information
	 *
	 * @return array Theme info.
	 */
	private function get_theme_info(): array {
		$theme  = wp_get_theme();
		$parent = $theme->parent();

		return [
			'name'    => $theme->get( 'Name' ),
			'version' => $theme->get( 'Version' ),
			'parent'  => $parent ? $parent->get( 'Name' ) : null,
		];
	}

	/**
	 * Get list of installed plugins
	 *
	 * @return array Plugins list with name, slug, version, and active status.
	 */
	private function get_plugins_list(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', [] );

		$plugins = [];
		foreach ( $all_plugins as $path => $plugin ) {
			$slug = dirname( $path );
			if ( $slug === '.' ) {
				$slug = basename( $path, '.php' );
			}

			$plugins[] = [
				'name'    => $plugin['Name'],
				'slug'    => $slug,
				'version' => $plugin['Version'],
				'active'  => in_array( $path, $active_plugins, true ),
			];
		}

		return $plugins;
	}
}
