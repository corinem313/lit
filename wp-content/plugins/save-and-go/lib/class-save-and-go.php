<?php
/**
 * Main plugin class.
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

if ( ! class_exists( 'Save_And_Go' ) ) {

	/**
	 * Main class. Boots every component of the plugin and registers
	 * the built-in actions.
	 */
	class Save_And_Go {

		/**
		 * Main entry point of the plugin. Calls the setup function
		 * of the other classes.
		 *
		 * @return void
		 */
		public static function setup() {
			Save_And_Go_Settings::setup();
			Save_And_Go_Bulk_Add::setup();
			Save_And_Go_Post_Edit::setup();
			Save_And_Go_Post_Save::setup();
			Save_And_Go_Block_Editor::setup();
			Save_And_Go_Messages::setup();
			Save_And_Go_Actions::setup();

			add_filter( 'save_and_go_load_actions', array( __CLASS__, 'load_default_actions' ) );
		}

		/**
		 * Returns the localized name of the plugin.
		 *
		 * @return string
		 */
		public static function get_localized_name() {
			return __( 'Save & Go', 'save-and-go' );
		}

		/**
		 * Called by the save_and_go_load_actions filter. Loads all the
		 * actions that come by default with the plugin.
		 *
		 * @param array $actions Currently registered actions.
		 * @return array
		 */
		public static function load_default_actions( $actions ) {
			$default_actions_classes = array(
				'Save_And_Go_Action_New',
				'Save_And_Go_Action_Duplicate',
				'Save_And_Go_Action_List',
				'Save_And_Go_Action_Return',
				'Save_And_Go_Action_Next',
				'Save_And_Go_Action_Previous',
				'Save_And_Go_Action_View',
				'Save_And_Go_Action_View_Popup',
			);

			foreach ( $default_actions_classes as $class_name ) {
				$actions[] = new $class_name();
			}

			return $actions;
		}

		/**
		 * Returns the full path of the plugin's main file.
		 *
		 * @return string
		 */
		public static function get_main_file_path() {
			return SAVE_AND_GO_FILE;
		}
	}
}
