<?php
/**
 * Plugin Name:       CRNM Under Construction
 * Plugin URI:        https://corinem.com
 * Description:       Serve a hardcoded HTML under-construction page to logged-out visitors.
 * Version:           1.1.0
 * Author:            Corinem LLC
 * Author URI:        https://corinem.com
 * License:           GPL-2.0-or-later
 * Text Domain:       crnm-under-construction
 * Requires PHP:      7.4
 * Requires at least: 6.5
 *
 * @package CRNM_Under_Construction
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRNM_UC_VERSION', '1.1.0' );
define( 'CRNM_UC_PATH', plugin_dir_path( __FILE__ ) );
define( 'CRNM_UC_URL', plugin_dir_url( __FILE__ ) );
define( 'CRNM_UC_OPTION', 'crnm_uc_enabled' );
define( 'CRNM_UC_PAGE_DIR', WP_CONTENT_DIR . '/crnm-under-construction' );
define( 'CRNM_UC_PAGE_URL', content_url( 'crnm-under-construction' ) );
define( 'CRNM_UC_BLUEPRINT_DIR', CRNM_UC_PATH . 'blueprint' );

/**
 * Absolute path to the working index.html.
 *
 * @return string
 */
function crnm_uc_page_file() {
	return trailingslashit( CRNM_UC_PAGE_DIR ) . 'index.html';
}

/**
 * Path relative to the WordPress root (ABSPATH), for display in settings.
 *
 * @param string $absolute Absolute filesystem path.
 * @return string e.g. wp-content/crnm-under-construction/index.html
 */
function crnm_uc_path_from_root( $absolute ) {
	$absolute = wp_normalize_path( $absolute );
	$root     = trailingslashit( wp_normalize_path( ABSPATH ) );

	if ( 0 === strpos( $absolute, $root ) ) {
		return ltrim( substr( $absolute, strlen( $root ) ), '/' );
	}

	$content = trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );
	if ( 0 === strpos( $absolute, $content ) ) {
		return 'wp-content/' . ltrim( substr( $absolute, strlen( $content ) ), '/' );
	}

	return $absolute;
}

/**
 * Whether the working page file exists and is readable.
 *
 * @return bool
 */
function crnm_uc_page_exists() {
	$file = crnm_uc_page_file();

	return is_readable( $file ) && is_file( $file );
}

/**
 * Whether under-construction mode is enabled.
 *
 * @return bool
 */
function crnm_uc_is_enabled() {
	return (bool) (int) get_option( CRNM_UC_OPTION, 0 );
}

/**
 * Recursively copy a directory.
 *
 * @param string $source Source directory.
 * @param string $dest   Destination directory.
 * @return bool True on success.
 */
function crnm_uc_copy_dir( $source, $dest ) {
	$source = untrailingslashit( $source );
	$dest   = untrailingslashit( $dest );

	if ( ! is_dir( $source ) ) {
		return false;
	}

	if ( ! wp_mkdir_p( $dest ) ) {
		return false;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $item ) {
		$relative = $iterator->getSubPathName();

		// Never copy install/cache dirs into the working page.
		if ( preg_match( '#(^|[/\\\\])(node_modules|\.git|\.sass-cache)([/\\\\]|$)#', $relative ) ) {
			continue;
		}

		$target = $dest . DIRECTORY_SEPARATOR . $relative;

		if ( $item->isDir() ) {
			if ( ! is_dir( $target ) && ! wp_mkdir_p( $target ) ) {
				return false;
			}
			continue;
		}

		if ( ! copy( $item->getPathname(), $target ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Copy the blueprint into the working page directory.
 *
 * @param bool $force When true, overwrite existing working files.
 * @return bool True on success.
 */
function crnm_uc_install_blueprint( $force = false ) {
	if ( ! $force && crnm_uc_page_exists() ) {
		return true;
	}

	return crnm_uc_copy_dir( CRNM_UC_BLUEPRINT_DIR, CRNM_UC_PAGE_DIR );
}

/**
 * Activation: create the working page from the blueprint when missing.
 */
function crnm_uc_activate() {
	crnm_uc_install_blueprint( false );

	if ( false === get_option( CRNM_UC_OPTION, false ) ) {
		add_option( CRNM_UC_OPTION, 0 );
	}
}
register_activation_hook( __FILE__, 'crnm_uc_activate' );

require_once CRNM_UC_PATH . 'includes/settings.php';
require_once CRNM_UC_PATH . 'includes/serve.php';
