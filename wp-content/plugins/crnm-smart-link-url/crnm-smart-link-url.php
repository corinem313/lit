<?php
/**
 * Plugin Name:       CRNM Smart Link URL
 * Plugin URI:        https://corinem.com
 * Description:       Replaces the 4WP Smart Link sidebar URL box with the link field used by Button blocks, including search and #anchor links.
 * Version:           1.0.1
 * Author:            Corinem LLC
 * Author URI:        https://corinem.com
 * License:           GPL-2.0-or-later
 * Text Domain:       crnm-smart-link-url
 * Requires PHP:      7.4
 * Requires at least: 6.5
 * Requires Plugins:  4wp-smart-link
 *
 * @package CRNM_Smart_Link_URL
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRNM_SLU_VERSION', '1.0.1' );
define( 'CRNM_SLU_PATH', plugin_dir_path( __FILE__ ) );
define( 'CRNM_SLU_URL', plugin_dir_url( __FILE__ ) );

/**
 * Whether 4WP Smart Link is loaded.
 *
 * @return bool
 */
function crnm_slu_smart_link_is_active() {
	return defined( 'FORWP_SMART_LINK_VERSION' );
}

/**
 * Editor script and sidebar styles.
 */
function crnm_slu_enqueue_editor_assets() {
	if ( ! crnm_slu_smart_link_is_active() ) {
		return;
	}

	$script_deps = array( 'wp-hooks', 'wp-compose', 'wp-element', 'wp-block-editor', 'wp-i18n' );

	if ( wp_script_is( 'forwp-smart-link-editor-script', 'registered' ) ) {
		$script_deps[] = 'forwp-smart-link-editor-script';
	}

	wp_enqueue_script(
		'crnm-smart-link-url',
		CRNM_SLU_URL . 'assets/js/editor.js',
		$script_deps,
		CRNM_SLU_VERSION,
		true
	);

	wp_set_script_translations( 'crnm-smart-link-url', 'crnm-smart-link-url' );

	$style_deps = array();

	if ( wp_style_is( 'forwp-smart-link-editor-style', 'registered' ) ) {
		$style_deps[] = 'forwp-smart-link-editor-style';
	}

	wp_enqueue_style(
		'crnm-smart-link-url',
		CRNM_SLU_URL . 'assets/css/editor.css',
		$style_deps,
		CRNM_SLU_VERSION
	);
}
add_action( 'enqueue_block_editor_assets', 'crnm_slu_enqueue_editor_assets' );

/**
 * Keep in-page anchors as #fragment.
 *
 * 4WP Smart Link prefixes scheme-less values with https:// before this filter runs,
 * so #our-methods arrives here as https://#our-methods.
 *
 * @param string $sanitized URL after esc_url_raw.
 * @param string $url       URL after the plugin's scheme prefix.
 * @return string
 */
function crnm_slu_preserve_hash_links( $sanitized, $url ) {
	foreach ( array( $url, $sanitized ) as $candidate ) {
		if ( ! is_string( $candidate ) ) {
			continue;
		}

		if ( preg_match( '#^https?://(\#.+)#i', trim( $candidate ), $matches ) ) {
			return $matches[1];
		}
	}

	return $sanitized;
}
add_filter( 'forwp_smart_link_sanitized_url', 'crnm_slu_preserve_hash_links', 10, 2 );
