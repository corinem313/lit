<?php
/**
 * Guards against incomplete installs (missing files after a bad deploy).
 *
 * @package ForWP\SmartLink
 */

namespace ForWP\SmartLink;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies shipped files exist before bootstrap; deactivates instead of fatal error.
 */
final class Plugin_Integrity {

	private const TRANSIENT_KEY = 'forwp_smart_link_integrity_error';

	/**
	 * Plugin-relative paths required for a complete install.
	 *
	 * @return string[]
	 */
	private static function required_relative_paths(): array {
		return array(
			'includes/bootstrap.php',
			'includes/class-smart-link-destination.php',
			'includes/class-smart-link-cover-media.php',
			'includes/class-smart-link-page-lightbox-gallery.php',
			'includes/class-smart-link-body-image-gallery.php',
			'includes/class-smart-link-cover-lightbox.php',
			'includes/class-smart-link-featured-image-lightbox.php',
			'includes/class-block-attributes.php',
			'includes/class-block-inner-links.php',
			'includes/class-block-link.php',
			'includes/class-bootstrap.php',
			'build/editor/block.json',
			'build/editor/index.js',
			'assets/forwp-smart-link-frontend.css',
			'assets/forwp-smart-link-lightbox-gallery.js',
		);
	}

	/**
	 * @return string[]
	 */
	private static function missing_paths(): array {
		$missing = array();

		foreach ( self::required_relative_paths() as $relative ) {
			$absolute = FORWP_SMART_LINK_PATH . $relative;

			if ( ! is_readable( $absolute ) ) {
				$missing[] = $relative;
			}
		}

		return $missing;
	}

	/**
	 * Load bootstrap when intact; otherwise deactivate and surface an admin notice.
	 *
	 * @return bool True when bootstrap loaded.
	 */
	public static function verify_and_load(): bool {
		$missing = self::missing_paths();

		if ( array() === $missing ) {
			delete_site_transient( self::TRANSIENT_KEY );
			require_once FORWP_SMART_LINK_PATH . 'includes/bootstrap.php';
			return true;
		}

		self::handle_incomplete_install( $missing );

		return false;
	}

	/**
	 * @param string[] $missing Relative paths.
	 * @return void
	 */
	private static function handle_incomplete_install( array $missing ): void {
		$message = sprintf(
			/* translators: %s: comma-separated list of missing plugin files */
			__(
				'4WP Smart Link was deactivated because the installation is incomplete (missing: %s). Delete the plugin folder and reinstall from WordPress.org or upload a complete ZIP.',
				'4wp-smart-link'
			),
			implode( ', ', $missing )
		);

		set_site_transient( self::TRANSIENT_KEY, $message, WEEK_IN_SECONDS );

		if ( function_exists( 'deactivate_plugins' ) ) {
			deactivate_plugins( plugin_basename( FORWP_SMART_LINK_FILE ) );
		}
	}

	/**
	 * @return void
	 */
	public static function maybe_render_notice(): void {
		if ( ! is_admin() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$message = get_site_transient( self::TRANSIENT_KEY );

		if ( ! is_string( $message ) || '' === $message ) {
			return;
		}

		delete_site_transient( self::TRANSIENT_KEY );

		printf(
			'<div class="notice notice-error is-dismissible"><p><strong>%1$s</strong> %2$s</p></div>',
			esc_html__( '4WP Smart Link', '4wp-smart-link' ),
			esc_html( $message )
		);
	}
}
