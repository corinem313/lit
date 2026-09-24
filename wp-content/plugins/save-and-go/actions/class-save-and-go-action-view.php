<?php
/**
 * 'Save and View' action.
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

if ( ! class_exists( 'Save_And_Go_Action_View' ) ) {

	/**
	 * 'Save and View' action: after saving the post, redirects to the
	 * post's page on the frontend.
	 */
	class Save_And_Go_Action_View extends Save_And_Go_Action {

		/**
		 * The action name.
		 *
		 * @return string
		 */
		public function get_name() {
			return _x( 'Save and View', 'Action name (used in settings page)', 'save-and-go' );
		}

		/**
		 * The action id.
		 *
		 * @return string
		 */
		public function get_id() {
			return 'saveandgo.view';
		}

		/**
		 * The action description.
		 *
		 * @return string
		 */
		public function get_description() {
			return _x( 'Shows the <strong>post itself</strong> after save. The same window is used.', 'Action description (used in settings page)', 'save-and-go' );
		}

		/**
		 * The button label pattern.
		 *
		 * @param WP_Post $post The post being edited.
		 * @return string
		 */
		public function get_button_label_pattern( $post ) {
			/* translators: %s: "Publish" or "Update" (the label of the original save button). */
			return _x( '%s and View', 'Button label (used in post edit page). %s = "Publish" or "Update"', 'save-and-go' );
		}

		/**
		 * Returns a title attribute that informs the user the post
		 * will open in the same window.
		 *
		 * @param WP_Post $post The post being edited.
		 * @return string
		 */
		public function get_button_title( $post ) {
			return _x( 'The post will be shown in this window.', 'Button title attribute (used in post edit page)', 'save-and-go' );
		}

		/**
		 * Returns the URL of the post's page on the frontend.
		 *
		 * @param string  $current_url Current redirect URL.
		 * @param WP_Post $post        The saved post.
		 * @return string
		 */
		public function get_redirect_url( $current_url, $post ) {
			return get_permalink( $post->ID );
		}
	}
}
