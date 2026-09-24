<?php
/**
 * Plugin Name:  CRNM Image Hover Swap
 * Plugin URI:   https://corinem.com
 * Description:  A Gutenberg block that swaps between two images on hover with a smooth transition. Images always cover the container.
 * Version:      1.3.1
 * Author:       Corinem LLC
 * Author URI:   https://corinem.com
 * License:      GPL-2.0-or-later
 * Text Domain:  crnm-image-hover-swap
 * Requires PHP: 7.4
 * Requires at least: 6.2
 * Requires Plugins: advanced-custom-fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CRNM_IHS_VERSION', '1.3.1' );
define( 'CRNM_IHS_PATH', plugin_dir_path( __FILE__ ) );
define( 'CRNM_IHS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load block registration only after ACF is available.
 *
 * The dependency slug is advanced-custom-fields. ACF PRO remaps that slug
 * to itself, so Pro satisfies it without a missing-plugin error.
 */
function crnm_ihs_load_acf_integration() {
	if ( ! function_exists( 'acf_register_block_type' ) || ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	require_once CRNM_IHS_PATH . 'includes/register-fields.php';
	require_once CRNM_IHS_PATH . 'includes/register-block.php';
}
add_action( 'acf/init', 'crnm_ihs_load_acf_integration', 5 );

/**
 * Notice when ACF never loaded. acf/init does not run in that case.
 */
function crnm_ihs_maybe_acf_missing_notice() {
	if ( function_exists( 'acf_register_block_type' ) ) {
		return;
	}

	crnm_ihs_acf_missing_notice();
}
add_action( 'admin_notices', 'crnm_ihs_maybe_acf_missing_notice' );

/**
 * Admin notice when ACF Pro is not active.
 */
function crnm_ihs_acf_missing_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'CRNM Image Hover Swap', 'crnm-image-hover-swap' ); ?>:</strong>
			<?php esc_html_e( 'This plugin requires Advanced Custom Fields PRO to be installed and active.', 'crnm-image-hover-swap' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Register styles and attach them to the block so WordPress injects
 * them into the iframed editor canvas (enqueue_block_editor_assets does not).
 */
function crnm_ihs_register_block_styles() {
	wp_register_style(
		'crnm-image-hover-swap',
		CRNM_IHS_URL . 'assets/css/crnm-image-hover-swap.css',
		array(),
		CRNM_IHS_VERSION
	);

	wp_register_style(
		'crnm-image-hover-swap-editor',
		CRNM_IHS_URL . 'assets/css/crnm-image-hover-swap-editor.css',
		array( 'crnm-image-hover-swap' ),
		CRNM_IHS_VERSION
	);

	wp_register_script(
		'crnm-image-hover-swap',
		CRNM_IHS_URL . 'assets/js/crnm-image-hover-swap.js',
		array(),
		CRNM_IHS_VERSION,
		true
	);

	if ( function_exists( 'wp_enqueue_block_style' ) ) {
		wp_enqueue_block_style(
			'acf/crnm-image-hover-swap',
			array(
				'handle' => 'crnm-image-hover-swap',
				'src'    => CRNM_IHS_URL . 'assets/css/crnm-image-hover-swap.css',
				'path'   => CRNM_IHS_PATH . 'assets/css/crnm-image-hover-swap.css',
				'ver'    => CRNM_IHS_VERSION,
			)
		);
	}

	$registry = WP_Block_Type_Registry::get_instance();
	$block    = $registry->get_registered( 'acf/crnm-image-hover-swap' );

	if ( $block ) {
		$block->style        = 'crnm-image-hover-swap';
		$block->editor_style = 'crnm-image-hover-swap-editor';
	}
}
add_action( 'init', 'crnm_ihs_register_block_styles', 20 );

/**
 * Enqueue CSS and JS whenever the block is used.
 */
function crnm_ihs_enqueue_render_assets() {
	wp_enqueue_style( 'crnm-image-hover-swap' );
	wp_enqueue_script( 'crnm-image-hover-swap' );
}

/**
 * Load styles in the editor iframe and on the front end.
 *
 * enqueue_block_assets is the hook WordPress uses to collect CSS for
 * the iframed block canvas. ACF's own enqueue_assets runs on
 * enqueue_block_editor_assets, which only reaches the editor chrome.
 */
function crnm_ihs_enqueue_block_assets() {
	$in_editor = is_admin() || ( function_exists( 'wp_should_load_block_editor_scripts_and_styles' ) && wp_should_load_block_editor_scripts_and_styles() );

	if ( ! $in_editor ) {
		return;
	}

	wp_enqueue_style( 'crnm-image-hover-swap' );
	wp_enqueue_style( 'crnm-image-hover-swap-editor' );
}
add_action( 'enqueue_block_assets', 'crnm_ihs_enqueue_block_assets' );

/**
 * Rewrite uploads URLs in the block HTML to root-relative paths.
 *
 * ACF stores absolute attachment URLs. A root-relative src keeps the
 * images working when the site domain changes.
 *
 * @param string $content Rendered block HTML.
 * @return string
 */
function crnm_ihs_relative_upload_urls( $content ) {
	return preg_replace(
		'#https?://[^"\']+(/wp-content/uploads/[^"\']+)#',
		'$1',
		$content
	);
}
add_filter( 'render_block_acf/crnm-image-hover-swap', 'crnm_ihs_relative_upload_urls' );
