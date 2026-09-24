/**
 * Save and Go — settings page script.
 *
 * Keeps the "Default action" choices in sync with the enabled actions:
 * an action that is not shown cannot be the default one.
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
		var form = document.querySelector( '[data-sng-settings="form"]' );

		if ( ! form ) {
			return;
		}

		var actionCheckboxes = form.querySelectorAll( '[data-sng-settings="action"]' ),
			defaultRadios = form.querySelectorAll( '[data-sng-settings="default"]' ),
			modeRadios = form.querySelectorAll( '[data-sng-settings="mode"]' ),
			stayRow = form.querySelector( '[data-sng-stay-default]' );

		/**
		 * Enables/disables each "default action" radio based on whether
		 * the corresponding action is enabled. If the selected radio
		 * becomes disabled, falls back to the first radio ("Last used").
		 */
		function syncDefaultRadios() {
			var enabledIds = {},
				selectedDisabled = false;

			Array.prototype.forEach.call( actionCheckboxes, function( checkbox ) {
				enabledIds[ checkbox.getAttribute( 'data-sng-settings-value' ) ] = checkbox.checked;
			} );

			Array.prototype.forEach.call( defaultRadios, function( radio ) {
				var actionId = radio.value,
					row = radio.closest( '.sng-radio-row' );

				// The "_last" radio is always available.
				if ( ! Object.prototype.hasOwnProperty.call( enabledIds, actionId ) ) {
					return;
				}

				var enabled = enabledIds[ actionId ];

				radio.disabled = ! enabled;

				if ( row ) {
					row.classList.toggle( 'is-disabled', ! enabled );
				}

				if ( ! enabled && radio.checked ) {
					radio.checked = false;
					selectedDisabled = true;
				}
			} );

			if ( selectedDisabled && defaultRadios.length ) {
				defaultRadios[ 0 ].checked = true;
			}
		}

		/**
		 * Shows the "Update only" default choice only while at least one
		 * editor is set to "Replace the WordPress button". If it gets
		 * hidden while selected, falls back to the first radio
		 * ("Last used").
		 */
		function syncStayDefault() {
			if ( ! stayRow ) {
				return;
			}

			var hasReplaceMode = false;

			Array.prototype.forEach.call( modeRadios, function( radio ) {
				if ( radio.checked && 'replace' === radio.value ) {
					hasReplaceMode = true;
				}
			} );

			stayRow.classList.toggle( 'is-hidden', ! hasReplaceMode );

			var stayRadio = stayRow.querySelector( '[data-sng-settings="default"]' );

			if ( ! hasReplaceMode && stayRadio && stayRadio.checked ) {
				stayRadio.checked = false;

				if ( defaultRadios.length ) {
					defaultRadios[ 0 ].checked = true;
				}
			}
		}

		Array.prototype.forEach.call( actionCheckboxes, function( checkbox ) {
			checkbox.addEventListener( 'change', syncDefaultRadios );
		} );

		Array.prototype.forEach.call( modeRadios, function( radio ) {
			radio.addEventListener( 'change', syncStayDefault );
		} );

		syncDefaultRadios();
		syncStayDefault();
	} );
} )();
