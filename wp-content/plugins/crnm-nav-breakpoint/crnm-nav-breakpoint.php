<?php
/**
 * Plugin Name:       CRNM Navigation Breakpoint
 * Plugin URI:        https://corinem.com
 * Description:       Choose the viewport width where Navigation blocks set to Overlay: Mobile switch from the hamburger to the inline menu.
 * Version:           1.0.0
 * Author:            Corinem LLC
 * Author URI:        https://corinem.com
 * License:           GPL-2.0-or-later
 * Text Domain:       crnm-nav-breakpoint
 * Requires PHP:      7.4
 * Requires at least: 6.5
 *
 * @package CRNM_Nav_Breakpoint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRNM_NBP_VERSION', '1.0.0' );
define( 'CRNM_NBP_PATH', plugin_dir_path( __FILE__ ) );
define( 'CRNM_NBP_URL', plugin_dir_url( __FILE__ ) );
define( 'CRNM_NBP_OPTION', 'crnm_nbp_breakpoint' );
define( 'CRNM_NBP_DEFAULT', 600 );
define( 'CRNM_NBP_MIN', 320 );
define( 'CRNM_NBP_MAX', 1600 );

/**
 * Register the site-wide breakpoint so it is available to the REST settings endpoint.
 */
function crnm_nbp_register_setting() {
	register_setting(
		'crnm_nav_breakpoint',
		CRNM_NBP_OPTION,
		array(
			'type'              => 'integer',
			'description'       => __( 'Viewport width, in pixels, where Navigation blocks set to Overlay: Mobile switch to the inline menu.', 'crnm-nav-breakpoint' ),
			'default'           => CRNM_NBP_DEFAULT,
			'show_in_rest'      => array(
				'schema' => array(
					'type'    => 'integer',
					'minimum' => CRNM_NBP_MIN,
					'maximum' => CRNM_NBP_MAX,
					'default' => CRNM_NBP_DEFAULT,
				),
			),
			'sanitize_callback' => 'crnm_nbp_sanitize_breakpoint',
		)
	);
}
add_action( 'init', 'crnm_nbp_register_setting' );

/**
 * Clamp the breakpoint to the allowed range. An empty value keeps core's 600px.
 *
 * @param mixed $value Raw setting value.
 * @return int
 */
function crnm_nbp_sanitize_breakpoint( $value ) {
	if ( null === $value || false === $value || '' === $value ) {
		return CRNM_NBP_DEFAULT;
	}

	$value = absint( $value );

	if ( $value < CRNM_NBP_MIN ) {
		return CRNM_NBP_MIN;
	}

	if ( $value > CRNM_NBP_MAX ) {
		return CRNM_NBP_MAX;
	}

	return $value;
}

/**
 * Saved breakpoint, already clamped.
 *
 * @return int
 */
function crnm_nbp_get_breakpoint() {
	$stored = get_option( CRNM_NBP_OPTION, CRNM_NBP_DEFAULT );

	return crnm_nbp_sanitize_breakpoint( $stored );
}

/**
 * CSS that moves the Overlay: Mobile switch away from core's 600px.
 *
 * @param int $breakpoint Viewport width in pixels.
 * @return string
 */
function crnm_nbp_build_css( $breakpoint ) {
	$breakpoint = (int) $breakpoint;
	$root       = ':root { --crnm-nav-breakpoint: ' . $breakpoint . 'px; }';

	if ( $breakpoint < CRNM_NBP_DEFAULT ) {
		return $root . '
@media (min-width: ' . $breakpoint . 'px) {
	.wp-block-navigation__responsive-container:not(.hidden-by-default):not(.is-menu-open) {
		display: block;
		width: 100%;
		position: relative;
		z-index: auto;
		background-color: inherit;
	}
	.wp-block-navigation__responsive-container:not(.hidden-by-default):not(.is-menu-open) .wp-block-navigation__responsive-container-close {
		display: none;
	}
	.wp-block-navigation__responsive-container-open:not(.always-shown) {
		display: none;
	}
}';
	}

	$max = $breakpoint - 1;

	return $root . '
@media (max-width: ' . $max . 'px) {
	.wp-block-navigation__responsive-container:not(.hidden-by-default):not(.is-menu-open) {
		display: none;
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		width: auto;
		z-index: auto;
	}
	.wp-block-navigation__responsive-container-open:not(.always-shown) {
		display: flex;
	}
}';
}

/**
 * Attach the override to the Navigation block stylesheet on the front end and in the editor canvas.
 */
function crnm_nbp_enqueue_styles() {
	static $added = false;

	if ( $added ) {
		return;
	}

	$breakpoint = crnm_nbp_get_breakpoint();

	if ( CRNM_NBP_DEFAULT === $breakpoint ) {
		return;
	}

	if ( ! wp_style_is( 'wp-block-navigation', 'registered' ) ) {
		return;
	}

	wp_add_inline_style( 'wp-block-navigation', crnm_nbp_build_css( $breakpoint ) );
	$added = true;
}
add_action( 'wp_enqueue_scripts', 'crnm_nbp_enqueue_styles' );
add_action( 'enqueue_block_assets', 'crnm_nbp_enqueue_styles' );

/**
 * Site Editor sidebar for the breakpoint.
 */
function crnm_nbp_enqueue_editor_sidebar() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen || 'site-editor' !== $screen->id ) {
		return;
	}

	wp_enqueue_script(
		'crnm-nbp-editor-sidebar',
		CRNM_NBP_URL . 'assets/js/editor-sidebar.js',
		array( 'wp-plugins', 'wp-editor', 'wp-edit-site', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ),
		CRNM_NBP_VERSION,
		true
	);

	wp_enqueue_style(
		'crnm-nbp-editor-sidebar',
		CRNM_NBP_URL . 'assets/css/editor-sidebar.css',
		array(),
		CRNM_NBP_VERSION
	);

	wp_localize_script(
		'crnm-nbp-editor-sidebar',
		'crnmNbpSettings',
		array(
			'breakpoint' => crnm_nbp_get_breakpoint(),
			'restUrl'    => esc_url_raw( rest_url( 'wp/v2/settings' ) ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'crnm_nbp_enqueue_editor_sidebar' );
