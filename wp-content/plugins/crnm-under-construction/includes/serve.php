<?php
/**
 * Front-end under-construction page serving.
 *
 * @package CRNM_Under_Construction
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether this request should receive the under-construction page.
 *
 * @return bool
 */
function crnm_uc_should_serve() {
	if ( is_admin() ) {
		return false;
	}

	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return false;
	}

	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		return false;
	}

	if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
		return false;
	}

	if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
		return false;
	}

	if ( is_customize_preview() ) {
		return false;
	}

	$preview = isset( $_GET['crnm_uc_preview'] ) && '1' === $_GET['crnm_uc_preview'];

	if ( $preview ) {
		return current_user_can( 'manage_options' );
	}

	if ( ! crnm_uc_is_enabled() ) {
		return false;
	}

	if ( is_user_logged_in() ) {
		return false;
	}

	return true;
}

/**
 * Insert a <base> tag after <head> when one is not already present.
 *
 * @param string $html Document HTML.
 * @return string
 */
function crnm_uc_inject_base( $html ) {
	if ( false !== stripos( $html, '<base' ) ) {
		return $html;
	}

	$base = '<base href="' . esc_url( trailingslashit( CRNM_UC_PAGE_URL ) ) . '">';

	$replaced = preg_replace(
		'/<head([^>]*)>/i',
		'<head$1>' . $base,
		$html,
		1
	);

	if ( null === $replaced || $replaced === $html ) {
		return $base . $html;
	}

	return $replaced;
}

/**
 * Serve the working under-construction page and exit.
 */
function crnm_uc_serve_page() {
	if ( ! crnm_uc_should_serve() ) {
		return;
	}

	if ( ! crnm_uc_page_exists() ) {
		return;
	}

	$file = crnm_uc_page_file();
	$html = file_get_contents( $file );

	if ( false === $html ) {
		return;
	}

	$html = crnm_uc_inject_base( $html );

	status_header( 503 );
	nocache_headers();
	header( 'Retry-After: 86400' );
	header( 'Content-Type: text/html; charset=UTF-8' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	header( 'Cache-Control: no-store' );

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Serving author-controlled static HTML from disk.
	echo $html;
	exit;
}
add_action( 'template_redirect', 'crnm_uc_serve_page', 0 );
