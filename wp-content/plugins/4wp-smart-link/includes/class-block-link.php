<?php
/**
 * Smart Link wrapper for supported core blocks.
 *
 * @package ForWP\SmartLink
 */

namespace ForWP\SmartLink;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend rendering for Smart Link attributes on supported core blocks.
 */
final class Block_Link {

	/**
	 * Block name => data-forwp-smart-link / CSS modifier slug.
	 *
	 * @var array<string, string>
	 */
	private const BLOCK_MODIFIERS = array(
		'core/cover'               => 'cover',
		'core/group'               => 'group',
		'core/column'              => 'column',
		'core/post-featured-image' => 'post-featured-image',
	);

	/**
	 * Register render_block filters for each supported block.
	 *
	 * @return void
	 */
	public static function register(): void {
		$blocks = apply_filters( 'forwp_smart_link_supported_blocks', self::BLOCK_MODIFIERS );

		if ( ! is_array( $blocks ) ) {
			$blocks = self::BLOCK_MODIFIERS;
		}

		foreach ( $blocks as $block_name => $modifier ) {
			if ( ! is_string( $block_name ) || ! is_string( $modifier ) ) {
				continue;
			}
			add_filter(
				'render_block_' . $block_name,
				static function ( $block_content, $block, $instance = null ) use ( $modifier ) {
					return self::render( $block_content, $block, $modifier, $instance );
				},
				10,
				3
			);
		}
	}

	/**
	 * Wrap block HTML when Smart Link attributes resolve to a URL.
	 *
	 * @param string        $block_content Rendered block content.
	 * @param array         $block         Parsed block data.
	 * @param string        $modifier      Modifier slug for classes and data attribute.
	 * @param WP_Block|null $instance      Block instance (WP 5.9+).
	 * @return string
	 */
	public static function render( $block_content, $block, $modifier, $instance = null ) {
		if ( ! is_array( $block ) ) {
			return $block_content;
		}

		$modifier = sanitize_key( $modifier );
		if ( '' === $modifier ) {
			return $block_content;
		}

		$attrs = self::get_block_attrs( $block, $instance );

		if ( Smart_Link_Destination::is_lightbox_mode( $attrs ) ) {
			if ( 'cover' === $modifier ) {
				$block_content = is_string( $block_content ) ? trim( $block_content ) : '';

				if ( '' === $block_content ) {
					return '';
				}

				return Smart_Link_Cover_Lightbox::render( $block_content, $attrs, $block, $instance );
			}

			if ( 'post-featured-image' === $modifier ) {
				$block_content = is_string( $block_content ) ? trim( $block_content ) : '';

				if ( '' === $block_content ) {
					return '';
				}

				return Smart_Link_Featured_Image_Lightbox::render( $block_content, $attrs, $block, $instance );
			}

			return is_string( $block_content ) ? $block_content : '';
		}

		if ( 'post-featured-image' === $modifier ) {
			return is_string( $block_content ) ? $block_content : '';
		}

		$url = self::sanitize_smart_link_url( self::resolve_url( $attrs, $block, $instance ) );
		if ( '' === $url ) {
			return is_string( $block_content ) ? $block_content : '';
		}

		$block_content = is_string( $block_content ) ? trim( $block_content ) : '';
		if ( '' === $block_content ) {
			return '';
		}

		if ( self::markup_already_has_smart_link( $block_content, $modifier ) ) {
			return $block_content;
		}

		$block_content = self::normalize_block_markup_before_link( $block_content );
		if ( '' === $block_content ) {
			return '';
		}

		$has_inner_links = Block_Inner_Links::has_native_links( $block, $block_content );

		/**
		 * Choose host (div + data URL + JS) vs anchor wrap.
		 *
		 * @param bool   $has_inner_links True when inner native links exist.
		 * @param array  $block           Parsed block.
		 * @param string $block_content   Normalized inner HTML.
		 * @param string $modifier        Block modifier slug.
		 */
		$use_host_mode = (bool) apply_filters(
			'forwp_smart_link_use_host_mode',
			$has_inner_links,
			$block,
			$block_content,
			$modifier
		);

		if ( $use_host_mode ) {
			if ( 'column' === $modifier ) {
				return self::build_column_host( $block_content, $url, $attrs, $modifier );
			}

			return self::build_wrapper_host( $block_content, $url, $attrs, $modifier );
		}

		if ( 'column' === $modifier ) {
			return self::build_column_anchor( $block_content, $url, $attrs, $modifier );
		}

		return self::build_wrapper_anchor( $block_content, $url, $attrs, $modifier );
	}

