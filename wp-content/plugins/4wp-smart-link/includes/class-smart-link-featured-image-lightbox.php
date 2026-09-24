<?php
/**
 * Core/image lightbox integration for Post Featured Image + Smart Link.
 *
 * @package ForWP\SmartLink
 */

namespace ForWP\SmartLink;

defined( 'ABSPATH' ) || exit;

/**
 * Adds Enlarge on click to core/post-featured-image (Query Loop templates).
 */
final class Smart_Link_Featured_Image_Lightbox {

	/**
	 * Whether the core lightbox footer overlay was scheduled.
	 *
	 * @var bool
	 */
	private static $overlay_scheduled = false;

	/**
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $attrs         Block attributes.
	 * @param array                $block         Parsed block.
	 * @param \WP_Block|null       $instance      Block instance.
	 * @return string
	 */
	public static function render( $block_content, array $attrs, array $block, $instance = null ) {
		$block_content = is_string( $block_content ) ? trim( $block_content ) : '';

		if ( '' === $block_content ) {
			return '';
		}

		$image = self::collect_image_data( $block, $instance );

		if ( '' === $image['uploaded_src'] ) {
			return $block_content;
		}

		self::enqueue_assets();

		$block_content = self::remove_post_permalink_link( $block_content );
		$context_id    = self::register_interactivity( $image, $block, $instance, $attrs );
		$updated       = self::apply_lightbox_to_figure_markup( $block_content, $context_id );

		if ( null === $updated ) {
			return $block_content;
		}

		/*
		 * Do not wrap Featured Image in data-wp-interactive="core/gallery".
		 * Nested interactive regions prevent core/image data-wp-init from setting
		 * imageRef/buttonRef, so showLightbox returns early. Gallery id/order stay
		 * in core/image metadata for navigation when selectedGalleryId is set from
		 * other page-gallery participants (Cover / core Image wraps).
		 */

		/**
		 * Filter Featured Image HTML after lightbox injection.
		 *
		 * @param string               $updated       Featured Image HTML.
		 * @param array<string, mixed> $image         Image metadata.
		 * @param array<string, mixed> $attrs         Block attributes.
		 * @param array                $block         Parsed block.
		 * @param \WP_Block|null       $instance      Block instance.
		 * @param string               $block_content Original HTML.
		 */
		return (string) apply_filters(
			'forwp_smart_link_featured_image_lightbox_markup',
			$updated,
			$image,
			$attrs,
			$block,
			$instance,
			$block_content
		);
	}

	/**
	 * @param string $html Figure markup.
	 * @return string
	 */
	private static function remove_post_permalink_link( string $html ): string {
		$replaced = preg_replace(
			'#(<figure\b[^>]*>)\s*<a\b[^>]*>([\s\S]*?)</a>#i',
			'$1$2',
			$html,
			1
		);

		return is_string( $replaced ) ? $replaced : $html;
	}

