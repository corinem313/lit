<?php
/**
 * Actions registry.
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

if ( ! class_exists( 'Save_And_Go_Actions' ) ) {

	/**
	 * Static class to manage the actions.
	 */
	class Save_And_Go_Actions {

		/**
		 * Special id of the 'use last' action.
		 */
		const ACTION_LAST = '_last';

		/**
		 * Special id of the 'update only' (save and stay) option that
		 * exists in editors where Save & Go replaces the WordPress button.
		 */
		const ACTION_STAY = '_stay';

		/**
		 * Array of loaded actions.
		 *
		 * @var array
		 */
		protected static $actions = array();

		/**
		 * Called on plugin setup. Loads all the actions.
		 *
		 * @return void
		 */
		public static function setup() {
			// Priority set to 9 to be sure it executes before the settings page
			// creates the actions list (which has priority 10).
			add_action( 'admin_init', array( __CLASS__, 'load_actions' ), 9 );
			// The block editor and REST requests also need the actions.
			add_action( 'rest_api_init', array( __CLASS__, 'load_actions' ), 9 );
		}

		/**
		 * Applies a filter to load all the actions.
		 * New actions register by hooking on the save_and_go_load_actions
		 * filter and adding an instance of themselves to the supplied array.
		 *
		 * @return void
		 */
		public static function load_actions() {
			if ( ! empty( self::$actions ) ) {
				return;
			}

			/**
			 * Filters the list of Save and Go actions.
			 *
			 * @param array $actions Array of Save_And_Go_Action instances.
			 */
			self::$actions = apply_filters( 'save_and_go_load_actions', self::$actions );
		}

		/**
		 * Returns an array of all loaded actions. Note that this method
		 * returns all actions, it is NOT to be used to get
		 * only enabled actions (in the settings page).
		 *
		 * @return array
		 */
		public static function get_actions() {
			return self::$actions;
		}

		/**
		 * Returns true if an action with the specified id is loaded.
		 *
		 * @param string $action_id The action id.
		 * @return boolean
		 */
		public static function action_exists( $action_id ) {
			return ! is_null( self::get_action( $action_id ) );
		}

		/**
		 * Returns the action with the specified id. If the action
		 * does not exist (invalid id), null is returned.
		 *
		 * @param string $action_id The action id.
		 * @return Save_And_Go_Action|null
		 */
		public static function get_action( $action_id ) {
			foreach ( self::$actions as $action ) {
				if ( $action->get_id() === $action_id ) {
					return $action;
				}
			}

			return null;
		}
	}
}
