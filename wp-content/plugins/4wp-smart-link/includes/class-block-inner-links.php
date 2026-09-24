<?php
/**
 * Detect native inner links inside Smart Link containers.
 *
 * @package ForWP\SmartLink
 */

namespace ForWP\SmartLink;

defined( 'ABSPATH' ) || exit;

/**
 * Mirrors editor heuristics so front-end render mode matches block structure.
 */
final class Block_Inner_Links {

	/**
	 * Whether the block tree or rendered HTML includes native links / link-like controls.
	 *
	 * @param array  $block         Parsed block.
	 * @param string $block_content Rendered HTML (optional fallback).
	 * @return bool
	 */
	public static function has_native_links( array $block, string $block_content = '' ): bool {
		$from_tree = self::block_tree_has_native_links( $block );

		/**
		 * Filter whether inner native links were detected before choosing host vs anchor mode.
		 *
		 * @param bool   $from_tree     Result from block tree walk.
		 * @param array  $block         Parsed block.
		 * @param string $block_content Rendered HTML.
		 */
		$from_tree = (bool) apply_filters( 'forwp_smart_link_has_inner_links', $from_tree, $block, $block_content );

		if ( $from_tree ) {
			return true;
		}

		return self::html_has_native_anchors( $block_content );
	}

	/**
	 * Walk inner blocks for known link-capable block types.
	 *
	 * @param array $block Parsed block.
	 * @return bool
	 */
	public static function block_tree_has_native_links( array $block ): bool {
		if ( self::single_block_has_native_link( $block ) ) {
			return true;
		}

		if ( empty( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
			return false;
		}

		foreach ( $block['innerBlocks'] as $inner ) {
			if ( is_array( $inner ) && self::block_tree_has_native_links( $inner ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array $block Parsed block.
	 * @return bool
	 */
	private static function single_block_has_native_link( array $block ): bool {
		$name  = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

		if ( 'core/button' === $name && ! empty( $attrs['url'] ) ) {
			return true;
		}

		if ( 'core/navigation-link' === $name && ! empty( $attrs['url'] ) ) {
			return true;
		}

		if (
			'core/image' === $name
			&& ! empty( $attrs['linkDestination'] )
			&& 'none' !== $attrs['linkDestination']
		) {
			return true;
		}

		if ( 'core/read-more' === $name ) {
			return true;
		}

		if ( 'core/post-terms' === $name ) {
			return true;
		}

		if ( 'core/post-title' === $name ) {
			if ( ! isset( $attrs['isLink'] ) || $attrs['isLink'] ) {
				return true;
			}
		}

		if (
			in_array( $name, array( 'core/heading', 'core/paragraph', 'core/list-item' ), true )
		) {
			$content = isset( $attrs['content'] ) ? (string) $attrs['content'] : '';
			if ( '' !== $content && preg_match( '/<a\b/i', $content ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Fallback: rendered markup already contains anchor tags.
	 *
	 * @param string $html Rendered HTML.
	 * @return bool
	 */
	public static function html_has_native_anchors( string $html ): bool {
		$html = trim( $html );

		return '' !== $html && (bool) preg_match( '/<a\s/i', $html );
	}
}