	/**
	 * @param string $html            Figure HTML.
	 * @param string $unique_image_id Context image id.
	 * @return string|null
	 */
	private static function apply_lightbox_to_figure_markup( string $html, string $unique_image_id ): ?string {
		$processor = new \WP_HTML_Tag_Processor( $html );

		if ( ! $processor->next_tag(
			array(
				'tag_name'   => 'FIGURE',
				'class_name' => 'wp-block-post-featured-image',
			)
		) ) {
			return null;
		}

		// Match core/image lightbox: figure is the interactive wp-lightbox-container.
		$processor->add_class( 'forwp-smart-link-featured-image-has-lightbox' );
		$processor->add_class( 'wp-lightbox-container' );
		$processor->set_attribute( 'data-wp-interactive', 'core/image' );
		$processor->set_attribute( 'data-wp-key', $unique_image_id );
		$processor->set_attribute(
			'data-wp-context',
			wp_json_encode(
				array( 'imageId' => $unique_image_id ),
				JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
			)
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON_HEX_* encoded for attribute.
		$html = $processor->get_updated_html();

		$processor = new \WP_HTML_Tag_Processor( $html );
		$found_img = false;

		while ( $processor->next_tag( 'IMG' ) ) {
			$found_img = true;
			$processor->add_class( 'forwp-smart-link-featured-image-lightbox__ref' );
			$processor->set_attribute( 'data-wp-init', 'callbacks.setButtonStyles' );
			$processor->set_attribute( 'data-wp-on--load', 'callbacks.setButtonStyles' );
			$processor->set_attribute( 'data-wp-on-window--resize', 'callbacks.setButtonStyles' );
			$processor->set_attribute( 'data-wp-on--pointerenter', 'actions.preloadImageWithDelay' );
			$processor->set_attribute( 'data-wp-on--pointerdown', 'actions.preloadImage' );
			$processor->set_attribute( 'data-wp-on--pointerleave', 'actions.cancelPreload' );
			// Core: click the image itself (not only the corner button).
			$processor->set_attribute( 'data-wp-on--click', 'actions.showLightbox' );
			$processor->set_attribute( 'data-wp-class--hide', 'state.isContentHidden' );
			$processor->set_attribute( 'data-wp-class--show', 'state.isContentVisible' );
			break;
		}

		if ( ! $found_img ) {
			return null;
		}

		$html = $processor->get_updated_html();

		if ( ! preg_match( '/<img[^>]+>/i', $html, $img_match ) ) {
			return null;
		}

		$button = $img_match[0] . self::build_trigger_button_markup();
		$updated = preg_replace( '/<img[^>]+>/i', $button, $html, 1 );

		return is_string( $updated ) ? $updated : null;
	}

	/**
	 * @return string
	 */
	private static function build_trigger_button_markup(): string {
		return '<button
			class="lightbox-trigger forwp-smart-link-featured-image-lightbox__trigger"
			type="button"
			aria-haspopup="dialog"
			data-wp-bind--aria-label="state.thisImage.triggerButtonAriaLabel"
			data-wp-init="callbacks.initTriggerButton"
			data-wp-on--click="actions.showLightbox"
			data-wp-style--right="state.thisImage.buttonRight"
			data-wp-style--top="state.thisImage.buttonTop"
		>
			<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 12 12" aria-hidden="true" focusable="false">
				<path fill="#fff" d="M2 0a2 2 0 0 0-2 2v2h1.5V2a.5.5 0 0 1 .5-.5h2V0H2Zm2 10.5H2a.5.5 0 0 1-.5-.5V8H0v2a2 2 0 0 0 2 2h2v-1.5ZM8 12v-1.5h2a.5.5 0 0 0 .5-.5V8H12v2a2 2 0 0 1-2 2H8Zm2-12a2 2 0 0 1 2 2v2h-1.5V2a.5.5 0 0 0-.5-.5H8V0h2Z" />
			</svg>
		</button>';
	}

	/**
	 * @param array                $block    Parsed block.
	 * @param \WP_Block|null       $instance Block instance.
	 * @return array<string, mixed>
	 */
	private static function collect_image_data( array $block, $instance ): array {
		$post_id = 0;

		if ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) ) {
			$post_id = (int) $instance->context['postId'];
		} elseif ( ! empty( $block['context']['postId'] ) ) {
			$post_id = (int) $block['context']['postId'];
		} elseif ( in_the_loop() ) {
			$post_id = (int) get_the_ID();
		}

		$attachment_id = $post_id > 0 ? (int) get_post_thumbnail_id( $post_id ) : 0;
		$uploaded_src  = '';
		$alt           = '';
		$img_srcset    = false;
		$img_width     = 'none';
		$img_height    = 'none';

