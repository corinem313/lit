<?php
/**
 * Bulk Add: quickly create many posts/pages/CPT entries at once.
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

if ( ! class_exists( 'Save_And_Go_Bulk_Add' ) ) {

	/**
	 * Renders the "Bulk Add" admin page content and handles its
	 * form submission: create many titled (optionally slugged) posts of
	 * any post type in one go, each with its own status.
	 */
	class Save_And_Go_Bulk_Add {

		/**
		 * The admin-post action name for the bulk add form.
		 */
		const FORM_ACTION = 'sng_bulk_add';

		/**
		 * The admin page slug of the Bulk Add page.
		 */
		const MENU_SLUG = 'save-and-go-bulk-add';

		/**
		 * URL params used to report results back to the tab.
		 */
		const PARAM_CREATED    = 'sng-bulk-created';
		const PARAM_SKIPPED    = 'sng-bulk-skipped';
		const PARAM_FAILED     = 'sng-bulk-failed';
		const PARAM_DOWNGRADED = 'sng-bulk-downgraded';
		const PARAM_ERROR      = 'sng-bulk-error';

		/**
		 * Main entry point. Sets up all the WordPress hooks.
		 *
		 * @return void
		 */
		public static function setup() {
			add_action( 'admin_post_' . self::FORM_ACTION, array( __CLASS__, 'handle_submit' ) );
			add_filter( 'removable_query_args', array( __CLASS__, 'removable_query_args' ), 99 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'add_list_screen_button' ) );
			// After the post type menus themselves are registered.
			add_action( 'admin_menu', array( __CLASS__, 'add_post_type_submenus' ), 15 );
			add_filter( 'parent_file', array( __CLASS__, 'highlight_post_type_menu' ) );
		}

		/**
		 * Adds a "Bulk Add" entry to the submenu of every post type
		 * Bulk Add supports (Posts, Pages and custom post types,
		 * automatically), right after "Add New". The entry links to
		 * the Bulk Add page with that post type pre-selected.
		 *
		 * @return void
		 */
		public static function add_post_type_submenus() {
			foreach ( self::get_available_post_types() as $name => $post_type_object ) {
				$parent = ( 'post' === $name ) ? 'edit.php' : 'edit.php?post_type=' . $name;

				add_submenu_page(
					$parent,
					sprintf(
						/* translators: %s: plugin name. */
						_x( '%s — Bulk Add', 'Bulk Add page <title>. %s = plugin name', 'save-and-go' ),
						Save_And_Go::get_localized_name()
					),
					_x( 'Bulk Add', 'Submenu label under each post type menu', 'save-and-go' ),
					$post_type_object->cap->create_posts,
					// A slug that is a URL renders as a plain link.
					'admin.php?page=' . self::MENU_SLUG . '&post_type=' . $name,
					'',
					2
				);
			}
		}

		/**
		 * When the Bulk Add page was opened from a post type's submenu
		 * (or list screen button), keeps that post type's menu open and
		 * highlighted, with the "Bulk Add" entry marked as current.
		 *
		 * @param string $parent_file The current parent menu file.
		 * @return string
		 */
		public static function highlight_post_type_menu( $parent_file ) {
			global $plugin_page, $submenu_file;

			if ( self::MENU_SLUG !== $plugin_page ) {
				return $parent_file;
			}

			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only: only decides which admin menu is highlighted.
			$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			if ( ! $post_type ) {
				return $parent_file;
			}

			$available = self::get_available_post_types();

			if ( ! isset( $available[ $post_type ] ) ) {
				return $parent_file;
			}

			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Setting $submenu_file is the documented way to mark a submenu entry as current.
			$submenu_file = 'admin.php?page=' . self::MENU_SLUG . '&post_type=' . $post_type;

			return ( 'post' === $post_type ) ? 'edit.php' : 'edit.php?post_type=' . $post_type;
		}

		/**
		 * Returns the URL of the Bulk Add page, optionally pre-selecting
		 * a post type.
		 *
		 * @param string $post_type Optional post type to pre-select.
		 * @return string
		 */
		public static function get_page_url( $post_type = '' ) {
			$url = admin_url( 'admin.php?page=' . self::MENU_SLUG );

			if ( $post_type ) {
				$url = add_query_arg( 'post_type', $post_type, $url );
			}

			return $url;
		}

		/**
		 * On post list screens (edit.php), adds a "Bulk Add" button next
		 * to the "Add New" button — for every post type Bulk Add supports,
		 * including custom post types, automatically. Clicking it opens
		 * the Bulk Add page with that post type pre-selected.
		 *
		 * @param string $hook_suffix The current admin page hook.
		 * @return void
		 */
		public static function add_list_screen_button( $hook_suffix ) {
			if ( 'edit.php' !== $hook_suffix ) {
				return;
			}

			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

			if ( ! $screen || empty( $screen->post_type ) ) {
				return;
			}

			$available = self::get_available_post_types();

			if ( ! isset( $available[ $screen->post_type ] ) ) {
				return;
			}

			wp_enqueue_script(
				'save-and-go-list-screen',
				Save_And_Go_Utils::plugins_url( 'js/list-screen.js' ),
				array(),
				Save_And_Go_Utils::asset_version( 'js/list-screen.js' ),
				true
			);

			wp_add_inline_script(
				'save-and-go-list-screen',
				'window.SaveAndGo = window.SaveAndGo || {}; window.SaveAndGo.listScreen = ' . wp_json_encode(
					array(
						'url'   => self::get_page_url( $screen->post_type ),
						'label' => _x( 'Bulk Add', 'Label of the button next to "Add New" on post list screens', 'save-and-go' ),
					)
				) . ';',
				'before'
			);
		}

		/**
		 * Registers this tab's result params as removable from the URL.
		 *
		 * @param array $removable_query_args An array of parameters to remove from the URL.
		 * @return array
		 */
		public static function removable_query_args( $removable_query_args ) {
			$removable_query_args[] = self::PARAM_CREATED;
			$removable_query_args[] = self::PARAM_SKIPPED;
			$removable_query_args[] = self::PARAM_FAILED;
			$removable_query_args[] = self::PARAM_DOWNGRADED;
			$removable_query_args[] = self::PARAM_ERROR;
			return $removable_query_args;
		}

		/**
		 * The statuses a row can be created with. Keys are the status
		 * names, values the labels shown in the dropdown.
		 *
		 * @return array
		 */
		public static function get_allowed_statuses() {
			return array(
				'publish' => _x( 'Published', 'Post status (used in the Bulk Add tab)', 'save-and-go' ),
				'draft'   => _x( 'Draft', 'Post status (used in the Bulk Add tab)', 'save-and-go' ),
				'pending' => _x( 'Pending review', 'Post status (used in the Bulk Add tab)', 'save-and-go' ),
				'private' => _x( 'Private', 'Post status (used in the Bulk Add tab)', 'save-and-go' ),
			);
		}

		/**
		 * Returns the post types the current user can bulk-create:
		 * every post type with an admin UI (posts, pages, public CPTs),
		 * minus internal ones, filtered by the user's create capability.
		 *
		 * @return array Array of WP_Post_Type objects keyed by name.
		 */
		public static function get_available_post_types() {
			$excluded = array(
				'attachment',
				'wp_block',
				'wp_navigation',
				'wp_template',
				'wp_template_part',
				'wp_font_family',
				'wp_font_face',
			);

			$post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
			$available  = array();

			foreach ( $post_types as $name => $post_type_object ) {
				if ( in_array( $name, $excluded, true ) ) {
					continue;
				}

				/*
				 * Only public content types. Post type builders (ACF,
				 * Meta Box, JetEngine, Elementor…) register their own
				 * internal configuration types — field groups, post
				 * type definitions, templates — that have an admin UI
				 * but are not content; those are all non-public.
				 */
				if ( empty( $post_type_object->public ) ) {
					continue;
				}

				if ( ! current_user_can( $post_type_object->cap->create_posts ) ) {
					continue;
				}

				$available[ $name ] = $post_type_object;
			}

			/**
			 * Filters the post types available in Bulk Add.
			 *
			 * Lets developers add back a non-public content type (unset
			 * types are removed everywhere: dropdown, menus, buttons and
			 * the submit validation all use this list).
			 *
			 * @param array $available Array of WP_Post_Type objects keyed by name.
			 */
			return apply_filters( 'save_and_go_bulk_add_post_types', $available );
		}

		/**
		 * Tries to identify which post type builder plugin registered
		 * the given post type, so the "not hierarchical" note can point
		 * the user to the right place to enable nesting.
		 *
		 * @param string $slug The post type slug.
		 * @return string Builder id ('acf', 'cptui', 'metabox',
		 *                'jetengine', 'pods', 'toolset') or '' if unknown.
		 */
		protected static function detect_post_type_builder( $slug ) {
			/*
			 * Definitive checks first: these builders expose which post
			 * types they registered.
			 */
			$cptui_types = get_option( 'cptui_post_types' );

			if ( is_array( $cptui_types ) && isset( $cptui_types[ $slug ] ) ) {
				return 'cptui';
			}

			if ( function_exists( 'acf_get_internal_post_type_posts' ) ) {
				foreach ( (array) acf_get_internal_post_type_posts( 'acf-post-type' ) as $acf_post_type ) {
					if ( is_array( $acf_post_type ) && isset( $acf_post_type['post_type'] ) && $acf_post_type['post_type'] === $slug ) {
						return 'acf';
					}
				}
			}

			/*
			 * Otherwise: when exactly one known builder is active, it is
			 * the most likely origin of the post type.
			 */
			$active = array();

			if ( class_exists( 'ACF' ) ) {
				$active[] = 'acf';
			}

			if ( defined( 'CPTUI_VERSION' ) || function_exists( 'cptui_get_post_type_slugs' ) ) {
				$active[] = 'cptui';
			}

			if ( defined( 'MB_CPT_VER' ) || post_type_exists( 'mb-post-type' ) ) {
				$active[] = 'metabox';
			}

			if ( function_exists( 'jet_engine' ) ) {
				$active[] = 'jetengine';
			}

			if ( function_exists( 'pods' ) ) {
				$active[] = 'pods';
			}

			if ( defined( 'TYPES_VERSION' ) ) {
				$active[] = 'toolset';
			}

			return ( 1 === count( $active ) ) ? $active[0] : '';
		}

		/**
		 * Builds the note shown when a non-hierarchical custom post
		 * type is selected: explains why nesting is unavailable and —
		 * when the post type builder can be identified — where to turn
		 * the "hierarchical" setting on.
		 *
		 * @param WP_Post_Type $post_type_object The post type.
		 * @return string
		 */
		protected static function get_hierarchical_note( $post_type_object ) {
			$instructions = array(
				'acf'       => __( 'This post type was created with ACF: go to ACF → Post Types, edit it, turn on "Hierarchical" under Advanced Settings, and reload this page.', 'save-and-go' ),
				'cptui'     => __( 'This post type was created with Custom Post Type UI: go to CPT UI → Add/Edit Post Types, select it, set "Hierarchical" to True (and check "Page Attributes" under Supports), and reload this page.', 'save-and-go' ),
				'metabox'   => __( 'This post type was created with Meta Box: go to Meta Box → Post Types, edit it, enable "Hierarchical" on the Advanced tab, and reload this page.', 'save-and-go' ),
				'jetengine' => __( 'This post type was created with JetEngine: go to JetEngine → Post Types, edit it, enable "Hierarchical" under Advanced Settings, and reload this page.', 'save-and-go' ),
				'pods'      => __( 'This post type was created with Pods: go to Pods Admin → Edit Pods, open it, enable "Hierarchical" under Advanced Options, and reload this page.', 'save-and-go' ),
				'toolset'   => __( 'This post type was created with Toolset: go to Toolset → Post Types, edit it, enable the "hierarchical" option, and reload this page.', 'save-and-go' ),
			);

			$intro = sprintf(
				/* translators: %s: plural post type name. */
				__( '%s can\'t be nested right now: this post type isn\'t "hierarchical", so WordPress doesn\'t allow parent/child entries for it.', 'save-and-go' ),
				$post_type_object->labels->name
			);

			$builder = self::detect_post_type_builder( $post_type_object->name );

			$how = isset( $instructions[ $builder ] )
				? $instructions[ $builder ]
				: __( 'If it was created with a post type builder (ACF, CPT UI, Meta Box, JetEngine…), edit the post type there and turn on its "Hierarchical" setting; if it is registered in code, add \'hierarchical\' => true to its register_post_type() settings. Then reload this page.', 'save-and-go' );

			return $intro . ' ' . $how;
		}

		/**
		 * Handles the bulk add form submission (admin-post.php).
		 *
		 * @return void
		 */
		public static function handle_submit() {
			check_admin_referer( self::FORM_ACTION );

			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_die( esc_html__( 'You are not allowed to create posts.', 'save-and-go' ) );
			}

			$redirect_url = add_query_arg(
				array(
					'page' => self::MENU_SLUG,
				),
				admin_url( 'admin.php' )
			);

			// Post type: must be one of the available types for this user.
			$post_type = isset( $_POST['sng_bulk_post_type'] ) ? sanitize_key( wp_unslash( $_POST['sng_bulk_post_type'] ) ) : '';
			$available = self::get_available_post_types();

			if ( ! isset( $available[ $post_type ] ) ) {
				wp_safe_redirect( add_query_arg( self::PARAM_ERROR, 'post-type', $redirect_url ) );
				exit;
			}

			$post_type_object = $available[ $post_type ];
			$can_publish      = current_user_can( $post_type_object->cap->publish_posts );

			// The row arrays. Rows are aligned by index.
			// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each element is unslashed and sanitized individually below.
			$titles   = isset( $_POST['sng_bulk_titles'] ) && is_array( $_POST['sng_bulk_titles'] ) ? wp_unslash( $_POST['sng_bulk_titles'] ) : array();
			$slugs    = isset( $_POST['sng_bulk_slugs'] ) && is_array( $_POST['sng_bulk_slugs'] ) ? wp_unslash( $_POST['sng_bulk_slugs'] ) : array();
			$statuses = isset( $_POST['sng_bulk_statuses'] ) && is_array( $_POST['sng_bulk_statuses'] ) ? wp_unslash( $_POST['sng_bulk_statuses'] ) : array();
			$depths   = isset( $_POST['sng_bulk_depths'] ) && is_array( $_POST['sng_bulk_depths'] ) ? wp_unslash( $_POST['sng_bulk_depths'] ) : array();
			$parents  = isset( $_POST['sng_bulk_parents'] ) && is_array( $_POST['sng_bulk_parents'] ) ? wp_unslash( $_POST['sng_bulk_parents'] ) : array();
			// phpcs:enable

			/*
			 * Hierarchy (hierarchical post types only, e.g. pages).
			 * - The base parent is an existing post picked in the "Parent"
			 *   dropdown; it becomes the parent of every top-level row.
			 * - Each row carries an indentation depth. A row's parent is
			 *   the closest row above it with a smaller depth (or the base
			 *   parent for top-level rows). If a row's parent could not be
			 *   created, its children attach to the nearest created
			 *   ancestor instead.
			 */
			$is_hierarchical = is_post_type_hierarchical( $post_type );
			$base_parent     = 0;

			if ( $is_hierarchical && ! empty( $parents[ $post_type ] ) ) {
				$base_parent = absint( $parents[ $post_type ] );

				if ( $base_parent ) {
					$parent_post = get_post( $base_parent );

					if (
						! $parent_post
						|| $parent_post->post_type !== $post_type
						|| in_array( $parent_post->post_status, array( 'trash', 'auto-draft' ), true )
					) {
						$base_parent = 0;
					}
				}
			}

			$allowed_statuses = self::get_allowed_statuses();
			$created          = 0;
			$skipped          = 0;
			$failed           = 0;
			$downgraded       = 0;
			$max_rows         = 200;
			$max_depth        = 9;
			$ancestors        = array();
			$last_depth       = -1;

			$row_count = min( count( $titles ), $max_rows );

			for ( $i = 0; $i < $row_count; $i++ ) {
				$title  = sanitize_text_field( $titles[ $i ] );
				$slug   = isset( $slugs[ $i ] ) ? sanitize_title( sanitize_text_field( $slugs[ $i ] ) ) : '';
				$status = isset( $statuses[ $i ] ) ? sanitize_key( $statuses[ $i ] ) : 'draft';

				// Empty row: nothing to create.
				if ( '' === trim( $title ) ) {
					if ( '' !== $slug ) {
						++$skipped;
					}
					continue;
				}

				if ( ! isset( $allowed_statuses[ $status ] ) ) {
					$status = 'draft';
				}

				// Users who cannot publish this post type get their
				// rows created as "pending review" instead.
				if ( ! $can_publish && in_array( $status, array( 'publish', 'private' ), true ) ) {
					$status = 'pending';
					++$downgraded;
				}

				$new_post = array(
					'post_title'  => wp_slash( $title ),
					'post_name'   => $slug,
					'post_status' => $status,
					'post_type'   => $post_type,
				);

				$depth = 0;

				if ( $is_hierarchical ) {
					// A row can be at most one level deeper than the
					// previously created row.
					$depth = isset( $depths[ $i ] ) ? absint( $depths[ $i ] ) : 0;
					$depth = min( $depth, $last_depth + 1, $max_depth );

					$parent_id = $base_parent;

					for ( $d = $depth - 1; $d >= 0; $d-- ) {
						if ( isset( $ancestors[ $d ] ) ) {
							$parent_id = $ancestors[ $d ];
							break;
						}
					}

					$new_post['post_parent'] = $parent_id;
				}

				$new_post_id = wp_insert_post( $new_post, true );

				if ( is_wp_error( $new_post_id ) ) {
					++$failed;
				} else {
					++$created;

					if ( $is_hierarchical ) {
						// This row is now the potential parent at its
						// depth; deeper previous branches are closed.
						$ancestors[ $depth ] = $new_post_id;

						foreach ( array_keys( $ancestors ) as $d ) {
							if ( $d > $depth ) {
								unset( $ancestors[ $d ] );
							}
						}

						$last_depth = $depth;
					}
				}
			}

			$redirect_url = add_query_arg(
				array(
					'post_type'            => $post_type,
					self::PARAM_CREATED    => $created,
					self::PARAM_SKIPPED    => $skipped,
					self::PARAM_FAILED     => $failed,
					self::PARAM_DOWNGRADED => $downgraded,
				),
				$redirect_url
			);

			wp_safe_redirect( $redirect_url );
			exit;
		}

		/**
		 * Renders the result notice after a submission, if any.
		 *
		 * @return void
		 */
		public static function render_notices() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only display of result counters set by our own redirect.
			if ( isset( $_GET[ self::PARAM_ERROR ] ) ) {
				echo '<div class="sng-notice sng-notice-error"><p>' . esc_html__( 'Please choose a valid post type.', 'save-and-go' ) . '</p></div>';
				return;
			}

			if ( ! isset( $_GET[ self::PARAM_CREATED ] ) ) {
				return;
			}

			$created    = absint( wp_unslash( $_GET[ self::PARAM_CREATED ] ) );
			$skipped    = isset( $_GET[ self::PARAM_SKIPPED ] ) ? absint( wp_unslash( $_GET[ self::PARAM_SKIPPED ] ) ) : 0;
			$failed     = isset( $_GET[ self::PARAM_FAILED ] ) ? absint( wp_unslash( $_GET[ self::PARAM_FAILED ] ) ) : 0;
			$downgraded = isset( $_GET[ self::PARAM_DOWNGRADED ] ) ? absint( wp_unslash( $_GET[ self::PARAM_DOWNGRADED ] ) ) : 0;
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			$parts = array();

			/* translators: %d: number of posts created. */
			$parts[] = sprintf( _n( '%d post created.', '%d posts created.', $created, 'save-and-go' ), $created );

			if ( $skipped > 0 ) {
				/* translators: %d: number of rows skipped. */
				$parts[] = sprintf( _n( '%d row without a title was skipped.', '%d rows without a title were skipped.', $skipped, 'save-and-go' ), $skipped );
			}

			if ( $failed > 0 ) {
				/* translators: %d: number of rows that failed. */
				$parts[] = sprintf( _n( '%d row could not be created.', '%d rows could not be created.', $failed, 'save-and-go' ), $failed );
			}

			if ( $downgraded > 0 ) {
				/* translators: %d: number of rows saved as pending. */
				$parts[] = sprintf( _n( '%d row was saved as "Pending review" because you cannot publish this post type.', '%d rows were saved as "Pending review" because you cannot publish this post type.', $downgraded, 'save-and-go' ), $downgraded );
			}

			$class = ( 0 === $created && ( $failed > 0 ) ) ? 'sng-notice-error' : 'sng-notice-success';

			echo '<div class="sng-notice ' . esc_attr( $class ) . '"><p>' . esc_html( implode( ' ', $parts ) ) . '</p></div>';
		}

		/**
		 * Renders the Bulk Add tab content.
		 *
		 * @return void
		 */
		public static function render_tab() {
			$post_types       = self::get_available_post_types();
			$allowed_statuses = self::get_allowed_statuses();
			$initial_rows     = 3;

			/*
			 * Pre-selected post type: set by the "Bulk Add" buttons on
			 * the post list screens; defaults to Pages.
			 */
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only: only pre-selects a dropdown value, validated against the available post types.
			$preselected = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			if ( ! isset( $post_types[ $preselected ] ) ) {
				$preselected = 'page';
			}

			/*
			 * Parent dropdowns: one per hierarchical post type (only the
			 * one matching the selected post type is shown/submitted).
			 * A type with no existing posts gets no dropdown — there is
			 * nothing to pick.
			 */
			$hierarchical_types = array();
			$parent_dropdowns   = array();

			foreach ( $post_types as $name => $post_type_object ) {
				if ( ! is_post_type_hierarchical( $name ) ) {
					continue;
				}

				$hierarchical_types[] = $name;

				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Nothing is output here: 'echo' is 0, so wp_dropdown_pages() returns the markup (which it escapes itself).
				$dropdown = wp_dropdown_pages(
					array(
						'post_type'         => $name,
						'name'              => 'sng_bulk_parents[' . $name . ']',
						'id'                => 'sng-bulk-parent-' . $name,
						'class'             => 'sng-bulk-select sng-bulk-parent',
						'echo'              => 0,
						'show_option_none'  => __( '&mdash; No parent (top level) &mdash;', 'save-and-go' ),
						'option_none_value' => '0',
						'sort_column'       => 'menu_order, post_title',
						// Not just published posts: with a bulk workflow,
						// the parent is often a draft created moments ago.
						'post_status'       => array( 'publish', 'future', 'draft', 'pending', 'private' ),
					)
				);
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

				if ( $dropdown ) {
					$parent_dropdowns[ $name ] = $dropdown;
				}
			}

			$is_flat = ! in_array( $preselected, $hierarchical_types, true );

			/*
			 * "Why can't I nest this?" notes, one per non-hierarchical
			 * custom post type. Regular posts are left out: nobody
			 * expects blog posts to nest.
			 */
			$hierarchy_notes = array();

			foreach ( $post_types as $name => $post_type_object ) {
				if ( 'post' === $name || in_array( $name, $hierarchical_types, true ) ) {
					continue;
				}

				$hierarchy_notes[ $name ] = self::get_hierarchical_note( $post_type_object );
			}
			?>

			<?php self::render_notices(); ?>

			<?php if ( empty( $post_types ) ) : ?>
				<section class="sng-card">
					<div class="sng-card-body">
						<p><?php esc_html_e( 'You do not have permission to create any post type.', 'save-and-go' ); ?></p>
					</div>
				</section>
				<?php
				return;
			endif;
			?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-sng-bulk="form">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::FORM_ACTION ); ?>" />
				<?php wp_nonce_field( self::FORM_ACTION ); ?>

				<section class="sng-card">
					<div class="sng-card-header">
						<h2><?php esc_html_e( 'Bulk add posts', 'save-and-go' ); ?></h2>
						<p><?php esc_html_e( 'Already know all the pages or posts you need? Type them in, pick a status for each, and create them all in one click. You can fill in the content later.', 'save-and-go' ); ?></p>
					</div>
					<div class="sng-card-body">

						<div class="sng-bulk-type-row">
							<label for="sng-bulk-post-type"><?php esc_html_e( 'Post type to create', 'save-and-go' ); ?></label>
							<select id="sng-bulk-post-type" name="sng_bulk_post_type" data-sng-bulk="post-type" data-sng-bulk-hierarchical="<?php echo esc_attr( implode( ',', $hierarchical_types ) ); ?>">
								<?php foreach ( $post_types as $name => $post_type_object ) : ?>
									<option value="<?php echo esc_attr( $name ); ?>" <?php selected( $preselected, $name ); ?>><?php echo esc_html( $post_type_object->labels->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<?php if ( ! empty( $parent_dropdowns ) ) : ?>
							<div class="sng-bulk-type-row sng-bulk-parent-row" data-sng-bulk="parent-row" <?php echo isset( $parent_dropdowns[ $preselected ] ) ? '' : 'hidden'; ?>>
								<span class="sng-bulk-parent-label"><?php esc_html_e( 'Parent', 'save-and-go' ); ?></span>
								<?php foreach ( $parent_dropdowns as $name => $dropdown ) : ?>
									<span class="sng-bulk-parent-wrap" data-sng-bulk-parent-for="<?php echo esc_attr( $name ); ?>" <?php echo ( $preselected === $name ) ? '' : 'hidden'; ?>>
										<?php
										if ( $preselected !== $name ) {
											// Hidden dropdowns must not submit their value.
											$dropdown = str_replace( '<select ', '<select disabled="disabled" ', $dropdown );
										}
										echo $dropdown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated <select> markup from wp_dropdown_pages().
										?>
									</span>
								<?php endforeach; ?>
								<span class="sng-bulk-hint"><?php esc_html_e( 'Top-level rows are created under this existing page.', 'save-and-go' ); ?></span>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $hierarchy_notes ) ) : ?>
							<div class="sng-bulk-note" data-sng-bulk="parent-note" <?php echo isset( $hierarchy_notes[ $preselected ] ) ? '' : 'hidden'; ?>>
								<svg viewBox="0 0 24 24" width="15" height="15" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/><path d="M12 11v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="8" r="1.1" fill="currentColor"/></svg>
								<?php foreach ( $hierarchy_notes as $name => $note ) : ?>
									<span data-sng-bulk-note-for="<?php echo esc_attr( $name ); ?>" <?php echo ( $preselected === $name ) ? '' : 'hidden'; ?>><?php echo esc_html( $note ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<div class="sng-bulk-table<?php echo $is_flat ? ' sng-bulk-flat' : ''; ?>" data-sng-bulk="table">
							<div class="sng-bulk-head" aria-hidden="true">
								<span class="sng-bulk-col-handle sng-bulk-h"></span>
								<span class="sng-bulk-col-num">#</span>
								<span><?php esc_html_e( 'Title', 'save-and-go' ); ?></span>
								<span><?php esc_html_e( 'Slug', 'save-and-go' ); ?></span>
								<span><?php esc_html_e( 'Status', 'save-and-go' ); ?></span>
								<span class="sng-bulk-col-indent sng-bulk-h"><?php echo esc_html_x( 'Nest', 'Column label above the indent/outdent buttons (Bulk Add page)', 'save-and-go' ); ?></span>
								<span class="sng-bulk-col-remove"></span>
							</div>

							<?php for ( $i = 0; $i < $initial_rows; $i++ ) : ?>
								<div class="sng-bulk-row" data-sng-bulk="row">
									<input type="hidden" name="sng_bulk_depths[]" value="0" data-sng-bulk="depth" />
									<button type="button" class="sng-bulk-handle sng-bulk-h" data-sng-bulk="handle" tabindex="-1" aria-hidden="true">
										<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><circle cx="9" cy="6" r="1.7"/><circle cx="15" cy="6" r="1.7"/><circle cx="9" cy="12" r="1.7"/><circle cx="15" cy="12" r="1.7"/><circle cx="9" cy="18" r="1.7"/><circle cx="15" cy="18" r="1.7"/></svg>
									</button>
									<span class="sng-bulk-col-num sng-bulk-num"><?php echo esc_html( $i + 1 ); ?></span>
									<input
										type="text"
										name="sng_bulk_titles[]"
										class="sng-bulk-input"
										data-sng-bulk="title"
										placeholder="<?php echo esc_attr_x( 'Title', 'Placeholder of the title column (Bulk Add tab)', 'save-and-go' ); ?>"
										aria-label="<?php esc_attr_e( 'Title', 'save-and-go' ); ?>"
									/>
									<input
										type="text"
										name="sng_bulk_slugs[]"
										class="sng-bulk-input sng-bulk-slug"
										data-sng-bulk="slug"
										placeholder="<?php echo esc_attr_x( 'auto-generated-from-title', 'Placeholder of the slug column (Bulk Add tab)', 'save-and-go' ); ?>"
										aria-label="<?php esc_attr_e( 'Slug', 'save-and-go' ); ?>"
									/>
									<select name="sng_bulk_statuses[]" class="sng-bulk-select" data-sng-bulk="status" aria-label="<?php esc_attr_e( 'Status', 'save-and-go' ); ?>">
										<?php foreach ( $allowed_statuses as $status => $label ) : ?>
											<option value="<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
									<span class="sng-bulk-indent-controls sng-bulk-h">
										<button type="button" class="sng-bulk-indent-button" data-sng-bulk="outdent" title="<?php esc_attr_e( 'Move up one level (no longer a child of the row above)', 'save-and-go' ); ?>" aria-label="<?php esc_attr_e( 'Move up one level (no longer a child of the row above)', 'save-and-go' ); ?>">
											<svg viewBox="0 0 24 24" width="14" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M19 6v5a4 4 0 0 1-4 4H6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="m10 11-4 4 4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
										</button>
										<button type="button" class="sng-bulk-indent-button" data-sng-bulk="indent" title="<?php esc_attr_e( 'Make this a child of the row above', 'save-and-go' ); ?>" aria-label="<?php esc_attr_e( 'Make this a child of the row above', 'save-and-go' ); ?>">
											<svg viewBox="0 0 24 24" width="14" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M5 6v5a4 4 0 0 0 4 4h9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="m14 11 4 4-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
										</button>
									</span>
									<button type="button" class="sng-bulk-remove" data-sng-bulk="remove" aria-label="<?php esc_attr_e( 'Remove this row', 'save-and-go' ); ?>">
										<svg viewBox="0 0 24 24" width="14" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
									</button>
								</div>
							<?php endfor; ?>
						</div>

						<div class="sng-bulk-actions">
							<button type="button" class="sng-bulk-add-row" data-sng-bulk="add-row">
								<svg viewBox="0 0 24 24" width="14" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
								<?php esc_html_e( 'Add row', 'save-and-go' ); ?>
							</button>
							<span class="sng-bulk-hint"><?php esc_html_e( 'Leave the slug empty to let WordPress generate it from the title. Rows without a title are ignored.', 'save-and-go' ); ?>
								<span class="sng-bulk-h"><?php esc_html_e( 'Drag the dotted handle to reorder rows — drag right (or use the arrows) to nest a row under the one above it.', 'save-and-go' ); ?></span>
							</span>
						</div>
					</div>
				</section>

				<div class="sng-footer">
					<button type="submit" class="sng-save-button">
						<?php echo Save_And_Go_Settings::get_icon_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static, hardcoded SVG. ?>
						<?php esc_html_e( 'Create posts', 'save-and-go' ); ?>
					</button>
				</div>
			</form>
			<?php
		}
	}
}