	/**
	 * @param array         $block    Parsed block.
	 * @param WP_Block|null $instance Block instance.
	 * @return array<string, mixed>
	 */
	private static function get_block_attrs( array $block, $instance ): array {
		$from_block = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$from_parsed  = array();

		if ( $instance instanceof \WP_Block && is_array( $instance->parsed_block['attrs'] ?? null ) ) {
			$from_parsed = $instance->parsed_block['attrs'];
		}

		$from_instance = ( $instance instanceof \WP_Block && is_array( $instance->attributes ) )
			? $instance->attributes
			: array();

		return array_merge( $from_block, $from_parsed, $from_instance );
	}

	/**
	 * @param array<string, mixed> $attrs    Block attributes.
	 * @param array                $block    Parsed block.
	 * @param WP_Block|null        $instance Block instance.
	 * @return string
	 */
	private static function resolve_url( array $attrs, array $block, $instance ): string {
		$destination = Smart_Link_Destination::resolve( $attrs );

		if ( Smart_Link_Destination::POST === $destination || ! empty( $attrs['smartLinkToCurrentPost'] ) ) {
			$post_id = self::resolve_query_post_id( $block, $instance );

			if ( $post_id > 0 ) {
				return (string) get_permalink( $post_id );
			}

			return '';
		}

		if ( Smart_Link_Destination::MEDIA === $destination ) {
			return Smart_Link_Cover_Media::resolve_url( $attrs, $block, $instance );
		}

		if ( Smart_Link_Destination::CUSTOM === $destination || ! empty( $attrs['smartLinkUrl'] ) ) {
			return (string) ( $attrs['smartLinkUrl'] ?? '' );
		}

		return '';
	}

	/**
	 * Post ID for Query Loop / post template Smart Links.
	 *
	 * @param array                $block    Parsed block.
	 * @param \WP_Block|null $instance Block instance.
	 * @return int
	 */
	private static function resolve_query_post_id( array $block, $instance ): int {
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
		 * Filter the post ID used for smartLinkToCurrentPost resolution.
		 *
		 * @param int          $post_id  Resolved post ID (0 if unknown).
		 * @param array        $block    Parsed block.
		 * @param \WP_Block|null $instance Block instance.
		 */
		return (int) apply_filters( 'forwp_smart_link_query_post_id', $post_id, $block, $instance );
	}

