<?php
/**
 * 'Save and Previous' action.
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

if ( ! class_exists( 'Save_And_Go_Action_Previous' ) ) {

	/**
	 * 'Save and Previous' action: after saving the post, redirects to
	 * the edit screen of the previous post (same post type).
	 */
	class Save_And_Go_Action_Previous extends Save_And_Go_Action {

		/**
		 * The action name.
		 *
		 * @return string
		 */
		public function get_name() {
			return _x( 'Save and Previous', 'Action name (used in settings page)', 'save-and-go' );
		}

		/**
		 * The action id.
		 *
		 * @return string
		 */
		public function get_id() {
			return 'saveandgo.previous';
		}

		/**
		 * The action description.
		 *
		 * @return string
		 */
		public function get_description() {
			return _x( 'Shows the <strong>previous post</strong> edit form after save.', 'Action description (used in settings page)', 'save-and-go' );
		}

		/**
		 * The button label pattern.
		 *
		 * @param WP_Post $post The post being edited.
		 * @return string
		 */
		public function get_button_label_pattern( $post ) {
			/* translators: %s: "Publish" or "Update" (the label of the original save button). */
			return _x( '%s and Previous', 'Button label (used in post edit page). %s = "Publish" or "Update"', 'save-and-go' );
		}

		/**
		 * Returns true only if there is a previous post.
		 *
		 * @param WP_Post $post The post being edited.
		 * @return boolean
		 */
		public function is_enabled( $post ) {
			if ( ! $post ) {
				return false;
			}

			return (bool) Save_And_Go_Utils::get_adjacent_post( $post, 'previous' );
		}

		/**
		 * Returns the HTML title attribute for this action: the name
		 * of the previous post (if there is one), else a message
		 * indicating why the action is disabled.
		 *
		 * @param WP_Post $post The post being edited.
		 * @return string
		 */
		public function get_button_title( $post ) {
			if ( ! $this->is_enabled( $post ) ) {
				return _x( 'You are at the first post.', 'Button title attribute (used in post edit page)', 'save-and-go' );
			}

			$previous_post = Save_And_Go_Utils::get_adjacent_post( $post, 'previous' );

			return sprintf(
				/* translators: %s: other post name. */
				_x( 'The previous post is "%s".', 'Button title attribute (used in post edit page). %s = other post name', 'save-and-go' ),
				$previous_post->post_title
			);
		}

		/**
		 * Returns the URL of the previous post's edit screen. If there
		 * is not a previous post, returns null.
		 *
		 * @param string  $current_url Current redirect URL.
		 * @param WP_Post $post        The saved post.
		 * @return string|null
		 */
		public function get_redirect_url( $current_url, $post ) {
			$previous_post = Save_And_Go_Utils::get_adjacent_post( $post, 'previous' );

			// Should not happen, but just to be sure.
			if ( ! $previous_post ) {
				return null;
			}

			$url_parts = Save_And_Go_Utils::parse_url( $current_url );
			$params    = $url_parts['query'];

			// Query params to add.
			$params['post']   = $previous_post->ID;
			$params['action'] = 'edit';
			$params[ Save_And_Go_Messages::HTTP_PARAM_UPDATED_POST_ID ] = $post->ID;

			return Save_And_Go_Utils::admin_url( 'post.php', $params );
		}
	}
}
