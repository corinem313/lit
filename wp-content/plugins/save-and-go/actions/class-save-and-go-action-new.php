<?php
/**
 * 'Save and New' action.
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

if ( ! class_exists( 'Save_And_Go_Action_New' ) ) {

	/**
	 * 'Save and New' action: after saving the post, redirects to the
	 * new post screen.
	 */
	class Save_And_Go_Action_New extends Save_And_Go_Action {

		/**
		 * The action name.
		 *
		 * @return string
		 */
		public function get_name() {
			return _x( 'Save and New', 'Action name (used in settings page)', 'save-and-go' );
		}

		/**
		 * The action id.
		 *
		 * @return string
		 */
		public function get_id() {
			return 'saveandgo.new';
		}

		/**
		 * The action description.
		 *
		 * @return string
		 */
		public function get_description() {
			return _x( 'Shows the <strong>new post</strong> form after save.', 'Action description (used in settings page)', 'save-and-go' );
		}

		/**
		 * The button label pattern.
		 *
		 * @param WP_Post $post The post being edited.
		 * @return string
		 */
		public function get_button_label_pattern( $post ) {
			/* translators: %s: "Publish" or "Update" (the label of the original save button). */
			return _x( '%s and New', 'Button label (used in post edit page). %s = "Publish" or "Update"', 'save-and-go' );
		}

		/**
		 * Returns the URL of the New Post screen for this post type.
		 *
		 * @param string  $current_url Current redirect URL.
		 * @param WP_Post $post        The saved post.
		 * @return string
		 */
		public function get_redirect_url( $current_url, $post ) {
			$post_type = get_post_type( $post );
			$url_parts = Save_And_Go_Utils::parse_url( $current_url );
			$params    = $url_parts['query'];

			// We delete unwanted query params.
			unset( $params['post'] );
			unset( $params['action'] );

			// Query params to add.
			if ( $post_type && 'post' !== $post_type ) {
				$params['post_type'] = $post_type;
			}

			$params[ Save_And_Go_Messages::HTTP_PARAM_UPDATED_POST_ID ] = $post->ID;

			return Save_And_Go_Utils::admin_url( 'post-new.php', $params );
		}
	}
}
