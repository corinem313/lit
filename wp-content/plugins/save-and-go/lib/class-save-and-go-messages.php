<?php
/**
 * Admin messages after a redirect.
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

if ( ! class_exists( 'Save_And_Go_Messages' ) ) {

	/**
	 * Manages message display in the administration header after a redirect.
	 */
	class Save_And_Go_Messages {

		/**
		 * URL parameter defining the id of the post that was being modified
		 * before the redirect.
		 */
		const HTTP_PARAM_UPDATED_POST_ID = 'sng-updated-post-id';

		/**
		 * Main entry point. Sets up all the WordPress hooks.
		 *
		 * @return void
		 */
		public static function setup() {
			add_filter( 'post_updated_messages', array( __CLASS__, 'post_updated_messages' ), 99 );
			add_filter( 'removable_query_args', array( __CLASS__, 'removable_query_args' ), 99 );
		}

		/**
		 * Adds the URL param containing the last modified post id to
		 * the list of URL params that are removed after being used once.
		 *
		 * @param array $removable_query_args An array of parameters to remove from the URL.
		 * @return array The array with the added param.
		 */
		public static function removable_query_args( $removable_query_args ) {
			$removable_query_args[] = self::HTTP_PARAM_UPDATED_POST_ID;
			return $removable_query_args;
		}

		/**
		 * If the plugin did a redirect, we update the success messages.
		 * The regular messages always contain links and dates of the
		 * currently shown post but, if we did a redirect, we want to
		 * change them to reflect the post where we were.
		 *
		 * @see wp-admin/edit-form-advanced.php ($messages variable)
		 * @param array $messages Associative array of messages per post type.
		 * @return array The modified messages array.
		 */
		public static function post_updated_messages( $messages ) {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only display tweak; the param is set by a redirect we created and only changes which post a success message links to.

			// Only modify the messages if this plugin did a redirect.
			if ( ! isset( $_REQUEST[ self::HTTP_PARAM_UPDATED_POST_ID ] ) ) {
				return $messages;
			}

			$current_post = get_post();

			if ( ! $current_post ) {
				return $messages;
			}

			$post_id          = $current_post->ID;
			$previous_post_id = absint( wp_unslash( $_REQUEST[ self::HTTP_PARAM_UPDATED_POST_ID ] ) );

			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			// Check that the previous post exists and can be read by this user.
			if ( ! $previous_post_id || false === get_post_status( $previous_post_id ) ) {
				return $messages;
			}

			if ( ! current_user_can( 'edit_post', $previous_post_id ) ) {
				return $messages;
			}

			$current_permalink_url  = get_permalink( $post_id );
			$current_preview_url    = get_preview_post_link( $post_id );
			$previous_permalink_url = get_permalink( $previous_post_id );
			$previous_preview_url   = get_preview_post_link( $previous_post_id );

			foreach ( $messages as $post_type => $post_messages ) {
				foreach ( $post_messages as $code => $message ) {
					$message_changed = false;
					$new_message     = '';

					// We replace URLs (normal and escaped versions).
					switch ( $code ) {
						case 1:
						case 6:
						case 9:
							$new_message     = str_replace( $current_permalink_url, $previous_permalink_url, $message );
							$new_message     = str_replace( esc_url( $current_permalink_url ), esc_url( $previous_permalink_url ), $new_message );
							$message_changed = true;
							break;

						case 8:
						case 10:
							$new_message     = str_replace( $current_preview_url, $previous_preview_url, $message );
							$new_message     = str_replace( esc_url( $current_preview_url ), esc_url( $previous_preview_url ), $new_message );
							$message_changed = true;
							break;
					}

					// We update the published date.
					if ( 9 === $code ) {
						$date_format   = _x( 'M j, Y @ H:i', 'Date format used to find and replace the date in success messages for scheduled posts. Important: translate with *exactly* the official WordPress translation for this string.', 'save-and-go' );
						$previous_post = get_post( $previous_post_id );
						$current_date  = date_i18n( $date_format, strtotime( $current_post->post_date ) );
						$previous_date = date_i18n( $date_format, strtotime( $previous_post->post_date ) );

						$new_message     = str_replace( $current_date, $previous_date, $message );
						$message_changed = true;
					}

					if ( $message_changed ) {
						$messages[ $post_type ][ $code ] = $new_message;
					}
				}
			}

			return $messages;
		}
	}
}
