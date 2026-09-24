<?php
/**
 * Inject selected WPCode CSS snippets into the block editor canvas.
 *
 * @package crnm-wpcode-editor-css
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register editor-style hooks.
 */
function crnm_wpec_editor_styles_init() {
	add_filter( 'block_editor_settings_all', 'crnm_wpec_add_block_editor_styles' );
}

/**
 * Add snippet CSS to Gutenberg editor settings so it lands in the iframe canvas.
 *
 * Gutenberg scopes these rules to `.editor-styles-wrapper`, which keeps
 * `body` / `html` selectors from styling the admin chrome.
 *
 * @param array $settings Block editor settings.
 * @return array
 */
function crnm_wpec_add_block_editor_styles( $settings ) {
	if ( ! is_array( $settings ) ) {
		return $settings;
	}

	$css = crnm_wpec_get_combined_editor_css();

	if ( '' === $css ) {
		return $settings;
	}

	if ( ! isset( $settings['styles'] ) || ! is_array( $settings['styles'] ) ) {
		$settings['styles'] = array();
	}

	$settings['styles'][] = array(
		'css'            => $css,
		'__unstableType' => 'user',
	);

	return $settings;
}
