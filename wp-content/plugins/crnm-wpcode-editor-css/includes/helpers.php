<?php
/**
 * Helpers for reading WPCode CSS snippets marked for the editor.
 *
 * @package crnm-wpcode-editor-css
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Code types that can be loaded as editor CSS.
 *
 * @return string[]
 */
function crnm_wpec_supported_code_types() {
	return array( 'css', 'scss' );
}

/**
 * Whether a snippet is flagged to load in the block editor.
 *
 * @param int $snippet_id Snippet post ID.
 * @return bool
 */
function crnm_wpec_snippet_loads_in_editor( $snippet_id ) {
	return '1' === (string) get_post_meta( absint( $snippet_id ), CRNM_WPEC_META_KEY, true );
}

/**
 * Persist the editor-load flag on a snippet.
 *
 * @param int  $snippet_id Snippet post ID.
 * @param bool $enabled    Whether it should load in the editor.
 */
function crnm_wpec_set_snippet_loads_in_editor( $snippet_id, $enabled ) {
	$snippet_id = absint( $snippet_id );

	if ( ! $snippet_id ) {
		return;
	}

	if ( $enabled ) {
		update_post_meta( $snippet_id, CRNM_WPEC_META_KEY, '1' );
	} else {
		delete_post_meta( $snippet_id, CRNM_WPEC_META_KEY );
	}
}

/**
 * Query CSS/SCSS WPCode snippets.
 *
 * @param bool $editor_only When true, only snippets flagged for the editor.
 * @return WP_Post[]
 */
function crnm_wpec_get_css_snippets( $editor_only = false ) {
	$args = array(
		'post_type'              => 'wpcode',
		'post_status'            => array( 'publish', 'draft' ),
		'posts_per_page'         => 200,
		'orderby'                => 'title',
		'order'                  => 'ASC',
		'no_found_rows'          => true,
		'update_post_meta_cache' => true,
		'update_post_term_cache' => true,
		'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'wpcode_type',
				'field'    => 'slug',
				'terms'    => crnm_wpec_supported_code_types(),
			),
		),
	);

	if ( $editor_only ) {
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'   => CRNM_WPEC_META_KEY,
				'value' => '1',
			),
		);
	}

	$posts = get_posts( $args );

	usort(
		$posts,
		static function ( $a, $b ) {
			$priority_a = (int) get_post_meta( $a->ID, '_wpcode_priority', true );
			$priority_b = (int) get_post_meta( $b->ID, '_wpcode_priority', true );

			if ( $priority_a <= 0 ) {
				$priority_a = 10;
			}
			if ( $priority_b <= 0 ) {
				$priority_b = 10;
			}

			if ( $priority_a === $priority_b ) {
				return strcasecmp( $a->post_title, $b->post_title );
			}

			return $priority_a <=> $priority_b;
		}
	);

	return $posts;
}

/**
 * CSS string for a single snippet (compiled SCSS when available).
 *
 * @param WP_Post|int $snippet Snippet post or ID.
 * @return string
 */
function crnm_wpec_get_snippet_css( $snippet ) {
	$post = $snippet instanceof WP_Post ? $snippet : get_post( $snippet );

	if ( ! $post instanceof WP_Post || 'wpcode' !== $post->post_type ) {
		return '';
	}

	$code_type = '';

	if ( function_exists( 'wpcode_get_snippet' ) ) {
		$object    = wpcode_get_snippet( $post );
		$code_type = $object->get_code_type();

		if ( 'scss' === $code_type ) {
			$compiled = $object->get_compiled_code();
			if ( is_string( $compiled ) && '' !== trim( $compiled ) ) {
				return trim( $compiled );
			}
		}

		return trim( (string) $object->get_code() );
	}

	$terms = wp_get_post_terms( $post->ID, 'wpcode_type', array( 'fields' => 'slugs' ) );
	if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
		$code_type = (string) $terms[0];
	}

	if ( 'scss' === $code_type ) {
		$compiled = get_post_meta( $post->ID, '_wpcode_compiled_code', true );
		if ( is_string( $compiled ) && '' !== trim( $compiled ) ) {
			return trim( $compiled );
		}
	}

	return trim( (string) $post->post_content );
}

/**
 * Combined CSS for all active snippets flagged for the editor.
 *
 * @return string
 */
function crnm_wpec_get_combined_editor_css() {
	if ( isset( $_GET['wpcode-safe-mode'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return '';
	}

	if ( ! apply_filters( 'wpcode_do_auto_insert', true ) ) {
		return '';
	}

	$snippets = crnm_wpec_get_css_snippets( true );
	$parts    = array();

	foreach ( $snippets as $post ) {
		if ( 'publish' !== $post->post_status ) {
			continue;
		}

		$css = crnm_wpec_get_snippet_css( $post );
		if ( '' === $css ) {
			continue;
		}

		$title   = $post->post_title ? $post->post_title : __( 'Untitled Snippet', 'crnm-wpcode-editor-css' );
		$parts[] = '/* WPCode #' . absint( $post->ID ) . ': ' . str_replace( '*/', '* /', $title ) . " */\n" . $css;
	}

	$css = implode( "\n\n", $parts );

	/**
	 * Filter the combined CSS injected into the block editor.
	 *
	 * @param string $css Combined CSS.
	 */
	$css = apply_filters( 'crnm_wpcode_editor_css', $css );

	return is_string( $css ) ? wp_strip_all_tags( $css ) : '';
}

/**
 * Capability required to manage the editor-CSS flags.
 *
 * @return string
 */
function crnm_wpec_manage_capability() {
	return 'wpcode_edit_snippets';
}
