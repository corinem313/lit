<?php
/**
 * Register ACF field group for the CRNM Image Hover Swap block.
 *
 * @package CRNM_Image_Hover_Swap
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the ACF field group with all fields for the Image Hover Swap block.
 */
function crnm_ihs_register_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
		'key'      => 'group_crnm_image_hover_swap',
		'title'    => 'CRNM Image Hover Swap',
		'fields'   => array(
			array(
				'key'           => 'field_crnm_default_image',
				'label'         => 'Default Image',
				'name'          => 'crnm_default_image',
				'type'          => 'image',
				'instructions'  => 'Select the image displayed by default.',
				'required'      => 1,
				'return_format' => 'array',
				'preview_size'  => 'medium',
				'library'       => 'all',
			),
			array(
				'key'           => 'field_crnm_hover_image',
				'label'         => 'Hover Image',
				'name'          => 'crnm_hover_image',
				'type'          => 'image',
				'instructions'  => 'Select the image displayed on hover.',
				'required'      => 1,
				'return_format' => 'array',
				'preview_size'  => 'medium',
				'library'       => 'all',
			),
			array(
				'key'           => 'field_crnm_container_width',
				'label'         => 'Container Width',
				'name'          => 'crnm_container_width',
				'type'          => 'number',
				'instructions'  => 'Set the container width in pixels.',
				'default_value' => 400,
				'min'           => 50,
				'step'          => 1,
				'append'        => 'px',
			),
			array(
				'key'           => 'field_crnm_container_height',
				'label'         => 'Container Height',
				'name'          => 'crnm_container_height',
				'type'          => 'number',
				'instructions'  => 'Set the container height in pixels.',
				'default_value' => 300,
				'min'           => 50,
				'step'          => 1,
				'append'        => 'px',
			),
			array(
				'key'           => 'field_crnm_hover_effect',
				'label'         => 'Hover Effect',
				'name'          => 'crnm_hover_effect',
				'type'          => 'select',
				'instructions'  => 'Choose how the images transition on hover.',
				'choices'       => array(
					'fade'      => 'Fade',
					'fade-zoom' => 'Fade + Zoom',
				),
				'default_value' => 'fade',
				'allow_null'    => 0,
				'multiple'      => 0,
				'ui'            => 0,
				'return_format' => 'value',
			),
			array(
				'key'           => 'field_crnm_effect_duration',
				'label'         => 'Effect Duration',
				'name'          => 'crnm_effect_duration',
				'type'          => 'range',
				'instructions'  => 'How long the hover transition takes. Default is 700ms.',
				'default_value' => 700,
				'min'           => 200,
				'max'           => 2000,
				'step'          => 50,
				'append'        => 'ms',
			),
		),
		'location' => array(
			array(
				array(
					'param'    => 'block',
					'operator' => '==',
					'value'    => 'acf/crnm-image-hover-swap',
				),
			),
		),
		'menu_order'            => 0,
		'position'              => 'normal',
		'style'                 => 'default',
		'label_placement'       => 'top',
		'instruction_placement' => 'label',
		'active'                => true,
	) );
}
add_action( 'acf/init', 'crnm_ihs_register_field_group' );
