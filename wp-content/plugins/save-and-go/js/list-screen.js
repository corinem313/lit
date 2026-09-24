/**
 * Save & Go — post list screen script.
 *
 * Adds a "Bulk Add" button right after the "Add New" button on post
 * list screens (edit.php), linking to the Bulk Add page with the
 * current post type pre-selected.
 *
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

( function() {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function() {
		var config = window.SaveAndGo && window.SaveAndGo.listScreen;

		if ( ! config || ! config.url ) {
			return;
		}

		var link = document.createElement( 'a' );

		link.className = 'page-title-action sng-list-bulk-add';
		link.href = config.url;
		link.textContent = config.label;

		var addNew = document.querySelector( '.wrap .page-title-action' );

		if ( addNew && addNew.parentNode ) {
			// Right after the "Add New" button.
			if ( addNew.nextSibling ) {
				addNew.parentNode.insertBefore( link, addNew.nextSibling );
			} else {
				addNew.parentNode.appendChild( link );
			}
		} else {
			// No "Add New" button (unusual): fall back to the page title.
			var title = document.querySelector( '.wrap > h1' );

			if ( title ) {
				title.appendChild( link );
			}
		}
	} );
} )();
