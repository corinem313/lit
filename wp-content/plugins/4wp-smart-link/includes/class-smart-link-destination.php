<?php
/**
 * Smart Link destination modes (custom URL, post, media file, core lightbox).
 *
 * @package ForWP\SmartLink
 */

namespace ForWP\SmartLink;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical destination values and legacy attribute normalization.
 */
final class Smart_Link_Destination {

	public const CUSTOM    = 'custom';
	public const POST      = 'post';
	public const MEDIA     = 'media';
	public const LIGHTBOX  = 'lightbox';

	/**
	 * Destinations that make the whole Cover/Group/Column navigate on click.
	 *
	 * @var list<string>
	 */
	public const CARD_LINK_DESTINATIONS = array(
		self::CUSTOM,
		self::POST,
		self::MEDIA,
	);

	/**
	 * @var list<string>
	 */
	private const KNOWN_DESTINATIONS = array(
		self::CUSTOM,
		self::POST,
		self::MEDIA,
		self::LIGHTBOX,
	);

	/**
	 * Resolve destination from smartLinkDestination or legacy booleans/URL.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return string Empty string when Smart Link is off.
	 */
	public static function resolve( array $attrs ): string {
		$stored = isset( $attrs['smartLinkDestination'] )
			? sanitize_key( (string) $attrs['smartLinkDestination'] )
			: '';

		if ( in_array( $stored, self::KNOWN_DESTINATIONS, true ) ) {
			/**
			 * Filter resolved Smart Link destination before legacy fallback.
			 *
			 * @param string $stored  Destination from attributes.
			 * @param array  $attrs   Full block attributes.
			 */
			return (string) apply_filters( 'forwp_smart_link_destination', $stored, $attrs );
		}

		if ( ! empty( $attrs['smartLinkToCurrentPost'] ) ) {
			return self::POST;
		}

		if ( ! empty( $attrs['smartLinkUrl'] ) ) {
			return self::CUSTOM;
		}

		return '';
	}

	/**
	 * Whether any Smart Link mode is active (including lightbox-only on Cover).
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return bool
	 */
	public static function is_active( array $attrs ): bool {
		$destination = self::resolve( $attrs );

		if ( '' === $destination ) {
			return false;
		}

		if ( self::LIGHTBOX === $destination ) {
			return self::is_lightbox_enabled( $attrs );
		}

		return true;
	}

	/**
	 * Whole-block click navigation (not lightbox trigger-only).
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return bool
	 */
	public static function uses_card_link( array $attrs ): bool {
		return in_array( self::resolve( $attrs ), self::CARD_LINK_DESTINATIONS, true );
	}

	/**
	 * Lightbox mode: icon trigger only; card area stays non-navigating.
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return bool
	 */
	public static function is_lightbox_mode( array $attrs ): bool {
		return self::LIGHTBOX === self::resolve( $attrs ) && self::is_lightbox_enabled( $attrs );
	}

	/**
	 * Per-block lightbox override (mirrors core/image `lightbox.enabled` shape).
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return bool
	 */
	public static function is_lightbox_enabled( array $attrs ): bool {
		if ( self::LIGHTBOX !== self::resolve( $attrs ) ) {
			return false;
		}

		if ( isset( $attrs['smartLinkLightbox'] ) && is_array( $attrs['smartLinkLightbox'] ) ) {
			if ( array_key_exists( 'enabled', $attrs['smartLinkLightbox'] ) ) {
				return (bool) $attrs['smartLinkLightbox']['enabled'];
			}
		}

		return true;
	}

	/**
	 * Whether this Cover lightbox slide joins the page-wide gallery (prev/next).
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return bool
	 */
	public static function is_lightbox_in_page_gallery( array $attrs ): bool {
		if ( ! self::is_lightbox_mode( $attrs ) ) {
			return false;
		}

		if ( isset( $attrs['smartLinkLightbox'] ) && is_array( $attrs['smartLinkLightbox'] ) ) {
			if ( array_key_exists( 'includeInPageGallery', $attrs['smartLinkLightbox'] ) ) {
				return (bool) $attrs['smartLinkLightbox']['includeInPageGallery'];
			}
		}

		return true;
	}

	/**
	 * Attribute patch to clear modes that conflict with a new destination.
	 *
	 * @param string $destination One of KNOWN_DESTINATIONS or empty to reset all.
	 * @return array<string, mixed>
	 */
	public static function conflicting_attributes_patch( string $destination ): array {
		$patch = array(
			'smartLinkDestination'   => $destination,
			'smartLinkUrl'           => '',
			'smartLinkNewTab'        => false,
			'smartLinkRel'           => '',
			'smartLinkAriaLabel'     => '',
			'smartLinkToCurrentPost' => false,
			'smartLinkLightbox'      => array(),
		);

		switch ( $destination ) {
			case self::CUSTOM:
				$patch['smartLinkToCurrentPost'] = false;
				$patch['smartLinkLightbox']      = array( 'enabled' => false );
				break;
			case self::POST:
				$patch['smartLinkUrl']      = '';
				$patch['smartLinkLightbox'] = array( 'enabled' => false );
				break;
			case self::MEDIA:
				$patch['smartLinkUrl']           = '';
				$patch['smartLinkToCurrentPost'] = false;
				$patch['smartLinkLightbox']      = array( 'enabled' => false );
				break;
			case self::LIGHTBOX:
				$patch['smartLinkUrl']           = '';
				$patch['smartLinkToCurrentPost'] = false;
				$patch['smartLinkLightbox']      = array(
					'enabled'              => true,
					'includeInPageGallery' => true,
				);
				break;
			default:
				$patch['smartLinkDestination'] = '';
				break;
		}

		return $patch;
	}
}
