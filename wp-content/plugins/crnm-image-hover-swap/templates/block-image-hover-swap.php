<?php
/**
 * Block render template for CRNM Image Hover Swap.
 *
 * @package CRNM_Image_Hover_Swap
 *
 * @param array  $block      The block settings and attributes.
 * @param string $content    The block inner HTML (empty for ACF blocks).
 * @param bool   $is_preview True during editor preview.
 * @param int    $post_id    The post ID the block is rendering content for.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$crnm_default_image = get_field( 'crnm_default_image' );
$crnm_hover_image   = get_field( 'crnm_hover_image' );
$crnm_width         = absint( get_field( 'crnm_container_width' ) ?: 400 );
$crnm_height        = absint( get_field( 'crnm_container_height' ) ?: 300 );
$crnm_effect        = get_field( 'crnm_hover_effect' );
$crnm_effect        = in_array( $crnm_effect, array( 'fade', 'fade-zoom' ), true ) ? $crnm_effect : 'fade';
$crnm_duration      = absint( get_field( 'crnm_effect_duration' ) ?: 700 );
$crnm_duration      = min( 2000, max( 200, $crnm_duration ) );
$crnm_zoom_duration = (int) round( $crnm_duration * 1.2 );

if ( function_exists( 'crnm_ihs_enqueue_render_assets' ) ) {
	crnm_ihs_enqueue_render_assets();
}

$crnm_classes = array(
	'crnm-image-hover-swap',
	'crnm-image-hover-swap--' . $crnm_effect,
);

if ( ! empty( $block['className'] ) ) {
	$crnm_classes[] = $block['className'];
}

if ( ! empty( $block['align'] ) ) {
	$crnm_classes[] = 'align' . $block['align'];
}

$crnm_style = sprintf(
	'width:%dpx;height:%dpx;position:relative;overflow:hidden;display:block;max-width:100%%;box-sizing:border-box;--crnm-ihs-duration:%sms;--crnm-ihs-zoom-duration:%sms;',
	$crnm_width,
	$crnm_height,
	$crnm_duration,
	$crnm_zoom_duration
);

$crnm_img_style = 'position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;margin:0;padding:0;border:0;display:block;';

if ( ! empty( $is_preview ) ) {
	static $crnm_ihs_preview_css_printed = false;

	if ( ! $crnm_ihs_preview_css_printed ) {
		$crnm_ihs_preview_css_printed = true;
		$crnm_css_files               = array(
			CRNM_IHS_PATH . 'assets/css/crnm-image-hover-swap.css',
			CRNM_IHS_PATH . 'assets/css/crnm-image-hover-swap-editor.css',
		);

		echo '<style id="crnm-ihs-editor-inline">';
		foreach ( $crnm_css_files as $crnm_css_file ) {
			if ( file_exists( $crnm_css_file ) ) {
				echo file_get_contents( $crnm_css_file ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}
		echo '</style>';
	}
}
?>
<div
	class="<?php echo esc_attr( implode( ' ', $crnm_classes ) ); ?>"
	style="<?php echo esc_attr( $crnm_style ); ?>"
	data-crnm-effect="<?php echo esc_attr( $crnm_effect ); ?>"
	data-crnm-duration="<?php echo esc_attr( (string) $crnm_duration ); ?>"
	tabindex="0"
	<?php echo ! empty( $block['anchor'] ) ? 'id="' . esc_attr( $block['anchor'] ) . '"' : ''; ?>
>
	<?php if ( $crnm_default_image && $crnm_hover_image ) : ?>
		<?php
		$crnm_default_src = is_array( $crnm_default_image ) ? $crnm_default_image['url'] : wp_get_attachment_image_url( $crnm_default_image, 'full' );
		$crnm_default_alt = is_array( $crnm_default_image ) ? $crnm_default_image['alt'] : '';
		$crnm_hover_src   = is_array( $crnm_hover_image ) ? $crnm_hover_image['url'] : wp_get_attachment_image_url( $crnm_hover_image, 'full' );
		$crnm_hover_alt   = is_array( $crnm_hover_image ) ? $crnm_hover_image['alt'] : '';
		?>
		<img
			class="crnm-ihs__img crnm-ihs__img--default"
			src="<?php echo esc_url( $crnm_default_src ); ?>"
			alt="<?php echo esc_attr( $crnm_default_alt ); ?>"
			style="<?php echo esc_attr( $crnm_img_style ); ?>"
		/>
		<img
			class="crnm-ihs__img crnm-ihs__img--hover"
			src="<?php echo esc_url( $crnm_hover_src ); ?>"
			alt="<?php echo esc_attr( $crnm_hover_alt ); ?>"
			style="<?php echo esc_attr( $crnm_img_style ); ?>"
		/>
	<?php else : ?>
		<div class="crnm-ihs__placeholder">
			<span class="dashicons dashicons-format-image"></span>
			<p><?php esc_html_e( 'Select both images in the sidebar to preview the hover swap.', 'crnm-image-hover-swap' ); ?></p>
		</div>
	<?php endif; ?>
</div>
