<?php
/**
 * Utility functions used by the plugin.
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

if ( ! class_exists( 'Save_And_Go_Utils' ) ) {

	/**
	 * Utilities functions used by the plugin.
	 */
	class Save_And_Go_Utils {

		/**
		 * Internal cache variable to hold the adjacent posts.
		 * The keys are in the format [post-id]-[next|previous],
		 * the values are the post objects.
		 *
		 * @var array
		 */
		protected static $adjacent_post_cache = array();

		/**
		 * Returns the full URL to a file in this plugin's folder.
		 *
		 * @param string $file The file path relative to the plugin's folder.
		 * @return string The full URL to the file.
		 */
		public static function plugins_url( $file ) {
			return plugins_url( $file, __DIR__ );
		}

		/**
		 * Returns the cache-busting version string for one of the
		 * plugin's CSS/JS files: the file's last-modified time. Unlike
		 * the plugin version, it changes on every deployed build, so
		 * browsers never keep serving a stale cached copy of an asset.
		 * Falls back to the plugin version if the file cannot be read.
		 *
		 * @param string $file The file path relative to the plugin's folder.
		 * @return string|int
		 */
		public static function asset_version( $file ) {
			$path  = plugin_dir_path( Save_And_Go::get_main_file_path() ) . $file;
			$mtime = file_exists( $path ) ? filemtime( $path ) : false;

			return $mtime ? $mtime : SAVE_AND_GO_VERSION;
		}

		/**
		 * Returns this plugin's basename.
		 *
		 * @return string
		 */
		public static function plugin_main_file_basename() {
			return plugin_basename( Save_And_Go::get_main_file_path() );
		}

		/**
		 * Takes a WP_Post instance and returns the next or previous
		 * (depending on $dir value) post the current user can edit,
		 * ordered by publication date.
		 *
		 * WordPress already has a get_adjacent_post() function, but it
		 * checks only posts with 'published' status. We need to check any
		 * post that would be shown on an administration post list page
		 * ('published', 'draft', 'future', ...).
		 *
		 * @param WP_Post $post The post.
		 * @param string  $dir  'next' or 'previous'. Specifies which post to return.
		 * @return WP_Post|null The adjacent post or null if no post is found.
		 */
		public static function get_adjacent_post( $post, $dir = 'next' ) {
			$cache_id = $post->ID . '-' . $dir;

			if ( ! array_key_exists( $cache_id, self::$adjacent_post_cache ) ) {
				$is_next = ( 'next' === $dir );
				$order   = $is_next ? 'ASC' : 'DESC';

				// Any status shown on the "All" posts list in the admin
				// ('publish', 'draft', 'future', 'pending', 'private', ...).
				$statuses = array_values( get_post_stati( array( 'show_in_admin_all_list' => true ) ) );

				$base_args = array(
					'post_type'           => $post->post_type,
					'post_status'         => $statuses,
					'fields'              => 'ids',
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
				);

				// If the current user cannot edit others' posts, only
				// consider the user's own posts.
				$post_type_object = get_post_type_object( get_post_type( $post ) );

				if ( $post_type_object && ! current_user_can( $post_type_object->cap->edit_others_posts ) ) {
					$base_args['author'] = get_current_user_id();
				}

				$found_post_id = null;

				/*
				 * Step 1 — tie-break: posts sharing the exact same
				 * post_date (down to the second, which happens with batch
				 * imports) are ordered by ID. Look for a same-date post
				 * with a greater (next) or smaller (previous) ID.
				 */
				$date_parts = date_parse( $post->post_date );

				if ( ! empty( $date_parts['year'] ) ) {
					$same_date_args = array_merge(
						$base_args,
						array(
							// Bounded: more than 100 posts sharing the exact
							// same timestamp (to the second) is pathological.
							'posts_per_page' => 100,
							'orderby'        => 'ID',
							'order'          => $order,
							'date_query'     => array(
								array(
									'year'     => $date_parts['year'],
									'monthnum' => $date_parts['month'],
									'day'      => $date_parts['day'],
									'hour'     => $date_parts['hour'],
									'minute'   => $date_parts['minute'],
									'second'   => $date_parts['second'],
								),
							),
						)
					);

					foreach ( get_posts( $same_date_args ) as $found_id ) {
						if ( $is_next ? ( $found_id > $post->ID ) : ( $found_id < $post->ID ) ) {
							$found_post_id = $found_id;
							break;
						}
					}
				}

				/*
				 * Step 2 — no same-date candidate: take the first post
				 * strictly after (next) or before (previous) this post's
				 * publication date.
				 */
				if ( ! $found_post_id ) {
					$adjacent_args = array_merge(
						$base_args,
						array(
							'posts_per_page' => 1,
							'orderby'        => array(
								'date' => $order,
								'ID'   => $order,
							),
							'date_query'     => array(
								array(
									( $is_next ? 'after' : 'before' ) => $post->post_date,
									'inclusive' => false,
								),
							),
						)
					);

					$adjacent_ids  = get_posts( $adjacent_args );
					$found_post_id = ! empty( $adjacent_ids ) ? $adjacent_ids[0] : null;
				}

				self::$adjacent_post_cache[ $cache_id ] = $found_post_id ? get_post( $found_post_id ) : null;
			}

			return self::$adjacent_post_cache[ $cache_id ];
		}

		/**
		 * Returns true if the $url is the listing page of $post_type.
		 *
		 * @param string $url       The url to check.
		 * @param string $post_type The post type. Defaults to 'post'.
		 * @return boolean
		 */
		public static function url_is_posts_list( $url, $post_type = 'post' ) {
			$url_parts           = self::parse_url( $url );
			$url_params          = $url_parts['query'];
			$posts_list_url_base = admin_url( 'edit.php' );

			// If no post type is set in the URL, defaults to 'post'.
			$url_post_type = isset( $url_params['post_type'] ) ? $url_params['post_type'] : 'post';

			return (
				0 === strpos( $url, $posts_list_url_base )
				&&
				$url_post_type === $post_type
			);
		}

		/**
		 * Returns true if the URL is a post edit page. If the
		 * $post_id is supplied, checks if it is also the post
		 * edit page of the specified post.
		 *
		 * @param string   $url     The URL to check.
		 * @param int|null $post_id Optional post id.
		 * @return boolean
		 */
		public static function url_is_post_edit( $url, $post_id = null ) {
			$url_parts          = self::parse_url( $url );
			$url_params         = $url_parts['query'];
			$post_edit_url_base = admin_url( 'post.php' );

			if ( false !== strpos( $url, $post_edit_url_base ) ) {
				$action_is_good = isset( $url_params['action'] ) && 'edit' === $url_params['action'];
				$post_is_good   = is_null( $post_id ) || ( isset( $url_params['post'] ) && (int) $url_params['post'] === (int) $post_id );

				return $action_is_good && $post_is_good;
			}

			return false;
		}

		/**
		 * From a url string, returns the address parts
		 * and the query params as an associative array.
		 *
		 * @param string $url The URL to parse.
		 * @return array
		 */
		public static function parse_url( $url ) {
			$url_parts = wp_parse_url( $url );
			$query     = array();

			if ( isset( $url_parts['query'] ) ) {
				wp_parse_str( $url_parts['query'], $query );
			}

			$url_parts['query'] = $query;

			return $url_parts;
		}

		/**
		 * Returns an admin URL with the passed query parameters added.
		 *
		 * @param string $relative_url Relative admin URL.
		 * @param array  $params       Query parameters to add.
		 * @return string
		 */
		public static function admin_url( $relative_url, $params = array() ) {
			$url = admin_url( $relative_url );
			return add_query_arg( $params, $url );
		}
	}
}
