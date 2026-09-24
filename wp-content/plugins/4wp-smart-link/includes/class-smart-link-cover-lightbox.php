<?php
/**
 * Core/image lightbox integration for Cover + Smart Link.
 *
 * @package ForWP\SmartLink
 */

namespace ForWP\SmartLink;

defined( 'ABSPATH' ) || exit;

/**
 * Registers Cover background images in core/image lightbox state (gallery navigation).
 */
final class Smart_Link_Cover_Lightbox {

	/**
	 * Whether the core lightbox footer overlay was scheduled.
	 *
	 * @var bool
	 */
	private static $overlay_scheduled = false;

	/**
	 * Inject core-compatible lightbox trigger (icon only; cover area not clickable).
	 *
	 * @param string               $block_content Rendered cover HTML.
	 * @param array<string, mixed> $attrs         Merged block attributes.
	 * @param array                $block         Parsed block.
	 * @param \WP_Block|null       $instance      Block instance.
	 * @return string
	 */
	public static function render( $block_content, array $attrs, array $block, $instance = null ) {
		$block_content = is_string( $block_content ) ? trim( $block_content ) : '';

		if ( '' === $block_content ) {
			return '';
		}

		$image = self::collect_image_data( $attrs, $block, $instance );

		if ( '' === $image['uploaded_src'] ) {
			return $block_content;
		}

		self::enqueue_assets();
		$context_id = self::register_interactivity( $image, $block, $instance, $attrs );

		$updated = self::apply_lightbox_to_cover_markup( $block_content, $context_id, $image );

		if ( null === $updated ) {
			$fragment = self::build_fallback_lightbox_fragment( $image, $context_id );
			$updated  = self::insert_after_inner_container( $block_content, $fragment );
		}

		if ( ! ( $instance instanceof \WP_Block && ! empty( $instance->context['galleryId'] ) ) ) {
			$updated = Smart_Link_Page_Lightbox_Gallery::wrap_gallery_context(
				$updated,
				Smart_Link_Page_Lightbox_Gallery::resolve_gallery_id( $instance, $attrs )
			);
		}

		/**
		 * Filter Cover HTML after lightbox injection.
		 *
		 * @param string               $updated       Cover HTML.
		 * @param array<string, mixed> $image         Image metadata.
		 * @param array<string, mixed> $attrs         Block attributes.
		 * @param array                $block         Parsed block.
		 * @param \WP_Block|null       $instance      Block instance.
		 * @param string               $block_content Original cover HTML.
		 */
		return (string) apply_filters(
			'forwp_smart_link_cover_lightbox_markup',
			$updated,
			$image,
			$attrs,
			$block,
			$instance,
			$block_content
		);
	}

	/**
	 * Attach interactivity to the visible Cover background image (required for showLightbox imageRef.complete).
	 *
	 * @param string               $html            Cover HTML.
	 * @param string               $unique_image_id Context image id.
	 * @param array<string, mixed> $image           Image metadata.
	 * @return string|null Updated HTML or null when no background img was found.
	 */
	private static function apply_lightbox_to_cover_markup( string $html, string $unique_image_id, array $image ): ?string {
		$processor = new \WP_HTML_Tag_Processor( $html );
		$found_img = false;

		if ( ! $processor->next_tag(
			array(
				'tag_name'   => 'DIV',
				'class_name' => 'wp-block-cover',
			)
		) ) {
			return null;
		}

		$processor->add_class( 'forwp-smart-link-cover-has-lightbox' );
		$processor->set_attribute( 'data-wp-interactive', 'core/image' );
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

		while ( $processor->next_tag( 'IMG' ) ) {
			$class = $processor->get_attribute( 'class' );

			if ( ! is_string( $class ) || false === strpos( $class, 'wp-block-cover__image-background' ) ) {
				continue;
			}

			$found_img = true;
			$processor->add_class( 'forwp-smart-link-cover-lightbox__ref' );
			$processor->set_attribute( 'data-wp-init', 'callbacks.setButtonStyles' );
			$processor->set_attribute( 'data-wp-on--load', 'callbacks.setButtonStyles' );
			$processor->set_attribute( 'data-wp-on-window--resize', 'callbacks.setButtonStyles' );
			$processor->set_attribute( 'data-wp-on--pointerenter', 'actions.preloadImageWithDelay' );
			$processor->set_attribute( 'data-wp-on--pointerdown', 'actions.preloadImage' );
			$processor->set_attribute( 'data-wp-on--pointerleave', 'actions.cancelPreload' );

			break;
		}

		if ( ! $found_img ) {
			return null;
		}

		$html = $processor->get_updated_html();

		$wrapper = self::build_lightbox_trigger_wrapper( $unique_image_id );

		// Append after inner content so Cover keeps img → background → inner-container order (Gutenberg + Swiper).
		$inserted = self::insert_before_cover_close( $html, $wrapper );

		if ( $inserted === $html ) {
			return null;
		}

		return $inserted;
	}

