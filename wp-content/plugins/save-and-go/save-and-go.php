<?php
/**
 * Plugin Name:       Save & Go
 * Description:       Adds a modern "2-in-1" save button to the post editor — classic AND block editor (Gutenberg) — that saves the post and immediately takes you to your next action: a new post, the next/previous post, the posts list, the post's page, and more.
 * Version:           1.3.2
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            Alisha Thomas
 * Author URI:        https://eightysevenweb.com
 * License:           GPLv3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       save-and-go
 *
 * @package Save_And_Go
 */

/**
 * Copyright 2026 Alisha Thomas (https://eightysevenweb.com)
 *
 * Save and Go is based on the "Improved Save Button" plugin,
 * copyright 2017 Label Blanc (http://www.labelblanc.ca/),
 * released under the GNU General Public License v3.
 *
 * Save and Go is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SAVE_AND_GO_VERSION', '1.3.2' );
define( 'SAVE_AND_GO_FILE', __FILE__ );

// Core library files.
$save_and_go_lib_files = array(
	'class-save-and-go.php',
	'class-save-and-go-utils.php',
	'class-save-and-go-settings.php',
	'class-save-and-go-bulk-add.php',
	'class-save-and-go-post-edit.php',
	'class-save-and-go-post-save.php',
	'class-save-and-go-block-editor.php',
	'class-save-and-go-messages.php',
	'class-save-and-go-actions.php',
	'class-save-and-go-action.php',
);

foreach ( $save_and_go_lib_files as $save_and_go_file_name ) {
	require_once plugin_dir_path( __FILE__ ) . 'lib/' . $save_and_go_file_name;
}

// Built-in action files.
$save_and_go_action_files = array(
	'class-save-and-go-action-new.php',
	'class-save-and-go-action-list.php',
	'class-save-and-go-action-view.php',
	'class-save-and-go-action-view-popup.php',
	'class-save-and-go-action-next.php',
	'class-save-and-go-action-previous.php',
	'class-save-and-go-action-duplicate.php',
	'class-save-and-go-action-return.php',
);

foreach ( $save_and_go_action_files as $save_and_go_file_name ) {
	require_once plugin_dir_path( __FILE__ ) . 'actions/' . $save_and_go_file_name;
}

unset( $save_and_go_lib_files, $save_and_go_action_files, $save_and_go_file_name );

Save_And_Go::setup();
