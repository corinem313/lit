<?php
/**
 * Register the ACF block for CRNM Image Hover Swap.
 *
 * @package CRNM_Image_Hover_Swap
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Image Hover Swap block type with ACF.
 */
function crnm_ihs_register_block() {
	if ( ! function_exists( 'acf_register_block_type' ) ) {
		return;
	}

	acf_register_block_type( array(
		'name'            => 'crnm-image-hover-swap',
		'title'           => __( 'CRNM Image Hover Swap', 'crnm-image-hover-swap' ),
		'description'     => __( 'A block that swaps between two images on hover.', 'crnm-image-hover-swap' ),
		'category'        => 'media',
		'icon'            => 'format-image',
		'keywords'        => array( 'image', 'hover', 'swap', 'crnm' ),
		'mode'            => 'preview',
		'supports'        => array(
			'align'  => true,
			'anchor' => true,
			'jsx'    => false,
		),
		'render_template' => CRNM_IHS_PATH . 'templates/block-image-hover-swap.php',
	) );
}
add_action( 'acf/init', 'crnm_ihs_register_block' );
