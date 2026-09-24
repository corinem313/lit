<?php
/**
 * Classic editor (post edit screen) integration.
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

if ( ! class_exists( 'Save_And_Go_Post_Edit' ) ) {

	/**
	 * Management of the "edit post" and "new post" admin pages
	 * (classic editor).
	 */
	class Save_And_Go_Post_Edit {

		/**
		 * URL parameter defining the action to do after saving.
		 */
		const HTTP_PARAM_ACTION = 'sng-action';

		/**
		 * Main entry point. Sets up all the WordPress hooks.
		 *
		 * @return void
		 */
		public static function setup() {
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'add_admin_scripts' ) );
		}

		/**
		 * Builds the configuration object shared with the JavaScript,
		 * for both the classic and the block editor.
		 *
		 * @param WP_Post|null $post   The post being edited.
		 * @param string       $editor 'classic' or 'block'. Selects which
		 *                             "display as default" toggle applies.
		 * @return array|null Null when no action is enabled.
		 */
		public static function get_js_config( $post, $editor = 'classic' ) {
			$options         = Save_And_Go_Settings::get_options();
			$enabled_actions = Save_And_Go_Settings::get_enabled_actions();

			// If the user didn't enable any action, we quit here.
			if ( ! count( $enabled_actions ) ) {
				return null;
			}

			$button_mode = ( 'block' === $editor )
				? $options['button-mode-block']
				: $options['button-mode-classic'];

			$config = array(
				'buttonMode'      => $button_mode,
				'setAsDefault'    => ( 'beside' !== $button_mode ),
				'replaceDefault'  => ( 'replace' === $button_mode ),
				'actions'         => array(),
				'defaultActionId' => $options['default-action'],
				'actionLastId'    => Save_And_Go_Actions::ACTION_LAST,
				'httpParamAction' => self::HTTP_PARAM_ACTION,
				'cookiePath'      => defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
			);

			foreach ( $enabled_actions as $action ) {
				$new_js_action = array(
					'id'                 => $action->get_id(),
					'buttonLabelPattern' => $action->get_button_label_pattern( $post ),
					'enabled'            => (bool) $action->is_enabled( $post ),
				);

				$button_title = $action->get_button_title( $post );

				if ( $button_title ) {
					$new_js_action['title'] = $button_title;
				}

				$config['actions'][] = $new_js_action;
			}

			/*
			 * Replace mode hides the WordPress button, which would leave
			 * no way to "just save" without going somewhere. So in that
			 * mode (and only in that mode) a plain save option is added
			 * to the dropdown: it saves the post and stays on the page,
			 * exactly like the hidden WordPress button would.
			 */
			if ( 'replace' === $button_mode ) {
				$config['stayActionId'] = Save_And_Go_Actions::ACTION_STAY;
				$config['actions'][]    = array(
					'id'                 => Save_And_Go_Actions::ACTION_STAY,
					'buttonLabelPattern' => '%s',
					'enabled'            => true,
					'title'              => _x( 'Just save — stay on this page.', 'Title of the plain save option shown in replace mode', 'save-and-go' ),
				);
			}

			/**
			 * Filters the configuration object passed to the editor scripts.
			 *
			 * @param array        $config The configuration.
			 * @param WP_Post|null $post   The post being edited.
			 */
			return apply_filters( 'save_and_go_js_config', $config, $post );
		}

		/**
		 * Adds the JavaScript and CSS files on the "edit post" or
		 * "new post" pages when the classic editor is used.
		 *
		 * @param string $page_id Page id where we are.
		 * @return void
		 */
		public static function add_admin_scripts( $page_id ) {
			if ( 'post.php' !== $page_id && 'post-new.php' !== $page_id ) {
				return;
			}

			// The block editor has its own integration.
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

			if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
				return;
			}

			$config = self::get_js_config( get_post(), 'classic' );

			if ( null === $config ) {
				return;
			}

			wp_enqueue_script(
				'save-and-go-post-edit',
				Save_And_Go_Utils::plugins_url( 'js/post-edit.js' ),
				array( 'jquery', 'utils' ),
				Save_And_Go_Utils::asset_version( 'js/post-edit.js' ),
				true
			);

			wp_enqueue_style(
				'save-and-go-post-edit',
				Save_And_Go_Utils::plugins_url( 'css/post-edit.css' ),
				array(),
				Save_And_Go_Utils::asset_version( 'css/post-edit.css' )
			);

			wp_style_add_data( 'save-and-go-post-edit', 'rtl', 'replace' );

			wp_add_inline_script(
				'save-and-go-post-edit',
				'window.SaveAndGo = window.SaveAndGo || {}; window.SaveAndGo.config = ' . wp_json_encode( $config ) . ';',
				'before'
			);
		}
	}
}