		if ( $attachment_id > 0 ) {
			$uploaded_src = (string) wp_get_attachment_url( $attachment_id );
			$metadata     = wp_get_attachment_metadata( $attachment_id );
			$has_dims     = ( $metadata['width'] ?? '' ) && ( $metadata['height'] ?? '' );
			$srcset_size  = $has_dims ? array( $metadata['width'], $metadata['height'] ) : 'large';
			$img_srcset   = wp_get_attachment_image_srcset( $attachment_id, $srcset_size );
			$img_width    = $metadata['width'] ?? 'none';
			$img_height   = $metadata['height'] ?? 'none';
			$alt          = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );

			if ( '' === $alt && $post_id > 0 ) {
				$alt = trim( strip_tags( (string) get_the_title( $post_id ) ) );
			}
		}

		return array(
			'uploaded_src'    => $uploaded_src,
			'lightbox_srcset' => $img_srcset,
			'target_width'    => $img_width,
			'target_height'   => $img_height,
			'alt'             => $alt,
			'attachment_id'   => $attachment_id,
		);
	}

	/**
	 * @param array<string, mixed> $image    Image metadata.
	 * @param array                $block    Parsed block.
	 * @param \WP_Block|null       $instance Block instance.
	 * @param array<string, mixed> $attrs    Block attributes.
	 * @return string
	 */
	private static function register_interactivity( array $image, array $block, $instance, array $attrs ): string {
		wp_interactivity_config(
			'core/image',
			array(
				'defaultAriaLabel' => __( 'Enlarged image', '4wp-smart-link' ),
				'closeButtonText'  => esc_html__( 'Close', '4wp-smart-link' ),
				'prevButtonText'   => esc_html_x( 'Previous', 'previous image in lightbox', '4wp-smart-link' ),
				'nextButtonText'   => esc_html_x( 'Next', 'next image in lightbox', '4wp-smart-link' ),
			)
		);

		$custom_aria_label = null;

		if ( '' !== $image['alt'] ) {
			/* translators: %s: Image alt text. */
			$custom_aria_label = sprintf( __( 'Enlarged image: %s', '4wp-smart-link' ), $image['alt'] );
		}

		$unique_image_id = uniqid( 'forwp-featured-image-', false );

		$navigation = 'icon';

		if ( $instance instanceof \WP_Block ) {
			$navigation = $instance->context['navigationButtonType'] ?? 'icon';
		}

		$gallery_id = Smart_Link_Page_Lightbox_Gallery::resolve_gallery_id( $instance, $attrs );
		$order      = Smart_Link_Page_Lightbox_Gallery::resolve_order( $instance, $attrs );

		$metadata = array(
			'uploadedSrc'              => $image['uploaded_src'],
			'lightboxSrcset'           => $image['lightbox_srcset'],
			'figureClassNames'         => 'wp-block-image forwp-smart-link-lightbox-figure',
			'figureStyles'             => '',
			'imgClassNames'            => 'forwp-smart-link-featured-image-lightbox__ref',
			'imgStyles'                => '',
			'targetWidth'              => $image['target_width'],
			'targetHeight'             => $image['target_height'],
			'scaleAttr'                => false,
			'alt'                      => $image['alt'],
			'galleryId'                => $gallery_id,
			'customAriaLabel'          => $custom_aria_label,
			'navigationButtonType'     => $navigation,
			'triggerButtonAriaLabel'    => __( 'Enlarge', '4wp-smart-link' ),
		);

		if ( null !== $order ) {
			$metadata['order'] = $order;
		}

		wp_interactivity_state(
			'core/image',
			array(
				'metadata' => array(
					$unique_image_id => $metadata,
				),
			)
		);

		return $unique_image_id;
	}

	/**
	 * @return void
	 */
	private static function enqueue_assets(): void {
		Bootstrap::enqueue_cover_lightbox();

		if ( self::$overlay_scheduled ) {
			return;
		}

		if ( function_exists( 'block_core_image_print_lightbox_overlay' ) ) {
			add_action( 'wp_footer', 'block_core_image_print_lightbox_overlay', 5 );
			self::$overlay_scheduled = true;
		}
	}
}
