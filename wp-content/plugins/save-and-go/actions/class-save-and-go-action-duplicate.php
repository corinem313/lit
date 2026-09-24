<?php
/**
 * 'Save and Duplicate' action.
 *
 * @package Save_And_Go
 */

/**
 * Copyright 2026 Alisha Thomas (https://eightysevenweb.com)
 *
 * This file is part of the "Save and Go" WordPress plugin,
 * based on "Improved Save Button" by Label Blanc (GPLv3).
 *
 * Save and Go is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version. See <https://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Save_And_Go_Action_Duplicate' ) ) {

	/**
	 * 'Save and Duplicate' action: after saving the post,
	 * duplicates it (as a draft) and redirects to the new post's
	 * edit page.
	 */
	class Save_And_Go_Action_Duplicate extends Save_And_Go_Action {

		/**
		 * The action name.
		 *
		 * @return string
		 */
		public function get_name() {
			return _x( 'Save and Duplicate', 'Action name (used in settings page)', 'save-and-go' );
		}

		/**
		 * The action id.
		 *
		 * @return string
		 */
		public function get_id() {
			return 'saveandgo.duplicate';
		}

		/**
		 * The action description.
		 *
		 * @return string
		 */
		public function get_description() {
			return _x( '<strong>Duplicates the current post</strong> (as a draft) after save and shows the duplicated post\'s edit page.', 'Action description (used in settings page)', 'save-and-go' );
		}

		/**
		 * The button label pattern.
		 *
		 * @param WP_Post $post The post being edited.
		 * @return string
		 */
		public function get_button_label_pattern( $post ) {
			/* translators: %s: "Publish" or "Update" (the label of the original save button). */
			return _x( '%s and Duplicate', 'Button label (used in post edit page). %s = "Publish" or "Update"', 'save-and-go' );
		}

		/**
		 * Duplicates the current post (as a draft) and returns
		 * the URL of the new post's edit page.
		 *
		 * @param string  $current_url Current redirect URL.
		 * @param WP_Post $post        The saved post.
		 * @return string|WP_Error
		 */
		public function get_redirect_url( $current_url, $post ) {
			// The user must be allowed to create posts of this type.
			$post_type_object = get_post_type_object( $post->post_type );

			if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->create_posts ) ) {
				return new WP_Error(
					'save_and_go_cannot_duplicate',
					__( 'You are not allowed to create posts of this type.', 'save-and-go' )
				);
			}

			$new_post = self::copy_post( $post );

			if ( is_wp_error( $new_post ) ) {
				return $new_post;
			}

			self::copy_thumbnail( $post, $new_post );
			self::copy_taxonomies( $post, $new_post );
			self::copy_metas( $post, $new_post );

			$url_parts = Save_And_Go_Utils::parse_url( $current_url );
			$params    = $url_parts['query'];

			// Query params to add.
			$params['post']   = $new_post->ID;
			$params['action'] = 'edit';
			$params[ Save_And_Go_Messages::HTTP_PARAM_UPDATED_POST_ID ] = $post->ID;

			return Save_And_Go_Utils::admin_url( 'post.php', $params );
		}

		/**
		 * Inserts a new post with the same values as the passed
		 * one. On success, returns the new post. If an error
		 * occurred, a WP_Error is returned.
		 *
		 * @param WP_Post $post The post to copy.
		 * @return WP_Post|WP_Error
		 */
		protected static function copy_post( $post ) {
			$insert_post_args = array(
				'post_content'   => $post->post_content,
				'post_title'     => $post->post_title . _x( ' (copy)', "Text added to the duplicated post's title (notice the space at the beginning).", 'save-and-go' ),
				'post_excerpt'   => $post->post_excerpt,
				'post_status'    => 'draft',
				'post_type'      => $post->post_type,
				'comment_status' => $post->comment_status,
				'ping_status'    => $post->ping_status,
				'post_password'  => $post->post_password,
				'post_name'      => '', // Empty value allowed for draft posts.
				'post_parent'    => $post->post_parent,
				'menu_order'     => $post->menu_order,
				'to_ping'        => $post->to_ping,
			);

			$new_post_id = wp_insert_post( wp_slash( $insert_post_args ), true );

			if ( is_wp_error( $new_post_id ) ) {
				return $new_post_id;
			}

			return get_post( $new_post_id );
		}

		/**
		 * Sets the thumbnail of the second post to the same as
		 * the first post's.
		 *
		 * @param WP_Post $from_post Source post.
		 * @param WP_Post $to_post   Destination post.
		 * @return void
		 */
		protected static function copy_thumbnail( $from_post, $to_post ) {
			$post_thumbnail_id = get_post_thumbnail_id( $from_post->ID );

			if ( $post_thumbnail_id ) {
				set_post_thumbnail( $to_post->ID, $post_thumbnail_id );
			}
		}

		/**
		 * Copies all taxonomy terms from the first post to
		 * the second.
		 *
		 * @param WP_Post $from_post Source post.
		 * @param WP_Post $to_post   Destination post.
		 * @return void
		 */
		protected static function copy_taxonomies( $from_post, $to_post ) {
			$taxonomies = get_object_taxonomies( $from_post->post_type );

			foreach ( $taxonomies as $taxonomy ) {
				$post_terms = wp_get_object_terms( $from_post->ID, $taxonomy, array( 'fields' => 'slugs' ) );

				if ( ! is_wp_error( $post_terms ) ) {
					wp_set_object_terms( $to_post->ID, $post_terms, $taxonomy, false );
				}
			}
		}

		/**
		 * Copies all meta pairs from the first post to the second,
		 * using the WordPress meta API (no direct SQL).
		 *
		 * @param WP_Post $from_post Source post.
		 * @param WP_Post $to_post   Destination post.
		 * @return void
		 */
		protected static function copy_metas( $from_post, $to_post ) {
			$meta_keys = get_post_custom_keys( $from_post->ID );

			if ( empty( $meta_keys ) ) {
				return;
			}

			$excluded_keys = array( '_edit_lock', '_edit_last', '_wp_old_slug' );

			foreach ( $meta_keys as $meta_key ) {
				if ( in_array( $meta_key, $excluded_keys, true ) ) {
					continue;
				}

				if ( is_protected_meta( $meta_key, 'post' ) && ! current_user_can( 'edit_post_meta', $to_post->ID, $meta_key ) ) {
					continue;
				}

				$meta_values = get_post_meta( $from_post->ID, $meta_key, false );

				foreach ( $meta_values as $meta_value ) {
					add_post_meta( $to_post->ID, $meta_key, wp_slash( $meta_value ) );
				}
			}
		}
	}
}
