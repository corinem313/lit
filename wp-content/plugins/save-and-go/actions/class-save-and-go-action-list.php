<?php
/**
 * 'Save and List' action.
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

if ( ! class_exists( 'Save_And_Go_Action_List' ) ) {

	/**
	 * 'Save and List' action: after saving the post, redirects to the
	 * post listing page.
	 */
	class Save_And_Go_Action_List extends Save_And_Go_Action {

		/**
		 * Cookie name that contains the URL of the last 'post list'
		 * page that was visited.
		 */
		const COOKIE_LAST_EDIT_URL = 'sng_last_edit_url';

		/**
		 * Constructor, adds a WordPress hook to the 'current_screen' action.
		 */
		public function __construct() {
			parent::__construct();
			add_action( 'current_screen', array( $this, 'check_post_list_page' ) );
		}

		/**
		 * If we are on a post listing page, we save the current
		 * URL in a cookie, including all filtering and paginating
		 * parameters.
		 *
		 * When the user uses this action, we check if the last visited
		 * post listing page (in the cookie) is the listing page of this
		 * post type. If so, we redirect to this page.
		 *
		 * @param WP_Screen $wp_screen WP_Screen returned by the current_screen action.
		 * @return void
		 */
		public function check_post_list_page( $wp_screen ) {
			if ( 'edit' === $wp_screen->base && ! headers_sent() ) {
				$url = admin_url( 'edit.php' );

				if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
					$query_string = sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) );

					if ( '' !== $query_string ) {
						$url .= '?' . $query_string;
					}
				}

				setcookie( self::COOKIE_LAST_EDIT_URL, esc_url_raw( $url ), 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
			}
		}

		/**
		 * The action name.
		 *
		 * @return string
		 */
		public function get_name() {
			return _x( 'Save and List', 'Action name (used in settings page)', 'save-and-go' );
		}

		/**
		 * The action id.
		 *
		 * @return string
		 */
		public function get_id() {
			return 'saveandgo.list';
		}

		/**
		 * The action description.
		 *
		 * @return string
		 */
		public function get_description() {
			return _x( 'Shows the <strong>posts list</strong> after save.', 'Action description (used in settings page)', 'save-and-go' );
		}

		/**
		 * The button label pattern.
		 *
		 * @param WP_Post $post The post being edited.
		 * @return string
		 */
		public function get_button_label_pattern( $post ) {
			/* translators: %s: "Publish" or "Update" (the label of the original save button). */
			return _x( '%s and List', 'Button label (used in post edit page). %s = "Publish" or "Update"', 'save-and-go' );
		}

		/**
		 * Returns the post listing page URL for this post type.
		 * If the last post listing page visited was the one
		 * for this post type, we return that URL with the
		 * same filtering and paging parameters.
		 *
		 * @param string  $current_url Current redirect URL.
		 * @param WP_Post $post        The saved post.
		 * @return string
		 */
		public function get_redirect_url( $current_url, $post ) {
			$post_type = get_post_type( $post );

			/**
			 * URL parameters to add.
			 *
			 * @var array
			 */
			$params = array(
				'updated' => '1',
			);

			if ( $post_type && 'post' !== $post_type ) {
				$params['post_type'] = $post_type;
			}

			// Default return url: the edit screen of the post type.
			$redirect_url = Save_And_Go_Utils::admin_url( 'edit.php', $params );

			// If an edit url was set in the cookie, we retrieve it
			// and use it only if it is an edit page of the same
			// post type.
			if ( isset( $_COOKIE[ self::COOKIE_LAST_EDIT_URL ] ) ) {
				$cookie_url = esc_url_raw( trim( sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_LAST_EDIT_URL ] ) ) ) );

				if ( $cookie_url && Save_And_Go_Utils::url_is_posts_list( $cookie_url, $post_type ) ) {
					// We remove some unwanted params.
					$params_to_remove = array(
						'locked',
						'skipped',
						'updated',
						'deleted',
						'trashed',
						'untrashed',
						'ids',
					);

					$redirect_url = remove_query_arg( $params_to_remove, $cookie_url );

					// We set the new parameters.
					$redirect_url = add_query_arg( $params, $redirect_url );
				}
			}

			return $redirect_url;
		}
	}
}
