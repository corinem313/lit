<?php
/**
 * Page-level core/image lightbox gallery (swipe / prev-next across blocks).
 *
 * @package ForWP\SmartLink
 */

namespace ForWP\SmartLink;

defined( 'ABSPATH' ) || exit;

/**
 * Groups Smart Link Cover lightboxes and standalone core/image lightboxes on one page.
 */
final class Smart_Link_Page_Lightbox_Gallery {

	/**
	 * Shared gallery id for the current request.
	 *
	 * @var string|null
	 */
	private static $page_gallery_id = null;

	/**
	 * DOM order counter for page gallery slides.
	 *
	 * @var int
	 */
	private static $order = 0;

	/**
	 * Register render filter for core/image lightbox blocks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'render_block_core/image', array( self::class, 'integrate_core_image_lightbox' ), 16, 3 );
	}

	/**
	 * Stable gallery id for all page-level lightbox participants on this request.
	 *
	 * @return string
	 */
	public static function get_page_gallery_id(): string {
		if ( null === self::$page_gallery_id ) {
			self::$page_gallery_id = uniqid( 'forwp-lightbox-page-', false );
		}

		return self::$page_gallery_id;
	}

	/**
	 * Next slide index in document order.
	 *
	 * @return int
	 */
	public static function next_order(): int {
		$order = self::$order;
		++self::$order;

		return $order;
	}

	/**
	 * Whether the block should join the page gallery (not an inner core/gallery).
	 *
	 * @param \WP_Block|null $instance Block instance.
	 * @return bool
	 */
	public static function uses_page_gallery( $instance ): bool {
		if ( $instance instanceof \WP_Block && ! empty( $instance->context['galleryId'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether this block joins the shared page lightbox sequence.
	 *
	 * @param \WP_Block|null       $instance Block instance.
	 * @param array<string, mixed> $attrs    Block attributes.
	 * @return bool
	 */
	public static function should_join_page_gallery( $instance, array $attrs = array() ): bool {
		if ( ! self::uses_page_gallery( $instance ) ) {
			return false;
		}

		return Smart_Link_Destination::is_lightbox_in_page_gallery( $attrs );
	}

	/**
	 * Gallery id for metadata: core/gallery context, page gallery, or solo lightbox.
	 *
	 * @param \WP_Block|null       $instance Block instance.
	 * @param array<string, mixed> $attrs    Block attributes.
	 * @return string
	 */
	public static function resolve_gallery_id( $instance, array $attrs = array() ): string {
		if ( $instance instanceof \WP_Block && ! empty( $instance->context['galleryId'] ) ) {
			return (string) $instance->context['galleryId'];
		}

		if ( ! Smart_Link_Destination::is_lightbox_in_page_gallery( $attrs ) ) {
			return uniqid( 'forwp-lightbox-solo-', false );
		}

		return self::get_page_gallery_id();
	}

	/**
	 * Order for page gallery; null when not in the page sequence.
	 *
	 * @param \WP_Block|null       $instance Block instance.
	 * @param array<string, mixed> $attrs    Block attributes.
	 * @return int|null
	 */
	public static function resolve_order( $instance, array $attrs = array() ) {
		if ( ! self::should_join_page_gallery( $instance, $attrs ) ) {
			return null;
		}

		return self::next_order();
	}

	/**
	 * Wrap markup so showLightbox() receives core/gallery context (same as core/gallery block).
	 *
	 * @param string      $html       Block HTML.
	 * @param string|null $gallery_id Gallery id; defaults to page gallery id.
	 * @return string
	 */
	public static function wrap_gallery_context( string $html, ?string $gallery_id = null ): string {
		if ( '' === trim( $html ) ) {
			return $html;
		}

		$gallery_id = $gallery_id ?? self::get_page_gallery_id();

		return sprintf(
			'<div class="forwp-smart-link-page-lightbox-gallery" data-wp-interactive="core/gallery" data-wp-context="%1$s">%2$s</div>',
			esc_attr(
				wp_json_encode(
					array( 'galleryId' => $gallery_id ),
					JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
				)
			),
			$html
		);
	}

	/**
	 * Attach page gallery metadata to a core/image lightbox figure (runs after core render).
	 *
	 * @param string         $block_content Rendered HTML.
	 * @param array          $block         Parsed block.
	 * @param \WP_Block|null $instance      Block instance.
	 * @return string
	 */
	public static function integrate_core_image_lightbox( $block_content, $block, $instance ) {
		$block_content = is_string( $block_content ) ? $block_content : '';

		if ( '' === $block_content || false === strpos( $block_content, 'wp-lightbox-container' ) ) {
			return $block_content;
		}

		if ( ! self::uses_page_gallery( $instance ) ) {
			return $block_content;
		}

		// Always load gallery extension (showLightbox imageRef fix) when lightbox markup is present.
		Bootstrap::enqueue_cover_lightbox();

		if ( function_exists( 'block_core_image_print_lightbox_overlay' ) ) {
			add_action( 'wp_footer', 'block_core_image_print_lightbox_overlay', 5 );
		}

		if ( ! Smart_Link_Body_Image_Gallery::is_page_gallery_enabled() ) {
			return $block_content;
		}

		if ( ! preg_match( '/\bdata-wp-key="([^"]+)"/', $block_content, $matches ) ) {
			return $block_content;
		}

		$image_key = $matches[1];

		/*
		 * Core sets uploadedSrc via wp_get_attachment_url( id ). When the Image
		 * block keeps a foreign attachment id (copied content) that URL is false
		 * and showLightbox() never opens. Prefer the rendered <img src>.
		 */
		$img_src = '';
		if ( preg_match( '/<img\b[^>]*\bsrc=(["\'])([^"\']+)\1/i', $block_content, $src_match ) ) {
			$img_src = esc_url_raw( $src_match[2] );
		}

		$caption = '';
		if ( preg_match( '/<figcaption\b[^>]*>(.*?)<\/figcaption>/is', $block_content, $cap_match ) ) {
			$caption = trim( wp_strip_all_tags( $cap_match[1] ) );
		}

		$gallery_id = self::get_page_gallery_id();
		$order      = self::next_order();

		$meta_patch = array(
			'galleryId' => $gallery_id,
			'order'     => $order,
		);

		if ( '' !== $img_src ) {
			$meta_patch['uploadedSrc'] = $img_src;
		}

		if ( '' !== $caption ) {
			$meta_patch['caption'] = $caption;
		}

		wp_interactivity_state(
			'core/image',
			array(
				'metadata' => array(
					$image_key => $meta_patch,
				),
			)
		);

		/*
		 * Do not wrap in data-wp-interactive="core/gallery".
		 * That parent (without a gallery view module in the tree) can leave
		 * core/image directives unhydrated — Enlarge click then no-ops because
		 * imageRef was never set. galleryId on metadata is enough for prev/next
		 * once showLightbox sets selectedGalleryId from metadata.
		 */
		return $block_content;
	}
}
