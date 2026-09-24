<?php
/**
 * General Theme Settings.
 *
 * @since v1.0
 *
 * @return void
 */
function crnm_theme_support() {
	// Make theme available for translation: Translations can be filed in the /languages/ directory.
	load_theme_textdomain( 'crnm', __DIR__ . '/languages' );

	// Add support for Post thumbnails.
	add_theme_support( 'post-thumbnails' );
	// Add support for responsive embedded content.
	add_theme_support( 'responsive-embeds' );
	// Add support for Block Styles.
	add_theme_support( 'wp-block-styles' );

	// Add support for Editor Styles.
	add_theme_support( 'editor-styles' );
	// Enqueue Editor Styles.
	add_editor_style(
		array(
			'style-editor.css',
			'build/responsive.css',
		)
	);
}
add_action( 'after_setup_theme', 'crnm_theme_support' );

/**
 * Enqueue editor stylesheet (for iframed Post Editor):
 * https://make.wordpress.org/core/2023/07/18/miscellaneous-editor-changes-in-wordpress-6-3/#post-editor-iframed
 *
 * @since v1.2.2
 *
 * @return void
 */
function crnm_load_editor_styles() {
	if ( is_admin() ) {
		wp_enqueue_style( 'editor-style', get_theme_file_uri( 'style-editor.css' ) );

		$responsive_css = get_theme_file_path( 'build/responsive.css' );
		if ( file_exists( $responsive_css ) ) {
			wp_enqueue_style(
				'crnm-responsive-editor',
				get_theme_file_uri( 'build/responsive.css' ),
				array(),
				filemtime( $responsive_css )
			);
		}
	}
}
add_action( 'enqueue_block_assets', 'crnm_load_editor_styles' );

// Disable Block Directory: https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/filters/editor-filters.md#block-directory
remove_action( 'enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets' );
remove_action( 'enqueue_block_editor_assets', 'gutenberg_enqueue_block_editor_assets_block_directory' );

/**
 * Custom Template part.
 *
 * @param array $areas Template part areas.
 *
 * @return array
 */
function crnm_custom_template_part_area( $areas ) {
	array_push(
		$areas,
		array(
			'area'        => 'query',
			'label'       => esc_html__( 'Query', 'crnm' ),
			'description' => esc_html__( 'Custom query area', 'crnm' ),
			'icon'        => 'layout',
			'area_tag'    => 'div',
		)
	);

	return $areas;
}
add_filter( 'default_wp_template_part_areas', 'crnm_custom_template_part_area' );

/**
 * Enqueue CSS Stylesheets and Javascript files.
 *
 * @return void
 */
function crnm_load_scripts() {
	$theme_version = wp_get_theme()->get( 'Version' );

	// 1. Styles.
	wp_enqueue_style( 'style', get_stylesheet_uri(), array(), $theme_version );
	wp_enqueue_style( 'main', get_theme_file_uri( 'build/main.css' ), array(), $theme_version, 'all' ); // main.scss: Compiled custom styles.

	$responsive_css = get_theme_file_path( 'build/responsive.css' );
	if ( file_exists( $responsive_css ) ) {
		wp_enqueue_style(
			'crnm-responsive',
			get_theme_file_uri( 'build/responsive.css' ),
			array( 'main' ),
			filemtime( $responsive_css ),
			'all'
		); // responsive.scss: Compiled on save via `npm run watch`.
	}

	if ( is_rtl() ) {
		wp_enqueue_style( 'rtl', get_theme_file_uri( 'build/rtl.css' ), array(), $theme_version, 'all' );
	}

	// 2. Scripts.
	wp_enqueue_script( 'mainjs', get_theme_file_uri( 'build/main.js' ), array(), $theme_version, true );
}
add_action( 'wp_enqueue_scripts', 'crnm_load_scripts' );

/**
 * Rewrite uploads URLs in rendered blocks to root-relative paths.
 *
 * Priority 11 runs after core's background support (priority 10), which
 * injects background-image:url('https://…/wp-content/uploads/…') onto
 * Group, Quote, Pullquote, Verse, Accordion, and Post Content.
 *
 * @param string $content Rendered block HTML.
 * @return string
 */
function crnm_relative_upload_urls( $content ) {
	return preg_replace(
		'#https?://[^"\']+(/wp-content/uploads/[^"\']+)#',
		'$1',
		$content
	);
}
add_filter( 'render_block', 'crnm_relative_upload_urls', 11 );
