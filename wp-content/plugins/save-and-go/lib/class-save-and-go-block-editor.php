<?php
/**
 * Block editor (Gutenberg) integration.
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

if ( ! class_exists( 'Save_And_Go_Block_Editor' ) ) {

	/**
	 * Adds the Save and Go button to the block editor and provides the
	 * REST endpoint that resolves the redirection after a save.
	 *
	 * The block editor saves posts through the REST API without
	 * reloading the page, so the classic 'redirect_post_location'
	 * filter never runs. Instead, the JavaScript waits for the save
	 * to complete, asks this endpoint where to go, and navigates there.
	 */
	class Save_And_Go_Block_Editor {

		/**
		 * REST namespace and route.
		 */
		const REST_NAMESPACE = 'save-and-go/v1';
		const REST_ROUTE     = '/go';

		/**
		 * Main entry point. Sets up all the WordPress hooks.
		 *
		 * @return void
		 */
		public static function setup() {
			add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_editor_assets' ) );
			add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		}

		/**
		 * Enqueues the block editor script and style.
		 *
		 * @return void
		 */
		public static function enqueue_block_editor_assets() {
			// Only on the post editor (not the site editor or widgets screen).
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

			if ( $screen && 'post' !== $screen->base ) {
				return;
			}

			$post   = get_post();
			$config = Save_And_Go_Post_Edit::get_js_config( $post, 'block' );

			if ( null === $config ) {
				return;
			}

			$config['restPath'] = self::REST_NAMESPACE . self::REST_ROUTE;
			$config['labels']   = array(
				'update'           => _x( 'Update', 'Verb used in the button label for a published post', 'save-and-go' ),
				'save'             => _x( 'Save', 'Verb used in the button label for an unpublished post', 'save-and-go' ),
				'menuTip'          => _x( 'Choose a Save & Go action', 'Tooltip of the dropdown toggle in the block editor', 'save-and-go' ),
				'saving'           => _x( 'Saving…', 'Button label while the post is being saved', 'save-and-go' ),
				'popupWaitMessage' => _x( 'Please wait while the post is being saved. This window will refresh automatically.', 'Message shown in the new window when "Save and View (new window)" is used.', 'save-and-go' ),
			);

			wp_enqueue_script(
				'save-and-go-block-editor',
				Save_And_Go_Utils::plugins_url( 'js/block-editor.js' ),
				array( 'wp-data', 'wp-api-fetch', 'wp-dom-ready', 'wp-i18n', 'utils' ),
				Save_And_Go_Utils::asset_version( 'js/block-editor.js' ),
				true
			);

			wp_enqueue_style(
				'save-and-go-block-editor',
				Save_And_Go_Utils::plugins_url( 'css/block-editor.css' ),
				array(),
				Save_And_Go_Utils::asset_version( 'css/block-editor.css' )
			);

			wp_add_inline_script(
				'save-and-go-block-editor',
				'window.SaveAndGo = window.SaveAndGo || {}; window.SaveAndGo.config = ' . wp_json_encode( $config ) . ';',
				'before'
			);
		}

		/**
		 * Registers the REST route used by the block editor after a save.
		 *
		 * @return void
		 */
		public static function register_rest_routes() {
			register_rest_route(
				self::REST_NAMESPACE,
				self::REST_ROUTE,
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'rest_go' ),
					'permission_callback' => array( __CLASS__, 'rest_permission' ),
					'args'                => array(
						'post_id'   => array(
							'type'              => 'integer',
							'required'          => true,
							'sanitize_callback' => 'absint',
						),
						'action_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				)
			);
		}

		/**
		 * Permission check for the REST route: the current user must be
		 * able to edit the saved post. The REST API also verifies the
		 * X-WP-Nonce header before this runs.
		 *
		 * @param WP_REST_Request $request The request.
		 * @return boolean|WP_Error
		 */
		public static function rest_permission( $request ) {
			$post_id = absint( $request['post_id'] );
			$post    = get_post( $post_id );

			if ( ! $post ) {
				return new WP_Error(
					'save_and_go_not_found',
					__( 'Post not found.', 'save-and-go' ),
					array( 'status' => 404 )
				);
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return new WP_Error(
					'save_and_go_forbidden',
					__( 'You are not allowed to edit this post.', 'save-and-go' ),
					array( 'status' => rest_authorization_required_code() )
				);
			}

			return true;
		}

		/**
		 * Resolves the URL where the block editor should navigate after
		 * a successful save with the given action.
		 *
		 * @param WP_REST_Request $request The request.
		 * @return WP_REST_Response|WP_Error
		 */
		public static function rest_go( $request ) {
			$post_id   = absint( $request['post_id'] );
			$action_id = $request['action_id'];
			$post      = get_post( $post_id );

			Save_And_Go_Actions::load_actions();

			// The action must exist AND be enabled in the settings.
			$enabled_actions = Save_And_Go_Settings::get_enabled_actions();

			if ( ! isset( $enabled_actions[ $action_id ] ) ) {
				return new WP_Error(
					'save_and_go_invalid_action',
					__( 'Unknown or disabled Save & Go action.', 'save-and-go' ),
					array( 'status' => 400 )
				);
			}

			$sat_action = $enabled_actions[ $action_id ];

			// The URL the editor would normally stay on.
			$current_url = Save_And_Go_Utils::admin_url(
				'post.php',
				array(
					'post'   => $post->ID,
					'action' => 'edit',
				)
			);

			$behavior     = $sat_action->get_block_editor_behavior();
			$redirect_url = null;

			if ( 'popup' !== $behavior ) {
				$new_location = $sat_action->get_redirect_url( $current_url, $post );

				if ( is_wp_error( $new_location ) ) {
					return $new_location;
				}

				if ( $new_location ) {
					$redirect_url = esc_url_raw( $new_location );
				}
			}

			return rest_ensure_response(
				array(
					'behavior'    => $behavior,
					'redirectUrl' => $redirect_url,
					'permalink'   => esc_url_raw( get_permalink( $post ) ),
				)
			);
		}
	}
}
