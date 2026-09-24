<?php
/**
 * Redirection after a classic-editor post save.
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

if ( ! class_exists( 'Save_And_Go_Post_Save' ) ) {

	/**
	 * Manages the redirection after a post save (classic editor).
	 */
	class Save_And_Go_Post_Save {

		/**
		 * Main entry point. Sets up all the WordPress hooks.
		 *
		 * @return void
		 */
		public static function setup() {
			add_filter( 'redirect_post_location', array( __CLASS__, 'redirect_post_location' ), 10, 2 );
		}

		/**
		 * Changes the redirect URL after a post save/creation if applicable.
		 *
		 * If the redirect parameter set by this plugin is set, we determine
		 * the URL where to redirect (ex: to a new post, the next post, the
		 * posts list, ...). Called by the filter 'redirect_post_location'.
		 *
		 * Nonce verification is done by WordPress in wp-admin/post.php
		 * (check_admin_referer) before this filter ever runs.
		 *
		 * @param string $location Current new location defined by WordPress.
		 * @param int    $post_id  Id of the saved/created post.
		 * @return string The new (or unchanged) URL where to redirect.
		 */
		public static function redirect_post_location( $location, $post_id ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce already verified by wp-admin/post.php before this filter runs.

			/**
			 * Set in WordPress' wp-admin/post.php.
			 *
			 * @var string
			 */
			global $action;

			// Only enabled on save or publish actions.
			if ( ! isset( $_POST['save'] ) && ! isset( $_POST['publish'] ) ) {
				return $location;
			}

			if ( ! isset( $action ) || 'editpost' !== $action ) {
				return $location;
			}

			// The action parameter must be set.
			if ( ! isset( $_POST[ Save_And_Go_Post_Edit::HTTP_PARAM_ACTION ] ) ) {
				return $location;
			}

			$sat_action_id = sanitize_text_field( wp_unslash( $_POST[ Save_And_Go_Post_Edit::HTTP_PARAM_ACTION ] ) );

			// phpcs:enable WordPress.Security.NonceVerification.Missing

			$current_post = get_post( $post_id );

			if ( ! $current_post || ! current_user_can( 'edit_post', $post_id ) ) {
				return $location;
			}

			$sat_action = Save_And_Go_Actions::get_action( $sat_action_id );

			if ( is_null( $sat_action ) ) {
				return $location;
			}

			// We ask the action where to redirect the user.
			$new_location = $sat_action->get_redirect_url( $location, $current_post );

			// If an error was returned.
			if ( is_wp_error( $new_location ) ) {
				wp_die( esc_html( $new_location->get_error_message() ) );
			}

			if ( $new_location ) {
				return esc_url_raw( $new_location );
			}

			return $location;
		}
	}
}
