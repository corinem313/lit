<?php
/**
 * Resolve Cover background image URL for Smart Link "media" destination.
 *
 * @package ForWP\SmartLink
 */

namespace ForWP\SmartLink;

defined( 'ABSPATH' ) || exit;

/**
 * Cover block media URL helper (used when smartLinkDestination is "media").
 */
final class Smart_Link_Cover_Media {

	/**
	 * Full-size (or best available) URL for the Cover background image.
	 *
	 * @param array<string, mixed> $attrs    Merged block attributes (Cover + Smart Link).
	 * @param array                $block    Parsed block.
	 * @param \WP_Block|null       $instance Block instance.
	 * @return string
	 */
	public static function resolve_url( array $attrs, array $block, $instance = null ): string {
		if ( isset( $attrs['backgroundType'] ) && 'image' !== $attrs['backgroundType'] ) {
			return '';
		}

		if ( ! empty( $attrs['useFeaturedImage'] ) ) {
			$post_id = self::resolve_featured_post_id( $block, $instance );

			if ( $post_id > 0 ) {
				$thumb_url = get_the_post_thumbnail_url( $post_id, 'full' );

				if ( is_string( $thumb_url ) && '' !== $thumb_url ) {
					/**
					 * Filter Cover media URL when using the current post featured image.
					 *
					 * @param string         $thumb_url Resolved URL.
					 * @param int            $post_id   Post ID.
					 * @param array          $attrs     Block attributes.
					 * @param array          $block     Parsed block.
					 * @param \WP_Block|null $instance  Block instance.
					 */
					return (string) apply_filters(
						'forwp_smart_link_cover_media_url',
						$thumb_url,
						$post_id,
						$attrs,
						$block,
						$instance
					);
				}
			}

			return '';
		}

		$attachment_id = isset( $attrs['id'] ) ? (int) $attrs['id'] : 0;

		if ( $attachment_id > 0 ) {
			$url = wp_get_attachment_image_url( $attachment_id, 'full' );

			if ( is_string( $url ) && '' !== $url ) {
				/**
				 * Filter Cover media URL from attachment ID.
				 *
				 * @param string         $url      Resolved URL.
				 * @param int            $attach_id Attachment ID.
				 * @param array          $attrs    Block attributes.
				 * @param array          $block    Parsed block.
				 * @param \WP_Block|null $instance Block instance.
				 */
				return (string) apply_filters(
					'forwp_smart_link_cover_media_url',
					$url,
					$attachment_id,
					$attrs,
					$block,
					$instance
				);
			}
		}

		$url = isset( $attrs['url'] ) ? trim( (string) $attrs['url'] ) : '';

		if ( '' === $url ) {
			return '';
		}

		/**
		 * Filter Cover media URL from the block `url` attribute.
		 *
		 * @param string         $url      Raw URL from Cover.
		 * @param array          $attrs    Block attributes.
		 * @param array          $block    Parsed block.
		 * @param \WP_Block|null $instance Block instance.
		 */
		return (string) apply_filters(
			'forwp_smart_link_cover_media_url',
			$url,
			0,
			$attrs,
			$block,
			$instance
		);
	}

	/**
	 * @param array          $block    Parsed block.
	 * @param \WP_Block|null $instance Block instance.
	 * @return int
	 */
	private static function resolve_featured_post_id( array $block, $instance ): int {
		$post_id = 0;

		if ( $instance instanceof \WP_Block && ! empty( $instance->context['postId'] ) ) {
			$post_id = (int) $instance->context['postId'];
		} elseif ( ! empty( $block['context']['postId'] ) ) {
			$post_id = (int) $block['context']['postId'];
		}

		if ( $post_id <= 0 && in_the_loop() ) {
			$post_id = (int) get_the_ID();
		}

		/**
		 * Filter post ID for Cover useFeaturedImage Smart Link media URL.
		 *
		 * @param int            $post_id  Resolved post ID.
		 * @param array          $block    Parsed block.
		 * @param \WP_Block|null $instance Block instance.
		 */
		return (int) apply_filters( 'forwp_smart_link_cover_featured_post_id', $post_id, $block, $instance );
	}
}
