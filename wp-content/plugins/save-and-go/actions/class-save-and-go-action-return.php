<?php
/**
 * 'Save and Return' action.
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

if ( ! class_exists( 'Save_And_Go_Action_Return' ) ) {

	/**
	 * 'Save and Return' action: after saving the post,
	 * returns to the referer page, no matter which page it was.
	 */
	class Save_And_Go_Action_Return extends Save_And_Go_Action {

		/**
		 * Name of the cookie that will contain the URL of the
		 * referer page.
		 */
		const COOKIE_REFERER_URL = 'sng_return_referer';

		/**
		 * Constructor, adds a WordPress hook to the 'current_screen' action.
		 */
		public function __construct() {
			parent::__construct();
			add_action( 'current_screen', array( $this, 'save_referer' ) );
		}

		/**
		 * If we are in a post edit page (so this action could be
		 * called), we save the referer in a cookie. If this
		 * action is used, we will redirect to this saved URL.
		 *
		 * @param WP_Screen $wp_screen WP_Screen returned by the current_screen action.
		 * @return void
		 */
		public function save_referer( $wp_screen ) {
			if ( 'post' !== $wp_screen->base || headers_sent() ) {
				return;
			}

			// Only execute this function in GET.
			if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
				return;
			}

			$referer_url = '';

			if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
				$referer_url = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			}

			// Only keep referers that live inside this site's admin.
			if ( $referer_url && 0 !== strpos( $referer_url, admin_url() ) ) {
				$referer_url = '';
			}

			/*
			 * If the referer is the same as this post edit screen,
			 * we don't save its URL. This allows using the regular
			 * "Update" button at least once without losing where we
			 * were before editing.
			 */
			$is_same_url = false;

			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only: this only compares the current URL with the referer to decide whether to remember it.
			if ( isset( $_GET['post'] ) ) {
				$is_same_url = Save_And_Go_Utils::url_is_post_edit( $referer_url, absint( wp_unslash( $_GET['post'] ) ) );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			if ( ! $is_same_url ) {
				setcookie( self::COOKIE_REFERER_URL, $referer_url, 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
			}
		}

		/**
		 * The action name.
		 *
		 * @return string
		 */
		public function get_name() {
			return _x( 'Save and Return', 'Action name (used in settings page)', 'save-and-go' );
		}

		/**
		 * The action id.
		 *
		 * @return string
		 */
		public function get_id() {
			return 'saveandgo.return';
		}

		/**
		 * The action description.
		 *
		 * @return string
		 */
		public function get_description() {
			return _x( 'Returns to the <strong>previous page</strong> (no matter which page) after save.', 'Action description (used in settings page)', 'save-and-go' );
		}

		/**
		 * The button label pattern.
		 *
		 * @param WP_Post $post The post being edited.
		 * @return string
		 */
		public function get_button_label_pattern( $post ) {
			/* translators: %s: "Publish" or "Update" (the label of the original save button). */
			return _x( '%s and Return', 'Button label (used in post edit page). %s = "Publish" or "Update"', 'save-and-go' );
		}

		/**
		 * Returns the URL of the page we were on before editing
		 * the post. If, for any reason, we cannot determine
		 * the referer page, returns the $current_url.
		 *
		 * @param string  $current_url Current redirect URL.
		 * @param WP_Post $post        The saved post.
		 * @return string
		 */
		public function get_redirect_url( $current_url, $post ) {
			$url = $current_url;

			if ( ! isset( $_COOKIE[ self::COOKIE_REFERER_URL ] ) ) {
				return $url;
			}

			$cookie_url = esc_url_raw( trim( sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_REFERER_URL ] ) ) ) );

			if ( empty( $cookie_url ) ) {
				return $url;
			}

			// Only redirect inside this site's admin.
			if ( 0 !== strpos( $cookie_url, admin_url() ) ) {
				return $url;
			}

			$url_parts = Save_And_Go_Utils::parse_url( $current_url );
			$url       = $cookie_url;

			// If the URL is a post edit page, we add the
			// parameters to show the "post updated" message.
			if ( Save_And_Go_Utils::url_is_post_edit( $url ) ) {
				$url = add_query_arg(
					array(
						Save_And_Go_Messages::HTTP_PARAM_UPDATED_POST_ID => $post->ID,
						'message' => isset( $url_parts['query']['message'] ) ? $url_parts['query']['message'] : '',
					),
					$url
				);
			}

			return $url;
		}
	}
}
