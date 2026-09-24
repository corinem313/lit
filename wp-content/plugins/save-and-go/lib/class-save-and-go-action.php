<?php
/**
 * Abstract base class for actions.
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

if ( ! class_exists( 'Save_And_Go_Action' ) ) {

	/**
	 * Abstract class that represents an action. All actions must
	 * extend this class.
	 */
	abstract class Save_And_Go_Action {

		/**
		 * Constructor, does nothing by default.
		 */
		public function __construct() {
		}

		/**
		 * Name of this action. Used in the settings page. This is
		 * not the button label (see get_button_label_pattern()).
		 *
		 * @return string
		 */
		abstract public function get_name();

		/**
		 * Unique id of this action. Id should start with a namespace,
		 * followed by a dot, followed by the action name. Action
		 * ids that start with an underscore are reserved.
		 * Ex: saveandgo.new
		 *
		 * @return string
		 */
		abstract public function get_id();

		/**
		 * Description of this action. Used in the settings page.
		 *
		 * @return string
		 */
		abstract public function get_description();

		/**
		 * Returns true if this action can be executed when
		 * in the edit page of the specified $post. Note that
		 * this is NOT whether the action was enabled in the
		 * settings page.
		 *
		 * @param WP_Post $post Post object currently being edited.
		 * @return boolean
		 */
		public function is_enabled( $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Available for subclasses.
			return true;
		}

		/**
		 * String (label) to show on the button or in the dropdown for
		 * this action. Since the label will probably be of type 'X and [action]'
		 * where X is the default WordPress action (ex: 'Save and New'),
		 * '%s' in the label will be replaced with the default action name.
		 * Ex: '%s and New'. Note that you can use HTML.
		 *
		 * @param WP_Post $post Post currently being edited.
		 * @return string
		 */
		abstract public function get_button_label_pattern( $post );

		/**
		 * If wanted, an HTML title attribute can be added to the
		 * dropdown element. Return non null value to use.
		 *
		 * @param WP_Post $post The post currently being edited.
		 * @return string|null
		 */
		public function get_button_title( $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Available for subclasses.
			return null;
		}

		/**
		 * How the block editor should treat this action after a save:
		 * 'redirect' (default) navigates the browser to the URL returned
		 * by get_redirect_url(); 'popup' opens the post's permalink in a
		 * new window and keeps the editor open.
		 *
		 * @return string 'redirect' or 'popup'.
		 */
		public function get_block_editor_behavior() {
			return 'redirect';
		}

		/**
		 * Returns the URL where to send the user when this action
		 * was used to save the post. This is the main function of an
		 * action. If this action does not redirect, return falsy value.
		 *
		 * @param string  $current_url Current redirect URL, the WordPress default.
		 * @param WP_Post $post        The post that was saved.
		 * @return string|WP_Error|null The URL where to send the user; falsy if no redirection.
		 */
		public function get_redirect_url( $current_url, $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Available for subclasses.
			return null;
		}
	}
}
