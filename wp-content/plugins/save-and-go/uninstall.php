<?php
/**
 * Uninstall routine: removes the plugin's options.
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

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! is_multisite() ) {
	delete_option( 'save_and_go_options' );
} else {
	$save_and_go_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $save_and_go_site_ids as $save_and_go_site_id ) {
		switch_to_blog( $save_and_go_site_id );
		delete_option( 'save_and_go_options' );
		restore_current_blog();
	}

	unset( $save_and_go_site_ids, $save_and_go_site_id );
}