	/**
	 * Normalize user-entered URLs before esc_url_raw (which drops scheme-less hosts).
	 *
	 * @param string $url Raw URL from attributes or permalink.
	 * @return string Sanitized URL or empty when invalid.
	 */
	private static function sanitize_smart_link_url( string $url ): string {
		$url = trim( $url );

		if ( '' === $url ) {
			return '';
		}

		if ( '/' === $url[0] && ( ! isset( $url[1] ) || '/' !== $url[1] ) ) {
			return esc_url_raw( home_url( $url ) );
		}

		if ( preg_match( '#^(mailto:|tel:|sms:)#i', $url ) ) {
			return esc_url_raw( $url );
		}

		if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $url ) ) {
			$url = 'https://' . ltrim( $url, '/' );
		}

		$sanitized = esc_url_raw( $url );

		/**
		 * Filter the sanitized Smart Link URL used on the front end.
		 *
		 * @param string $sanitized URL after esc_url_raw.
		 * @param string $url       Raw URL before sanitization.
		 */
		return (string) apply_filters( 'forwp_smart_link_sanitized_url', $sanitized, $url );
	}

	/**
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return array{target:string,rel:string,aria_label:string}
	 */
	private static function resolve_link_meta( array $attrs ): array {
		$target = ! empty( $attrs['smartLinkNewTab'] ) ? '_blank' : '';
		$rel    = ! empty( $attrs['smartLinkRel'] ) ? sanitize_text_field( (string) $attrs['smartLinkRel'] ) : '';

		if ( '_blank' === $target ) {
			$rels = preg_split( '/\s+/', strtolower( $rel ), -1, PREG_SPLIT_NO_EMPTY );
			if ( ! is_array( $rels ) ) {
				$rels = array();
			}
			if ( ! in_array( 'noopener', $rels, true ) ) {
				$rels[] = 'noopener';
			}
			if ( ! in_array( 'noreferrer', $rels, true ) ) {
				$rels[] = 'noreferrer';
			}
			$rel = implode( ' ', array_unique( $rels ) );
		}

		$aria_label = ! empty( $attrs['smartLinkAriaLabel'] )
			? sanitize_text_field( (string) $attrs['smartLinkAriaLabel'] )
			: '';

		return array(
			'target'     => $target,
			'rel'        => $rel,
			'aria_label' => $aria_label,
		);
	}

	/**
	 * Full anchor wrap when no conflicting inner links exist.
	 *
	 * @param string               $inner    Inner HTML.
	 * @param string               $url      Resolved URL.
	 * @param array<string, mixed> $attrs    Block attributes.
	 * @param string               $modifier Modifier slug.
	 * @return string
	 */
	private static function build_wrapper_anchor( string $inner, string $url, array $attrs, string $modifier ): string {
		return self::build_anchor_markup( $inner, $url, $attrs, $modifier );
	}

	/**
	 * Anchor inside the column element so `.wp-block-columns > .wp-block-column` layout is unchanged.
	 *
	 * @param string               $block_content Rendered column HTML.
	 * @param string               $url           Resolved URL.
	 * @param array<string, mixed> $attrs         Block attributes.
	 * @param string               $modifier      Modifier slug.
	 * @return string
	 */
	private static function build_column_anchor( string $block_content, string $url, array $attrs, string $modifier ): string {
		$parts = self::split_column_element( $block_content );

		if ( null === $parts ) {
			return self::build_anchor_markup( $block_content, $url, $attrs, $modifier );
		}

		return $parts['opening']
			. self::build_anchor_markup( $parts['inner'], $url, $attrs, $modifier )
			. $parts['closing'];
	}

	/**
	 * @param string               $inner    Markup wrapped by the anchor.
	 * @param string               $url      Resolved URL.
	 * @param array<string, mixed> $attrs    Block attributes.
	 * @param string               $modifier Modifier slug.
	 * @return string
	 */
	private static function build_anchor_markup( string $inner, string $url, array $attrs, string $modifier ): string {
		$meta = self::resolve_link_meta( $attrs );

		$wrapper_class = sprintf(
			'forwp-smart-link-wrapper forwp-smart-link-wrapper--%s',
			esc_attr( $modifier )
		);

		$attributes = array(
			'href="' . esc_url( $url ) . '"',
			'class="' . $wrapper_class . '"',
			'data-forwp-smart-link="' . esc_attr( $modifier ) . '"',
			'style="display:block;color:inherit;text-decoration:none;"',
		);

		if ( ! empty( $meta['target'] ) ) {
			$attributes[] = 'target="' . esc_attr( $meta['target'] ) . '"';
		}
		if ( ! empty( $meta['rel'] ) ) {
			$attributes[] = 'rel="' . esc_attr( $meta['rel'] ) . '"';
		}
		if ( ! empty( $meta['aria_label'] ) ) {
			$attributes[] = 'aria-label="' . esc_attr( $meta['aria_label'] ) . '"';
		}

		return sprintf(
			'<a %1$s>%2$s</a>',
			implode( ' ', $attributes ),
			$inner
		);
	}

	/**
	 * Non-anchor host when inner links must stay crawlable and clickable.
	 *
	 * @param string               $inner    Inner HTML.
	 * @param string               $url      Resolved URL.
	 * @param array<string, mixed> $attrs    Block attributes.
	 * @param string               $modifier Modifier slug.
	 * @return string
	 */
	private static function build_wrapper_host( string $inner, string $url, array $attrs, string $modifier ): string {
		Bootstrap::enqueue_frontend_script();

		$meta = self::resolve_link_meta( $attrs );

		$wrapper_class = sprintf(
			'forwp-smart-link-host forwp-smart-link-host--%s',
			esc_attr( $modifier )
		);

		$attributes = array(
			'class="' . $wrapper_class . '"',
			'data-forwp-smart-link="' . esc_attr( $modifier ) . '"',
			'data-forwp-smart-link-url="' . esc_url( $url ) . '"',
			'style="cursor:pointer;display:block"',
			'tabindex="0"',
			'role="link"',
		);

		if ( ! empty( $meta['target'] ) ) {
			$attributes[] = 'data-forwp-smart-link-target="' . esc_attr( $meta['target'] ) . '"';
		}
		if ( ! empty( $meta['rel'] ) ) {
			$attributes[] = 'data-forwp-smart-link-rel="' . esc_attr( $meta['rel'] ) . '"';
		}
		if ( ! empty( $meta['aria_label'] ) ) {
			$attributes[] = 'aria-label="' . esc_attr( $meta['aria_label'] ) . '"';
		}

		return sprintf(
			'<div %1$s>%2$s</div>',
			implode( ' ', $attributes ),
			$inner
		);
	}

	/**
	 * Host attributes on `.wp-block-column` itself — no wrapper between Columns and Column.
	 *
	 * @param string               $block_content Rendered column HTML.
	 * @param string               $url           Resolved URL.
	 * @param array<string, mixed> $attrs         Block attributes.
	 * @param string               $modifier      Modifier slug.
	 * @return string
	 */
	private static function build_column_host( string $block_content, string $url, array $attrs, string $modifier ): string {
		$parts = self::split_column_element( $block_content );

		if ( null === $parts ) {
			return self::build_wrapper_host( $block_content, $url, $attrs, $modifier );
		}

		Bootstrap::enqueue_frontend_script();

		$meta    = self::resolve_link_meta( $attrs );
		$opening = self::merge_host_into_column_opening( $parts['opening'], $url, $modifier, $meta );

		return $opening . $parts['inner'] . $parts['closing'];
	}

	/**
	 * @param string               $opening  Opening `.wp-block-column` tag.
	 * @param string               $url      Resolved URL.
	 * @param string               $modifier Modifier slug.
	 * @param array<string, string> $meta    Link meta from resolve_link_meta().
	 * @return string
	 */
	private static function merge_host_into_column_opening(
		string $opening,
		string $url,
		string $modifier,
		array $meta
	): string {
		$wrapper_class = sprintf(
			'forwp-smart-link-host forwp-smart-link-host--%s',
			$modifier
		);

		$opening = self::merge_class_into_opening_tag( $opening, $wrapper_class );
		$opening = self::insert_attribute_into_opening_tag( $opening, 'data-forwp-smart-link', $modifier );
		$opening = self::insert_attribute_into_opening_tag( $opening, 'data-forwp-smart-link-url', $url );
		$opening = self::insert_attribute_into_opening_tag( $opening, 'tabindex', '0' );
		$opening = self::insert_attribute_into_opening_tag( $opening, 'role', 'link' );
		$opening = self::merge_style_into_opening_tag( $opening, 'cursor:pointer' );

		if ( ! empty( $meta['target'] ) ) {
			$opening = self::insert_attribute_into_opening_tag(
				$opening,
				'data-forwp-smart-link-target',
				$meta['target']
			);
		}
		if ( ! empty( $meta['rel'] ) ) {
			$opening = self::insert_attribute_into_opening_tag(
				$opening,
				'data-forwp-smart-link-rel',
				$meta['rel']
			);
		}
		if ( ! empty( $meta['aria_label'] ) ) {
			$opening = self::insert_attribute_into_opening_tag( $opening, 'aria-label', $meta['aria_label'] );
		}

		return $opening;
	}

	/**
	 * Split rendered column markup into opening tag, inner blocks, and closing tag.
	 *
	 * @param string $html Rendered column HTML.
	 * @return array{opening:string,inner:string,closing:string}|null
	 */
	private static function split_column_element( string $html ): ?array {
		$html = trim( $html );
		if ( '' === $html || ! preg_match( '/<div\b/i', $html, $match, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}

		$tag_start = (int) $match[0][1];
		if ( 0 !== $tag_start ) {
			return null;
		}

		$balanced = self::extract_balanced_div_at( $html, $tag_start );
		if ( null === $balanced || $balanced['outer_len'] !== strlen( $html ) ) {
			return null;
		}

		$closing_bracket = strpos( $html, '>', $tag_start );
		if ( false === $closing_bracket ) {
			return null;
		}

		$opening = substr( $html, $tag_start, $closing_bracket - $tag_start + 1 );

		if ( ! preg_match( '/\bwp-block-column\b/', $opening ) ) {
			return null;
		}

		$outer = substr( $html, $tag_start, $balanced['outer_len'] );
		if ( ! preg_match( '/<\/div>\s*$/i', $outer, $close_match ) ) {
			return null;
		}

		return array(
			'opening' => $opening,
			'inner'   => $balanced['inner'],
			'closing' => trim( $close_match[0] ),
		);
	}

	/**
	 * @param string $opening     Opening tag including `>`.
	 * @param string $add_classes Classes to append.
	 * @return string
	 */
	private static function merge_class_into_opening_tag( string $opening, string $add_classes ): string {
		if ( preg_match( '/\bclass="([^"]*)"/', $opening, $match ) ) {
			$merged = trim( $match[1] . ' ' . $add_classes );

			return (string) preg_replace(
				'/\bclass="[^"]*"/',
				'class="' . esc_attr( $merged ) . '"',
				$opening,
				1
			);
		}

		return (string) preg_replace( '/\s*>$/', ' class="' . esc_attr( $add_classes ) . '">', $opening, 1 );
	}

	/**
	 * @param string $opening Opening tag including `>`.
	 * @param string $name    Attribute name.
	 * @param string $value   Attribute value.
	 * @return string
	 */
	private static function insert_attribute_into_opening_tag( string $opening, string $name, string $value ): string {
		$escaped = esc_attr( $value );

		if ( 'data-forwp-smart-link-url' === $name ) {
			$escaped = esc_url( $value );
		}

		return (string) preg_replace(
			'/\s*>$/',
			sprintf( ' %s="%s">', $name, $escaped ),
			$opening,
			1
		);
	}

	/**
	 * @param string $opening    Opening tag including `>`.
	 * @param string $declaration CSS declaration(s) to merge into style.
	 * @return string
	 */
	private static function merge_style_into_opening_tag( string $opening, string $declaration ): string {
		if ( preg_match( '/\bstyle="([^"]*)"/', $opening, $match ) ) {
			$merged = trim( $match[1] );
			if ( '' !== $merged && ! str_ends_with( $merged, ';' ) ) {
				$merged .= ';';
			}
			$merged .= $declaration;

			return (string) preg_replace(
				'/\bstyle="[^"]*"/',
				'style="' . esc_attr( $merged ) . '"',
				$opening,
				1
			);
		}

		return self::insert_attribute_into_opening_tag( $opening, 'style', $declaration );
	}

	/**
	 * Remove corrupt shells / legacy wraps before applying one outer wrapper.
	 *
	 * @param string $html Rendered HTML.
	 * @return string
	 */
	private static function normalize_block_markup_before_link( string $html ): string {
		$html = trim( $html );
		if ( '' === $html ) {
			return '';
		}

		$limit = 40;
		while ( $limit-- > 0 ) {
			$html = self::strip_empty_plugin_wrapper_anchors( $html );
			$html = self::strip_empty_plugin_wrapper_hosts( $html );
			$html = trim( $html );
			if ( '' === $html ) {
				return '';
			}

			$trimmed = trim( $html );

			if ( self::is_single_root_plugin_wrapper( $trimmed ) ) {
				$tag_start = strpos( $trimmed, '<a' );
				if ( false !== $tag_start ) {
					$balanced = self::extract_balanced_anchor_at( $trimmed, $tag_start );
					if ( null !== $balanced ) {
						$html = trim( $balanced['inner'] );
						continue;
					}
				}
			}

			if ( self::is_single_root_plugin_host( $trimmed ) ) {
				$tag_start = stripos( $trimmed, '<div' );
				if ( false !== $tag_start ) {
					$balanced = self::extract_balanced_div_at( $trimmed, $tag_start );
					if ( null !== $balanced ) {
						$html = trim( $balanced['inner'] );
						continue;
					}
				}
			}

			break;
		}

		return trim( $html );
	}

	/**
	 * Remove empty Smart Link anchor shells.
	 *
	 * @param string $html Rendered HTML.
	 * @return string
	 */
	private static function strip_empty_plugin_wrapper_anchors( string $html ): string {
		$limit = 30;

		while (
			$limit-- > 0
			&& preg_match(
				'/<a\s[^>]*\b(?:forwp|4wp|f4wp)-smart-link-wrapper\b[^>]*>\s*<\/a>/is',
				$html
			)
		) {
			$html = (string) preg_replace(
				'/<a\s[^>]*\b(?:forwp|4wp|f4wp)-smart-link-wrapper\b[^>]*>\s*<\/a>/is',
				'',
				$html,
				1
			);
		}

		return $html;
	}

	/**
	 * Remove empty Smart Link host shells.
	 *
	 * @param string $html Rendered HTML.
	 * @return string
	 */
	private static function strip_empty_plugin_wrapper_hosts( string $html ): string {
		$limit = 30;

		while (
			$limit-- > 0
			&& preg_match(
				'/<div\s[^>]*\bforwp-smart-link-host\b[^>]*>\s*<\/div>/is',
				$html
			)
		) {
			$html = (string) preg_replace(
				'/<div\s[^>]*\bforwp-smart-link-host\b[^>]*>\s*<\/div>/is',
				'',
				$html,
				1
			);
		}

		return $html;
	}

	/**
	 * Whether the fragment is exactly one Smart Link wrapper around inner markup.
	 *
	 * @param string $html HTML fragment.
	 * @return bool
	 */
	private static function is_single_root_plugin_wrapper( string $html ): bool {
		$trimmed = trim( $html );

		if ( ! preg_match( '/^<a\b/i', $trimmed ) ) {
			return false;
		}

		$tag_start = strpos( $trimmed, '<a' );
		if ( false === $tag_start ) {
			return false;
		}

		$closing_bracket = strpos( $trimmed, '>', $tag_start );
		if ( false === $closing_bracket ) {
			return false;
		}

		$opening = substr( $trimmed, $tag_start, $closing_bracket - $tag_start + 1 );
		if ( ! self::opening_tag_is_smart_link_wrapper( $opening ) ) {
			return false;
		}

		$balanced = self::extract_balanced_anchor_at( $trimmed, $tag_start );

		return null !== $balanced
			&& '' !== trim( $balanced['inner'] )
			&& $balanced['outer_len'] === strlen( $trimmed );
	}

	/**
	 * Whether the fragment is exactly one Smart Link host around inner markup.
	 *
	 * @param string $html HTML fragment.
	 * @return bool
	 */
	private static function is_single_root_plugin_host( string $html ): bool {
		$trimmed = trim( $html );

		if ( ! preg_match( '/^<div\b/i', $trimmed ) ) {
			return false;
		}

		$tag_start = stripos( $trimmed, '<div' );
		if ( false === $tag_start ) {
			return false;
		}

		$closing_bracket = strpos( $trimmed, '>', $tag_start );
		if ( false === $closing_bracket ) {
			return false;
		}

		$opening = substr( $trimmed, $tag_start, $closing_bracket - $tag_start + 1 );
		if ( ! self::opening_tag_is_smart_link_host( $opening ) ) {
			return false;
		}

		$balanced = self::extract_balanced_div_at( $trimmed, $tag_start );

		return null !== $balanced
			&& '' !== trim( $balanced['inner'] )
			&& $balanced['outer_len'] === strlen( $trimmed );
	}

	/**
	 * @param string $opening_tag_fragment Opening `<a ...>` tag including `>`.
	 * @return bool
	 */
	private static function opening_tag_is_smart_link_wrapper( string $opening_tag_fragment ): bool {
		$frag = strtolower( $opening_tag_fragment );

		return false !== strpos( $frag, 'forwp-smart-link-wrapper' )
			|| preg_match( '/\b(?:4wp|forwp|f4wp)-smart-link-wrapper\b/', $frag )
			|| preg_match( '/\bdata-forwp-smart-link\s*=/', $frag );
	}

	/**
	 * @param string $opening_tag_fragment Opening `<div ...>` tag including `>`.
	 * @return bool
	 */
	private static function opening_tag_is_smart_link_host( string $opening_tag_fragment ): bool {
		$frag = strtolower( $opening_tag_fragment );

		// Host attrs on core block roots (e.g. .wp-block-column) must not be stripped as a "shell".
		if ( preg_match( '/\bwp-block-(?:column|group|cover)\b/', $frag ) ) {
			return false;
		}

		return false !== strpos( $frag, 'forwp-smart-link-host' )
			|| preg_match( '/\bdata-forwp-smart-link-url\s*=/', $frag );
	}

	/**
	 * Skip re-processing when render_block runs more than once on the same output.
	 *
	 * @param string $html     Rendered HTML.
	 * @param string $modifier Block modifier slug.
	 * @return bool
	 */
	private static function markup_already_has_smart_link( string $html, string $modifier ): bool {
		$trimmed = trim( $html );

		if ( '' === $trimmed || ! preg_match( '/^<\w+/', $trimmed ) ) {
			return false;
		}

		$opening_end = strpos( $trimmed, '>' );

		if ( false === $opening_end ) {
			return false;
		}

		$opening         = substr( $trimmed, 0, $opening_end + 1 );
		$modifier_quoted = preg_quote( $modifier, '/' );

		return (bool) preg_match(
			'/\bforwp-smart-link-(?:wrapper|host)--' . $modifier_quoted . '\b/i',
			$opening
		) || (bool) preg_match(
			'/\bdata-forwp-smart-link-url\s*=/i',
			$opening
		);
	}

	/**
	 * Given `<a` at byte offset `$tag_start`, return inner markup and full outer length (balanced `</a>`).
	 *
	 * @param string $html      Full fragment.
	 * @param int    $tag_start Position of `<a`.
	 * @return array{inner:string, outer_len:int}|null
	 */
	private static function extract_balanced_anchor_at( string $html, int $tag_start ): ?array {
		$closing_bracket = strpos( $html, '>', $tag_start );

		if ( false === $closing_bracket ) {
			return null;
		}

		$content_start = $closing_bracket + 1;
		$pos           = $content_start;
		$depth         = 1;
		$len           = strlen( $html );

		while ( $pos < $len ) {
			$open_pos = PHP_INT_MAX;
			if ( preg_match( '/<a\b/i', $html, $mo, PREG_OFFSET_CAPTURE, $pos ) ) {
				$open_pos = (int) $mo[0][1];
			}

			if ( ! preg_match( '/<\/a>/i', $html, $mc, PREG_OFFSET_CAPTURE, $pos ) ) {
				return null;
			}

			$close_pos = (int) $mc[0][1];
			$close_len = strlen( $mc[0][0] );

			if ( $open_pos < $close_pos ) {
				++$depth;
				$inner_gt = strpos( $html, '>', $open_pos );
				if ( false === $inner_gt ) {
					return null;
				}
				$pos = $inner_gt + 1;
				continue;
			}

			--$depth;

			if ( 0 === $depth ) {
				$close_end = $close_pos + $close_len;

				return array(
					'inner'     => substr( $html, $content_start, $close_pos - $content_start ),
					'outer_len' => $close_end - $tag_start,
				);
			}

			$pos = $close_pos + $close_len;
		}

		return null;
	}

	/**
	 * Given `<div` at byte offset `$tag_start`, return inner markup and full outer length (balanced `</div>`).
	 *
	 * @param string $html      Full fragment.
	 * @param int    $tag_start Position of `<div`.
	 * @return array{inner:string, outer_len:int}|null
	 */
	private static function extract_balanced_div_at( string $html, int $tag_start ): ?array {
		$closing_bracket = strpos( $html, '>', $tag_start );

		if ( false === $closing_bracket ) {
			return null;
		}

		$content_start = $closing_bracket + 1;
		$pos           = $content_start;
		$depth         = 1;
		$len           = strlen( $html );

		while ( $pos < $len ) {
			$open_pos = PHP_INT_MAX;
			if ( preg_match( '/<div\b/i', $html, $mo, PREG_OFFSET_CAPTURE, $pos ) ) {
				$open_pos = (int) $mo[0][1];
			}

			if ( ! preg_match( '/<\/div>/i', $html, $mc, PREG_OFFSET_CAPTURE, $pos ) ) {
				return null;
			}

			$close_pos = (int) $mc[0][1];
			$close_len = strlen( $mc[0][0] );

			if ( $open_pos < $close_pos ) {
				++$depth;
				$inner_gt = strpos( $html, '>', $open_pos );
				if ( false === $inner_gt ) {
					return null;
				}
				$pos = $inner_gt + 1;
				continue;
			}

			--$depth;

			if ( 0 === $depth ) {
				$close_end = $close_pos + $close_len;

				return array(
					'inner'     => substr( $html, $content_start, $close_pos - $content_start ),
					'outer_len' => $close_end - $tag_start,
				);
			}

			$pos = $close_pos + $close_len;
		}

		return null;
	}
}