	/**
	 * Lightbox trigger overlay (last child of .wp-block-cover).
	 *
	 * @param string $unique_image_id Context image id.
	 * @return string
	 */
	private static function build_lightbox_trigger_wrapper( string $unique_image_id ): string {
		return sprintf(
			'<div class="wp-lightbox-container forwp-smart-link-cover-lightbox forwp-smart-link-cover-lightbox--overlay" data-wp-key="%1$s">%2$s</div>',
			esc_attr( $unique_image_id ),
			self::build_trigger_button_markup( $unique_image_id )
		);
	}

	/**
	 * @param string $unique_image_id Context image id.
	 * @return string
	 */
	private static function build_trigger_button_markup( string $unique_image_id ): string {
		return '<button
			class="lightbox-trigger forwp-smart-link-cover-lightbox__trigger"
			type="button"
			aria-haspopup="dialog"
			data-wp-bind--aria-label="state.thisImage.triggerButtonAriaLabel"
			data-wp-init="callbacks.initTriggerButton"
			data-wp-on--click="actions.showLightbox"
		>
			<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 12 12" aria-hidden="true" focusable="false">
				<path fill="#fff" d="M2 0a2 2 0 0 0-2 2v2h1.5V2a.5.5 0 0 1 .5-.5h2V0H2Zm2 10.5H2a.5.5 0 0 1-.5-.5V8H0v2a2 2 0 0 0 2 2h2v-1.5ZM8 12v-1.5h2a.5.5 0 0 0 .5-.5V8H12v2a2 2 0 0 1-2 2H8Zm2-12a2 2 0 0 1 2 2v2h-1.5V2a.5.5 0 0 0-.5-.5H8V0h2Z" />
			</svg>
		</button>';
	}

	/**
	 * Fallback when Cover has no <img> background (span/gradient only).
	 *
	 * @param array<string, mixed> $image           Image metadata.
	 * @param string               $unique_image_id Context image id.
	 * @return string
	 */
	private static function build_fallback_lightbox_fragment( array $image, string $unique_image_id ): string {
		$context_attr = wp_interactivity_data_wp_context(
			array( 'imageId' => $unique_image_id ),
			'core/image'
		);

		$img_tag = sprintf(
			'<img class="forwp-smart-link-cover-lightbox__ref" src="%1$s" alt="%2$s" decoding="async" loading="eager" data-wp-init="callbacks.setButtonStyles" data-wp-on--load="callbacks.setButtonStyles" data-wp-on-window--resize="callbacks.setButtonStyles" data-wp-on--pointerenter="actions.preloadImageWithDelay" data-wp-on--pointerdown="actions.preloadImage" data-wp-on--pointerleave="actions.cancelPreload" />',
			esc_url( $image['uploaded_src'] ),
			esc_attr( $image['alt'] )
		);

		if ( is_numeric( $image['target_width'] ) && is_numeric( $image['target_height'] ) ) {
			$img_tag = str_replace(
				'<img ',
				sprintf(
					'<img width="%1$d" height="%2$d" ',
					(int) $image['target_width'],
					(int) $image['target_height']
				),
				$img_tag
			);
		}

		$figure = sprintf(
			'<figure class="wp-lightbox-container forwp-smart-link-cover-lightbox" data-wp-key="%1$s">%2$s%3$s</figure>',
			esc_attr( $unique_image_id ),
			$img_tag,
			self::build_trigger_button_markup( $unique_image_id )
		);

		return sprintf(
			'<div class="forwp-smart-link-cover-lightbox-fallback-host" data-wp-interactive="core/image" %1$s>%2$s</div>',
			$context_attr,
			$figure
		);
	}

