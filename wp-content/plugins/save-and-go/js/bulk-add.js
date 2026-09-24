/**
 * Save & Go — Bulk Add tab script.
 *
 * Handles the dynamic rows of the Bulk Add table, the live slug
 * formatting (spaces become dashes as you type; the slug auto-fills
 * from the title until edited manually), and — for hierarchical post
 * types like pages — the row hierarchy: drag the handle to reorder
 * rows and drag right/left (or use the indent/outdent arrows) to nest
 * a row under the one above it, exactly like the WordPress Menus
 * screen. The indentation depth of each row is submitted with the
 * form and becomes the created posts' parent/child structure.
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

	var MAX_ROWS = 200,
		MAX_DEPTH = 9,
		INDENT_STEP = 26; // px of horizontal drag per depth level; matches the CSS indent.

	/**
	 * Formats a string into slug characters: lowercase, spaces (and
	 * underscores) to dashes, anything else invalid removed, runs of
	 * dashes collapsed. Leading/trailing dashes are kept while typing
	 * and trimmed on blur/submit.
	 *
	 * @param {string} value
	 * @return {string}
	 */
	function formatSlug( value ) {
		return value
			.toLowerCase()
			.replace( /[\s_]+/g, '-' )
			.replace( /[^a-z0-9-]/g, '' )
			.replace( /-{2,}/g, '-' );
	}

	/**
	 * Formats an input's value as a slug while keeping the caret in
	 * place as well as possible.
	 *
	 * @param {HTMLInputElement} input
	 */
	function formatSlugInput( input ) {
		var before = input.value,
			after = formatSlug( before ),
			caret;

		if ( before === after ) {
			return;
		}

		caret = input.selectionStart;
		input.value = after;

		if ( null !== caret ) {
			caret = Math.max( 0, caret + ( after.length - before.length ) );
			input.setSelectionRange( caret, caret );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function() {
		var form = document.querySelector( '[data-sng-bulk="form"]' );

		if ( ! form ) {
			return;
		}

		var table = form.querySelector( '[data-sng-bulk="table"]' ),
			addRowButton = form.querySelector( '[data-sng-bulk="add-row"]' ),
			typeSelect = form.querySelector( '[data-sng-bulk="post-type"]' ),
			parentRow = form.querySelector( '[data-sng-bulk="parent-row"]' ),
			noteRow = form.querySelector( '[data-sng-bulk="parent-note"]' ),
			hierarchicalTypes = [];

		if ( typeSelect && typeSelect.getAttribute( 'data-sng-bulk-hierarchical' ) ) {
			hierarchicalTypes = typeSelect.getAttribute( 'data-sng-bulk-hierarchical' ).split( ',' );
		}

		function getRows() {
			return table.querySelectorAll( '[data-sng-bulk="row"]' );
		}

		function isFlat() {
			return table.classList.contains( 'sng-bulk-flat' );
		}

		/**
		 * Reads a row's hierarchy depth from its hidden input.
		 *
		 * @param {Element} row
		 * @return {number}
		 */
		function getDepth( row ) {
			var value = parseInt( row.querySelector( '[data-sng-bulk="depth"]' ).value, 10 );

			return isNaN( value ) ? 0 : Math.max( 0, Math.min( MAX_DEPTH, value ) );
		}

		/**
		 * Writes a row's hierarchy depth to its hidden input and updates
		 * the visual indentation.
		 *
		 * @param {Element} row
		 * @param {number}  depth
		 */
		function setDepth( row, depth ) {
			depth = Math.max( 0, Math.min( MAX_DEPTH, depth ) );
			row.querySelector( '[data-sng-bulk="depth"]' ).value = String( depth );
			row.style.setProperty( '--sng-depth', String( depth ) );

			// Child rows show a connector elbow to their parent.
			row.classList.toggle( 'sng-bulk-child', depth > 0 );
		}

		/**
		 * Renumbers the "#" column after adding/removing/moving rows.
		 */
		function renumberRows() {
			var rows = getRows(),
				i;

			for ( i = 0; i < rows.length; i++ ) {
				rows[ i ].querySelector( '.sng-bulk-num' ).textContent = String( i + 1 );
			}
		}

		/**
		 * Ensures the depths form a valid tree: the first row is at the
		 * top level, and a row can be at most one level deeper than the
		 * row above it. Also refreshes the indent/outdent buttons'
		 * disabled states.
		 */
		function normalizeDepths() {
			var rows = getRows(),
				previousDepth = -1,
				i,
				depth,
				maxAllowed,
				outdentButton,
				indentButton;

			for ( i = 0; i < rows.length; i++ ) {
				maxAllowed = Math.min( previousDepth + 1, MAX_DEPTH );
				depth = Math.min( getDepth( rows[ i ] ), maxAllowed );

				setDepth( rows[ i ], depth );

				outdentButton = rows[ i ].querySelector( '[data-sng-bulk="outdent"]' );
				indentButton = rows[ i ].querySelector( '[data-sng-bulk="indent"]' );

				if ( outdentButton ) {
					outdentButton.disabled = ( 0 === depth );
				}

				if ( indentButton ) {
					indentButton.disabled = ( depth >= maxAllowed );
				}

				previousDepth = depth;
			}
		}

		/**
		 * Returns the row plus all its descendants: the consecutive rows
		 * below it whose depth is greater than its own.
		 *
		 * @param {Element} row
		 * @return {Array} Array of row elements.
		 */
		function getRowBlock( row ) {
			var rows = getRows(),
				block = [ row ],
				depth = getDepth( row ),
				started = false,
				i;

			for ( i = 0; i < rows.length; i++ ) {
				if ( rows[ i ] === row ) {
					started = true;
					continue;
				}

				if ( ! started ) {
					continue;
				}

				if ( getDepth( rows[ i ] ) > depth ) {
					block.push( rows[ i ] );
				} else {
					break;
				}
			}

			return block;
		}

		/**
		 * Changes a row's depth by delta, moving its descendants with it.
		 *
		 * @param {Element} row
		 * @param {number}  delta +1 (indent) or -1 (outdent).
		 */
		function shiftDepth( row, delta ) {
			var block = getRowBlock( row ),
				i;

			for ( i = 0; i < block.length; i++ ) {
				setDepth( block[ i ], getDepth( block[ i ] ) + delta );
			}

			normalizeDepths();
		}

		/* ---------------------------------------------------------- */
		/* Dragging (reorder + nest, like the WordPress Menus screen) */
		/* ---------------------------------------------------------- */

		/**
		 * Wires the drag handle of one row: vertical movement reorders
		 * the row (descendants move along), horizontal movement changes
		 * its depth.
		 *
		 * @param {Element} row
		 * @param {Element} handle
		 */
		function setupDrag( row, handle ) {
			handle.addEventListener( 'pointerdown', function( event ) {
				if ( isFlat() || 0 !== event.button ) {
					return;
				}

				event.preventDefault();

				var block = getRowBlock( row ),
					startX = event.clientX,
					startDepth = getDepth( row ),
					maxRelative = 0,
					i;

				// How much deeper than the dragged row its deepest
				// descendant is, so the whole block stays within bounds.
				for ( i = 0; i < block.length; i++ ) {
					maxRelative = Math.max( maxRelative, getDepth( block[ i ] ) - startDepth );
				}

				table.classList.add( 'sng-bulk-drag-active' );

				for ( i = 0; i < block.length; i++ ) {
					block[ i ].classList.add( 'sng-bulk-dragging' );
				}

				function isInBlock( element ) {
					return -1 !== block.indexOf( element );
				}

				function onMove( moveEvent ) {
					var rows = getRows(),
						reference = null,
						j,
						rect;

					// Vertical: find the first non-dragged row whose
					// middle is below the pointer; the block goes right
					// before it.
					for ( j = 0; j < rows.length; j++ ) {
						if ( isInBlock( rows[ j ] ) ) {
							continue;
						}

						rect = rows[ j ].getBoundingClientRect();

						if ( moveEvent.clientY < rect.top + ( rect.height / 2 ) ) {
							reference = rows[ j ];
							break;
						}
					}

					// Already in place when the row right after the block
					// is the reference (both are null at the end).
					if ( reference !== block[ block.length - 1 ].nextElementSibling ) {
						for ( j = 0; j < block.length; j++ ) {
							if ( reference ) {
								table.insertBefore( block[ j ], reference );
							} else {
								table.appendChild( block[ j ] );
							}
						}
					}

					// Horizontal: each INDENT_STEP px is one depth level.
					var steps = Math.round( ( moveEvent.clientX - startX ) / INDENT_STEP ),
						targetDepth = startDepth + steps,
						previous = block[ 0 ].previousElementSibling,
						maxAllowed = 0,
						delta;

					if ( previous && previous.matches( '[data-sng-bulk="row"]' ) ) {
						maxAllowed = getDepth( previous ) + 1;
					}

					maxAllowed = Math.min( maxAllowed, MAX_DEPTH - maxRelative );
					targetDepth = Math.max( 0, Math.min( targetDepth, maxAllowed ) );
					delta = targetDepth - getDepth( block[ 0 ] );

					if ( 0 !== delta ) {
						for ( j = 0; j < block.length; j++ ) {
							setDepth( block[ j ], getDepth( block[ j ] ) + delta );
						}
					}

					renumberRows();
				}

				function onEnd() {
					document.removeEventListener( 'pointermove', onMove );
					document.removeEventListener( 'pointerup', onEnd );
					document.removeEventListener( 'pointercancel', onEnd );

					table.classList.remove( 'sng-bulk-drag-active' );

					for ( var j = 0; j < block.length; j++ ) {
						block[ j ].classList.remove( 'sng-bulk-dragging' );
					}

					normalizeDepths();
					renumberRows();
				}

				/*
				 * The listeners live on the document, not the handle:
				 * moving the row in the DOM mid-drag makes the browser
				 * drop pointer capture, and a handle-bound pointerup
				 * would then never fire.
				 */
				document.addEventListener( 'pointermove', onMove );
				document.addEventListener( 'pointerup', onEnd );
				document.addEventListener( 'pointercancel', onEnd );
			} );
		}

		/**
		 * Wires the behaviors of one row: slug formatting, auto-slug
		 * from the title, remove button, indent/outdent, drag handle,
		 * and auto-append when typing in the last row.
		 *
		 * @param {Element} row
		 */
		function setupRow( row ) {
			var titleInput = row.querySelector( '[data-sng-bulk="title"]' ),
				slugInput = row.querySelector( '[data-sng-bulk="slug"]' ),
				removeButton = row.querySelector( '[data-sng-bulk="remove"]' ),
				outdentButton = row.querySelector( '[data-sng-bulk="outdent"]' ),
				indentButton = row.querySelector( '[data-sng-bulk="indent"]' ),
				handle = row.querySelector( '[data-sng-bulk="handle"]' ),
				slugIsManual = false,
				settingProgrammatically = false;

			// Reflect the (possibly cloned) depth value visually.
			setDepth( row, getDepth( row ) );

			slugInput.addEventListener( 'input', function() {
				if ( ! settingProgrammatically ) {
					// The user typed in the slug field themselves. An
					// emptied field goes back to auto mode.
					slugIsManual = '' !== slugInput.value;
				}

				formatSlugInput( slugInput );
			} );

			slugInput.addEventListener( 'blur', function() {
				slugInput.value = slugInput.value.replace( /^-+|-+$/g, '' );

				if ( '' === slugInput.value ) {
					slugIsManual = false;
				}
			} );

			titleInput.addEventListener( 'input', function() {
				// Auto-fill the slug from the title until the user has
				// edited the slug manually.
				if ( ! slugIsManual ) {
					settingProgrammatically = true;
					slugInput.value = formatSlug( titleInput.value ).replace( /^-+|-+$/g, '' );
					settingProgrammatically = false;
				}

				// Typing in the last row appends a fresh one, so you can
				// keep tabbing/typing without reaching for the button.
				var rows = getRows();

				if ( row === rows[ rows.length - 1 ] && '' !== titleInput.value ) {
					addRow( false );
				}
			} );

			removeButton.addEventListener( 'click', function() {
				if ( getRows().length > 1 ) {
					row.parentNode.removeChild( row );
					normalizeDepths();
					renumberRows();
				} else {
					// Last remaining row: just clear it.
					titleInput.value = '';
					slugInput.value = '';
					slugIsManual = false;
					setDepth( row, 0 );
					normalizeDepths();
				}
			} );

			if ( outdentButton ) {
				outdentButton.addEventListener( 'click', function() {
					shiftDepth( row, -1 );
				} );
			}

			if ( indentButton ) {
				indentButton.addEventListener( 'click', function() {
					shiftDepth( row, 1 );
				} );
			}

			if ( handle ) {
				setupDrag( row, handle );
			}
		}

		/**
		 * Appends a new empty row, cloned from the last one. The new row
		 * starts at the same depth as the row above it (a sibling).
		 *
		 * @param {boolean} focus Whether to focus the new row's title field.
		 */
		function addRow( focus ) {
			var rows = getRows(),
				lastRow = rows[ rows.length - 1 ],
				newRow;

			if ( rows.length >= MAX_ROWS ) {
				return;
			}

			newRow = lastRow.cloneNode( true );

			newRow.querySelector( '[data-sng-bulk="title"]' ).value = '';
			newRow.querySelector( '[data-sng-bulk="slug"]' ).value = '';
			newRow.querySelector( '[data-sng-bulk="status"]' ).selectedIndex =
				lastRow.querySelector( '[data-sng-bulk="status"]' ).selectedIndex;
			newRow.classList.remove( 'sng-bulk-dragging' );

			table.appendChild( newRow );
			setupRow( newRow );
			normalizeDepths();
			renumberRows();

			if ( focus ) {
				newRow.querySelector( '[data-sng-bulk="title"]' ).focus();
			}
		}

		/**
		 * Shows/hides the hierarchy controls and the matching "Parent"
		 * dropdown for the selected post type. Hidden dropdowns are
		 * disabled so only the visible one is submitted.
		 */
		function syncPostType() {
			if ( ! typeSelect ) {
				return;
			}

			var type = typeSelect.value,
				isHierarchical = -1 !== hierarchicalTypes.indexOf( type ),
				hasParentDropdown = false;

			table.classList.toggle( 'sng-bulk-flat', ! isHierarchical );

			if ( parentRow ) {
				Array.prototype.forEach.call(
					parentRow.querySelectorAll( '[data-sng-bulk-parent-for]' ),
					function( wrap ) {
						var matches = wrap.getAttribute( 'data-sng-bulk-parent-for' ) === type,
							select = wrap.querySelector( 'select' );

						wrap.hidden = ! matches;

						if ( select ) {
							select.disabled = ! matches;
						}

						if ( matches ) {
							hasParentDropdown = true;
						}
					}
				);

				parentRow.hidden = ! hasParentDropdown;
			}

			// "Why can't I nest this?" note: only non-hierarchical
			// custom post types have one.
			if ( noteRow ) {
				var hasNote = false;

				Array.prototype.forEach.call(
					noteRow.querySelectorAll( '[data-sng-bulk-note-for]' ),
					function( note ) {
						var matches = note.getAttribute( 'data-sng-bulk-note-for' ) === type;

						note.hidden = ! matches;

						if ( matches ) {
							hasNote = true;
						}
					}
				);

				noteRow.hidden = ! hasNote;
			}
		}

		Array.prototype.forEach.call( getRows(), setupRow );
		normalizeDepths();
		renumberRows();
		syncPostType();

		if ( typeSelect ) {
			typeSelect.addEventListener( 'change', syncPostType );
		}

		addRowButton.addEventListener( 'click', function() {
			addRow( true );
		} );

		// Final cleanup before submitting: trim stray dashes.
		form.addEventListener( 'submit', function() {
			Array.prototype.forEach.call(
				form.querySelectorAll( '[data-sng-bulk="slug"]' ),
				function( slugInput ) {
					slugInput.value = formatSlug( slugInput.value ).replace( /^-+|-+$/g, '' );
				}
			);
		} );
	} );
} )();
