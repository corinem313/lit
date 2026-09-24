<?php
/**
 * Post meta for body Image lightbox → page gallery.
 *
 * @package ForWP\SmartLink
 */

namespace ForWP\SmartLink;

defined( 'ABSPATH' ) || exit;

/**
 * Document-level “Organize in lightbox” for standalone core/image blocks.
 */
final class Smart_Link_Body_Image_Gallery {

	public const META_KEY = 'forwp_smart_link_body_page_gallery';

	/**
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'register_meta' ) );
	}

	/**
	 * @return void
	 */
	public static function register_meta(): void {
		$post_types = get_post_types(
			array(
				'public'       => true,
				'show_in_rest' => true,
			),
			'names'
		);

		foreach ( $post_types as $post_type ) {
			register_post_meta(
				$post_type,
				self::META_KEY,
				array(
					'type'              => 'boolean',
					'single'            => true,
					'default'           => true,
					'show_in_rest'      => true,
					'auth_callback'     => static function ( $allowed, $meta_key, $post_id ): bool {
						unset( $allowed, $meta_key );
						return current_user_can( 'edit_post', (int) $post_id );
					},
					'sanitize_callback' => static function ( $value ): bool {
						return (bool) $value;
					},
				)
			);
		}
	}

	/**
	 * Whether body images on this post join the shared page lightbox.
	 *
	 * @param int|null $post_id Post ID; current post when null.
	 * @return bool
	 */
	public static function is_page_gallery_enabled( ?int $post_id = null ): bool {
		if ( null === $post_id ) {
			$post_id = (int) get_the_ID();
		}

		if ( $post_id <= 0 ) {
			return true;
		}

		$value = get_post_meta( $post_id, self::META_KEY, true );

		// Empty / never set → organize on (default).
		if ( '' === $value || null === $value ) {
			return true;
		}

		return (bool) $value;
	}
}
