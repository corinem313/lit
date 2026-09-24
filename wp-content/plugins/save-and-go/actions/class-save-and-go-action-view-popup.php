<?php
/**
 * 'Save and View (new window)' action.
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

if ( ! class_exists( 'Save_And_Go_Action_View_Popup' ) ) {

	/**
	 * 'Save and View (new window)' action: after saving the post, the
	 * post editing page is shown again, and a new window is opened with
	 * the post's frontend page.
	 */
	class Save_And_Go_Action_View_Popup extends Save_And_Go_Action {

		/**
		 * HTTP param added to the URL when we re-show the (classic) post
		 * editing page after saving the post with this action. It triggers
		 * the JavaScript that reloads the popup containing the post's
		 * frontend page.
		 */
		const HTTP_PARAM_RELOAD_POPUP = 'sng-reload-popup';

		/**
		 * The icon shown next to the action name.
		 */
		const HTML_ICON = '<span class="dashicons dashicons-external" aria-hidden="true"></span>';

		/**
		 * Name of the JavaScript window used for the popup.
		 */
		const JS_WINDOW_NAME = 'save-and-go-post-preview';

		/**
		 * Constructor. Adds some hooks.
		 */
		public function __construct() {
			parent::__construct();
			add_filter( 'removable_query_args', array( __CLASS__, 'removable_query_args' ), 99 );
			add_filter( 'save_and_go_js_config', array( $this, 'add_js_config' ), 10, 2 );
		}

		/**
		 * Adds the URL param HTTP_PARAM_RELOAD_POPUP to the list of
		 * URL params that are removed after being used once.
		 *
		 * @param array $removable_query_args An array of parameters to remove from the URL.
		 * @return array The array with the added param.
		 */
		public static function removable_query_args( $removable_query_args ) {
			$removable_query_args[] = self::HTTP_PARAM_RELOAD_POPUP;
			return $removable_query_args;
		}

		/**
		 * Adds this action's data to the JavaScript configuration:
		 * the window name, the "please wait" message and, when the classic
		 * editor page reloads after this action was used, the permalink to
		 * load in the already-opened popup.
		 *
		 * @param array        $config The configuration.
		 * @param WP_Post|null $post   The post being edited.
		 * @return array
		 */
		public function add_js_config( $config, $post ) {
			$view_popup_config = array(
				'actionId'    => $this->get_id(),
				'windowName'  => self::JS_WINDOW_NAME,
				'waitMessage' => _x( 'Please wait while the post is being saved. This window will refresh automatically.', 'Message shown in the new window when "Save and View (new window)" is used.', 'save-and-go' ),
				'reloadPopup' => false,
				'permalink'   => '',
			);

			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only display flag set by our own redirect; it only reloads an already-open preview window.
			if ( isset( $_GET[ self::HTTP_PARAM_RELOAD_POPUP ] ) && '1' === sanitize_text_field( wp_unslash( $_GET[ self::HTTP_PARAM_RELOAD_POPUP ] ) ) && $post ) {
				$view_popup_config['reloadPopup'] = true;
				$view_popup_config['permalink']   = get_permalink( $post );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			$config['viewPopup'] = $view_popup_config;

			return $config;
		}

		/**
		 * The action name.
		 *
		 * @return string
		 */
		public function get_name() {
			return sprintf(
				/* translators: %s: new window icon. */
				_x( 'Save and View %s (new window)', 'Action name (used in settings page). %s = new window icon', 'save-and-go' ),
				self::HTML_ICON
			);
		}

		/**
		 * The action id.
		 *
		 * @return string
		 */
		public function get_id() {
			return 'saveandgo.viewPopup';
		}

		/**
		 * The action description.
		 *
		 * @return string
		 */
		public function get_description() {
			return _x( 'Shows the <strong>post itself in a new window</strong> after save.', 'Action description (used in settings page)', 'save-and-go' );
		}

		/**
		 * The button label pattern.
		 *
		 * @param WP_Post $post The post being edited.
		 * @return string
		 */
		public function get_button_label_pattern( $post ) {
			// The first %s must be escaped, because it is not replaced by this sprintf.
			return sprintf(
				/* translators: %%s = "Publish" or "Update"; %s = new window icon. */
				_x( '%%s and View %s', 'Button label (used in post edit page). %%s = "Publish" or "Update"; %s = new window icon', 'save-and-go' ),
				self::HTML_ICON
			);
		}

		/**
		 * Returns a title attribute that informs the user the post
		 * will open in a new window.
		 *
		 * @param WP_Post $post The post being edited.
		 * @return string
		 */
		public function get_button_title( $post ) {
			return _x( 'The post will be shown in a new window.', 'Button title attribute (used in post edit page)', 'save-and-go' );
		}

		/**
		 * In the block editor there is no page reload: the popup simply
		 * opens with the post's permalink after the save.
		 *
		 * @return string
		 */
		public function get_block_editor_behavior() {
			return 'popup';
		}

		/**
		 * Returns the current redirect url with the added parameter that
		 * triggers the JavaScript popup reload (classic editor only).
		 *
		 * @param string  $current_url Current redirect URL.
		 * @param WP_Post $post        The saved post.
		 * @return string
		 */
		public function get_redirect_url( $current_url, $post ) {
			return add_query_arg( self::HTTP_PARAM_RELOAD_POPUP, '1', $current_url );
		}
	}
}