	/**
	 * @param array<string, mixed> $attrs    Block attributes.
	 * @param array                $block    Parsed block.
	 * @param \WP_Block|null       $instance Block instance.
	 * @return array<string, mixed>
	 */
	private static function collect_image_data( array $attrs, array $block, $instance ): array {
		$uploaded_src = Smart_Link_Cover_Media::resolve_url( $attrs, $block, $instance );
		$alt          = isset( $attrs['alt'] ) ? trim( (string) $attrs['alt'] ) : '';
		$attachment_id = isset( $attrs['id'] ) ? (int) $attrs['id'] : 0;

		if ( $attachment_id <= 0 && ! empty( $attrs['useFeaturedImage'] ) ) {
			$post_id = 0;

			if ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) ) {
				$post_id = (int) $instance->context['postId'];
			} elseif ( ! empty( $block['context']['postId'] ) ) {
				$post_id = (int) $block['context']['postId'];
			} elseif ( in_the_loop() ) {
				$post_id = (int) get_the_ID();
			}

			if ( $post_id > 0 ) {
				$attachment_id = (int) get_post_thumbnail_id( $post_id );
			}
		}

		$img_srcset = false;
		$img_width  = 'none';
		$img_height = 'none';

		if ( $attachment_id > 0 ) {
			$uploaded_src = wp_get_attachment_url( $attachment_id ) ?: $uploaded_src;
			$metadata     = wp_get_attachment_metadata( $attachment_id );
			$has_dims     = ( $metadata['width'] ?? '' ) && ( $metadata['height'] ?? '' );
			$srcset_size  = $has_dims ? array( $metadata['width'], $metadata['height'] ) : 'large';
			$img_srcset   = wp_get_attachment_image_srcset( $attachment_id, $srcset_size );
			$img_width    = $metadata['width'] ?? 'none';
			$img_height   = $metadata['height'] ?? 'none';

			if ( '' === $alt ) {
				$alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
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
	 * @return string Unique image id for data-wp-context.
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

		$unique_image_id = uniqid( 'forwp-cover-', false );

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
			'imgClassNames'            => 'wp-block-cover__image-background forwp-smart-link-cover-lightbox__ref',
			'imgStyles'                => '',
			'targetWidth'              => $image['target_width'],
			'targetHeight'             => $image['target_height'],
			'scaleAttr'                => false,
			'alt'                      => $image['alt'],
			'galleryId'                => $gallery_id,
			'customAriaLabel'          => $custom_aria_label,
			'navigationButtonType'     => $navigation,
			'triggerButtonAriaLabel' => null,
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

	/**
	 * Insert after inner container (fallback path only).
	 *
	 * @param string $html     Cover HTML.
	 * @param string $fragment Lightbox host markup.
	 * @return string
	 */
	private static function insert_after_inner_container( string $html, string $fragment ): string {
		if ( preg_match(
			'/<div[^>]*\bwp-block-cover__inner-container\b[^>]*>/i',
			$html,
			$open,
			PREG_OFFSET_CAPTURE
		) ) {
			$start  = $open[0][1] + strlen( $open[0][0] );
			$depth  = 1;
			$pos    = $start;
			$length = strlen( $html );

			while ( $pos < $length && $depth > 0 ) {
				if ( ! preg_match( '/<\/?div\b/i', $html, $div, PREG_OFFSET_CAPTURE, $pos ) ) {
					break;
				}

				$is_close = '/' === $html[ $div[0][1] + 1 ];
				$depth   += $is_close ? -1 : 1;
				$pos      = $div[0][1] + strlen( $div[0][0] );

				if ( 0 === $depth ) {
					return substr( $html, 0, $pos ) . $fragment . substr( $html, $pos );
				}
			}
		}

		return self::insert_before_cover_close( $html, $fragment );
	}

	/**
	 * @param string $html     Cover HTML.
	 * @param string $fragment Lightbox markup.
	 * @return string
	 */
	private static function insert_before_cover_close( string $html, string $fragment ): string {
		if ( ! preg_match(
			'/<div[^>]*\bwp-block-cover\b[^>]*>/i',
			$html,
			$open,
			PREG_OFFSET_CAPTURE
		) ) {
			return $html . $fragment;
		}

		$start  = $open[0][1] + strlen( $open[0][0] );
		$depth  = 1;
		$pos    = $start;
		$length = strlen( $html );
		$insert = $length;

		while ( $pos < $length && $depth > 0 ) {
			if ( ! preg_match( '/<\/?div\b/i', $html, $div, PREG_OFFSET_CAPTURE, $pos ) ) {
				break;
			}

			$is_close = '/' === $html[ $div[0][1] + 1 ];
			$depth   += $is_close ? -1 : 1;
			$pos      = $div[0][1] + strlen( $div[0][0] );

			if ( 0 === $depth ) {
				$insert = $div[0][1];
				break;
			}
		}

		return substr( $html, 0, $insert ) . $fragment . substr( $html, $insert );
	}
}
