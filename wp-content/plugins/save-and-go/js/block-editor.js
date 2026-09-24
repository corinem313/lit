/**
 * Save and Go — block editor (Gutenberg) script.
 *
 * The block editor saves posts through the REST API without reloading
 * the page, so this script: (1) renders a Save and Go split button in
 * the editor header, (2) triggers the editor save, (3) once the save
 * has succeeded, asks the plugin's REST endpoint where to go and
 * navigates there.
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

window.SaveAndGo = window.SaveAndGo || {};

( function( wp ) {
	'use strict';

	var config = window.SaveAndGo.config;

	if ( ! config || ! config.actions || ! config.actions.length || ! wp || ! wp.data ) {
		return;
	}

	var select = wp.data.select,
		dispatch = wp.data.dispatch,
		subscribe = wp.data.subscribe,
		apiFetch = wp.apiFetch,
		LAST_USED_COOKIE_NAME = 'sng-last-used-action',
		HEADER_SELECTORS = [
			'.editor-header__settings',
			'.edit-post-header__settings'
		],
		PUBLISH_BUTTON_SELECTORS = [
			'.editor-post-publish-button__button',
			'.editor-post-publish-button'
		],
		container = null,
		mainButton = null,
		mainLabel = null,
		toggleButton = null,
		menu = null,
		currentAction = null,
		isRunning = false,
		isNavigating = false,
		isDummy = false,
		menuOpen = false,
		supportsPopover = 'undefined' !== typeof HTMLElement && 'function' === typeof HTMLElement.prototype.showPopover;

	/* -------------------------------------------------------------- */
	/* Helpers                                                        */
	/* -------------------------------------------------------------- */

	function getActionFromId( id ) {
		for ( var i = 0; i < config.actions.length; i++ ) {
			if ( config.actions[ i ].id === id ) {
				return config.actions[ i ];
			}
		}
		return null;
	}

	function getDefaultAction() {
		var defaultActionId = config.defaultActionId,
			fallbackAction = null,
			defaultAction = null,
			i;

		for ( i = 0; i < config.actions.length; i++ ) {
			if ( config.actions[ i ].enabled ) {
				fallbackAction = config.actions[ i ];
				break;
			}
		}

		if ( ! defaultActionId ) {
			return fallbackAction;
		}

		if ( config.actionLastId === defaultActionId ) {
			var cookieVal = window.wpCookies ? window.wpCookies.get( LAST_USED_COOKIE_NAME ) : null;

			if ( ! cookieVal ) {
				return fallbackAction;
			}

			defaultActionId = cookieVal;
		}

		defaultAction = getActionFromId( defaultActionId );

		if ( ! defaultAction || ! defaultAction.enabled ) {
			defaultAction = fallbackAction;
		}

		return defaultAction;
	}

	/**
	 * The verb shown in the button label: "Update" for content that is
	 * already live, "Save" otherwise.
	 */
	function statusVerb() {
		var editor = select( 'core/editor' );

		if ( editor && ( editor.isCurrentPostPublished() || editor.isCurrentPostScheduled() ) ) {
			return config.labels.update;
		}

		return config.labels.save;
	}

	function generateLabel( pattern ) {
		return pattern.replace( '%s', statusVerb() );
	}

	function htmlToText( html ) {
		var tmp = document.createElement( 'div' );
		tmp.innerHTML = html;
		return tmp.textContent || '';
	}

	/* -------------------------------------------------------------- */
	/* DOM building                                                   */
	/* -------------------------------------------------------------- */

	function buildButtonSet() {
		container = document.createElement( 'div' );
		container.className = 'sng-be-container';

		if ( config.setAsDefault && ! isDummy ) {
			container.className += ' sng-be-primary';
		}

		mainButton = document.createElement( 'button' );
		mainButton.type = 'button';
		mainButton.className = 'sng-be-button sng-be-main';

		mainLabel = document.createElement( 'span' );
		mainLabel.className = 'sng-be-main-label';
		mainButton.appendChild( mainLabel );

		mainButton.addEventListener( 'click', function() {
			run( currentAction );
		} );

		container.appendChild( mainButton );

		if ( config.actions.length > 1 ) {
			toggleButton = document.createElement( 'button' );
			toggleButton.type = 'button';
			toggleButton.className = 'sng-be-button sng-be-toggle';
			toggleButton.setAttribute( 'aria-haspopup', 'true' );
			toggleButton.setAttribute( 'aria-expanded', 'false' );
			toggleButton.setAttribute( 'aria-label', config.labels.menuTip );
			toggleButton.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

			toggleButton.addEventListener( 'click', function( event ) {
				event.stopPropagation();
				toggleMenu( ! menuOpen );
			} );

			/*
			 * The menu is appended to document.body (not to the header)
			 * and positioned with position:fixed. On top of that, when
			 * the browser supports the native Popover API, the menu is
			 * shown in the browser's TOP LAYER (showPopover), which by
			 * definition renders above every stacking context on the
			 * page — including the editor sidebar, overlays and modals.
			 * No z-index war can be lost from the top layer.
			 */
			menu = document.createElement( 'ul' );
			menu.className = 'sng-be-menu';
			menu.setAttribute( 'role', 'menu' );

			if ( supportsPopover ) {
				// 'manual' = no light-dismiss; our own listeners manage
				// closing, same as the non-popover fallback.
				menu.setAttribute( 'popover', 'manual' );
			}

			config.actions.forEach( function( actionData ) {
				var item = document.createElement( 'li' ),
					itemButton = document.createElement( 'button' );

				itemButton.type = 'button';
				itemButton.className = 'sng-be-menu-item';
				itemButton.setAttribute( 'role', 'menuitem' );
				itemButton.setAttribute( 'data-sng-value', actionData.id );

				if ( actionData.title ) {
					itemButton.title = htmlToText( actionData.title );
				}

				if ( ! actionData.enabled ) {
					itemButton.className += ' is-disabled';
					itemButton.setAttribute( 'aria-disabled', 'true' );
				} else {
					itemButton.addEventListener( 'click', function() {
						setAction( actionData );
						toggleMenu( false );
						run( actionData );
					} );
				}

				item.appendChild( itemButton );
				menu.appendChild( item );
			} );

			container.appendChild( toggleButton );
			document.body.appendChild( menu );

			menu.addEventListener( 'click', function( event ) {
				// Keep clicks inside the menu from closing it via the
				// document listener before the item handler runs.
				event.stopPropagation();
			} );

			document.addEventListener( 'click', function() {
				if ( menuOpen ) {
					toggleMenu( false );
				}
			} );

			// A fixed-position menu must follow (or close on) viewport
			// changes.
			window.addEventListener( 'resize', function() {
				if ( menuOpen ) {
					toggleMenu( false );
				}
			} );

			document.addEventListener(
				'scroll',
				function() {
					if ( menuOpen ) {
						toggleMenu( false );
					}
				},
				true
			);
		}

		updateLabels();
		updateDisabledState();
	}

	function positionMenu() {
		if ( ! menu || ! container ) {
			return;
		}

		var rect = container.getBoundingClientRect(),
			isRtl = 'rtl' === ( document.documentElement.getAttribute( 'dir' ) || '' );

		// Neutralize the browser's default popover placement (inset: 0,
		// margin: auto centers the element) before anchoring it.
		menu.style.margin = '0';
		menu.style.bottom = 'auto';

		menu.style.top = Math.round( rect.bottom + 8 ) + 'px';

		if ( isRtl ) {
			menu.style.left = Math.max( 8, Math.round( rect.left ) ) + 'px';
			menu.style.right = 'auto';
		} else {
			menu.style.right = Math.max( 8, Math.round( window.innerWidth - rect.right ) ) + 'px';
			menu.style.left = 'auto';
		}
	}

	function toggleMenu( open ) {
		menuOpen = open;

		if ( container ) {
			container.classList.toggle( 'sng-be-menu-open', open );
		}

		if ( menu ) {
			if ( open ) {
				positionMenu();
			}

			if ( supportsPopover ) {
				try {
					if ( open ) {
						menu.showPopover();
					} else {
						menu.hidePopover();
					}
				} catch ( e ) {
					// Already in the requested state; nothing to do.
				}
			}

			menu.classList.toggle( 'is-open', open );
		}

		if ( toggleButton ) {
			toggleButton.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}
	}

	function setAction( action ) {
		currentAction = action;

		if ( window.wpCookies ) {
			window.wpCookies.set( LAST_USED_COOKIE_NAME, action.id, 365 * 24 * 3600, config.cookiePath );
		}

		updateLabels();
	}

	function updateLabels() {
		if ( ! mainLabel || ! currentAction ) {
			return;
		}

		if ( isRunning || isNavigating ) {
			mainLabel.textContent = config.labels.saving;
		} else {
			mainLabel.innerHTML = generateLabel( currentAction.buttonLabelPattern );
		}

		mainButton.title = currentAction.title ? htmlToText( currentAction.title ) : '';

		if ( menu ) {
			config.actions.forEach( function( actionData ) {
				var itemButton = menu.querySelector( '[data-sng-value="' + actionData.id + '"]' );

				if ( itemButton ) {
					itemButton.innerHTML = generateLabel( actionData.buttonLabelPattern );
				}
			} );
		}
	}

	/**
	 * Replace mode: hides the WordPress Publish/Update button — but only
	 * once the post is published or scheduled. Until then it stays
	 * visible, because publishing a draft must keep going through the
	 * editor's own pre-publish flow (visibility, schedule, checks),
	 * which Save & Go deliberately does not bypass.
	 */
	function updateReplaceState() {
		if ( ! config.replaceDefault ) {
			return;
		}

		var editor = select( 'core/editor' ),
			hide = false;

		if ( editor && ! isDummy ) {
			hide = editor.isCurrentPostPublished() || editor.isCurrentPostScheduled();
		}

		document.body.classList.toggle( 'sng-be-replace', hide );
	}

	function updateDisabledState() {
		var editor = select( 'core/editor' ),
			saving = editor ? editor.isSavingPost() && ! editor.isAutosavingPost() : false,
			disabled = isDummy || isRunning || isNavigating || saving;

		if ( mainButton ) {
			mainButton.disabled = disabled;
		}

		if ( toggleButton ) {
			toggleButton.disabled = disabled;
		}
	}

	/* -------------------------------------------------------------- */
	/* Injection into the editor header                               */
	/* -------------------------------------------------------------- */

	function findHeader() {
		for ( var i = 0; i < HEADER_SELECTORS.length; i++ ) {
			var header = document.querySelector( HEADER_SELECTORS[ i ] );

			if ( header ) {
				return header;
			}
		}

		return null;
	}

	function findPublishButton( header ) {
		for ( var i = 0; i < PUBLISH_BUTTON_SELECTORS.length; i++ ) {
			var button = header.querySelector( PUBLISH_BUTTON_SELECTORS[ i ] );

			if ( button ) {
				return button;
			}
		}

		return null;
	}

	function inject() {
		if ( container && document.body.contains( container ) ) {
			return;
		}

		var header = findHeader();

		if ( ! header ) {
			return;
		}

		if ( ! container ) {
			buildButtonSet();
		}

		var publishButton = findPublishButton( header ),
			publishWrapper = publishButton;

		// Climb to the direct child of the header, so we insert as a sibling.
		while ( publishWrapper && publishWrapper.parentElement && publishWrapper.parentElement !== header ) {
			publishWrapper = publishWrapper.parentElement;
		}

		if ( publishWrapper && publishWrapper.parentElement === header ) {
			if ( config.setAsDefault ) {
				// Our button goes where the eye expects the primary
				// action: right after the original publish button.
				if ( publishWrapper.nextSibling ) {
					header.insertBefore( container, publishWrapper.nextSibling );
				} else {
					header.appendChild( container );
				}
				document.body.classList.add( 'sng-be-is-default' );
			} else {
				header.insertBefore( container, publishWrapper );
			}
		} else {
			header.appendChild( container );
		}
	}

	/* -------------------------------------------------------------- */
	/* Saving and navigating                                          */
	/* -------------------------------------------------------------- */

	/**
	 * Triggers the editor save and resolves with what actually
	 * happened: 'saved', 'failed' or 'blocked'.
	 *
	 * 'blocked' means the save never started. Field validation
	 * plugins (Meta Box, for example) wrap savePost() and cancel it
	 * when a field is invalid — the promise (if any) resolves, but no
	 * save request is ever made. Trusting the promise alone would
	 * either hang forever or read a stale "last save succeeded" flag,
	 * so the only signal treated as proof of a save is the editor
	 * actually entering (and leaving) its saving state.
	 */
	function attemptSave() {
		return new Promise( function( resolve ) {
			var sawSaveStart = false,
				settled = false,
				unsubscribeFn = null,
				timeoutId = null;

			function isSavingNow() {
				var editor = select( 'core/editor' );
				return editor.isSavingPost() && ! editor.isAutosavingPost();
			}

			function settle( result ) {
				if ( settled ) {
					return;
				}

				settled = true;

				if ( unsubscribeFn ) {
					unsubscribeFn();
				}

				if ( timeoutId ) {
					clearTimeout( timeoutId );
				}

				resolve( result );
			}

			function settleFromState() {
				settle( select( 'core/editor' ).didPostSaveRequestSucceed() ? 'saved' : 'failed' );
			}

			unsubscribeFn = subscribe( function() {
				if ( isSavingNow() ) {
					sawSaveStart = true;
					return;
				}

				if ( sawSaveStart ) {
					settleFromState();
				}
			} );

			var savePromise = dispatch( 'core/editor' ).savePost();

			if ( savePromise && 'function' === typeof savePromise.then ) {
				savePromise.then( function() {
					// Give the subscriber one tick to observe the state.
					setTimeout( function() {
						if ( ! sawSaveStart ) {
							settle( 'blocked' );
						} else if ( ! isSavingNow() ) {
							settleFromState();
						}
						// Otherwise a save is still running; the
						// subscriber settles when it finishes.
					}, 50 );
				}, function() {
					settle( 'failed' );
				} );
			} else {
				/*
				 * No promise back. The editor's own savePost() always
				 * returns one, so a plugin wrapped it and cancelled the
				 * save (Meta Box validation returns nothing when a
				 * field is invalid). Confirm no save is starting, then
				 * hand the page back.
				 */
				setTimeout( function() {
					if ( ! sawSaveStart && ! isSavingNow() ) {
						settle( 'blocked' );
					}
				}, 300 );
			}

			// Safety net: if nothing has started saving after a short
			// while, it is not coming.
			timeoutId = setTimeout( function() {
				if ( ! sawSaveStart && ! isSavingNow() ) {
					settle( 'blocked' );
				}
			}, 3000 );
		} );
	}

	function run( action ) {
		if ( ! action || ! action.enabled || isRunning || isNavigating || isDummy ) {
			return;
		}

		var editor = select( 'core/editor' );

		if ( editor.isSavingPost() && ! editor.isAutosavingPost() ) {
			return;
		}

		setAction( action );

		isRunning = true;
		updateLabels();
		updateDisabledState();

		var popupWindow = null,
			isPopupAction = config.viewPopup && action.id === config.viewPopup.actionId,
			isStayAction = config.stayActionId && action.id === config.stayActionId;

		// The popup must be opened during the user gesture, otherwise
		// the browser blocks it.
		if ( isPopupAction ) {
			popupWindow = window.open( '', config.viewPopup.windowName );

			if ( popupWindow ) {
				popupWindow.document.open();
				popupWindow.document.write( config.labels.popupWaitMessage );
				popupWindow.document.close();
			}
		}

		attemptSave().then( function( result ) {
			if ( 'saved' !== result ) {
				/*
				 * 'failed': the editor shows its own error notice.
				 * 'blocked': a validation plugin cancelled the save and
				 * shows its own message. Either way: hand the page back.
				 */
				throw new Error( result );
			}

			// The plain save option (replace mode): the save already
			// happened, nothing else to do — stay in the editor.
			if ( isStayAction ) {
				return null;
			}

			var postId = select( 'core/editor' ).getCurrentPostId();

			return apiFetch( {
				path: '/' + config.restPath,
				method: 'POST',
				data: {
					post_id: postId,
					action_id: action.id
				}
			} );
		} ).then( function( response ) {
			if ( response && 'popup' === response.behavior ) {
				if ( popupWindow && response.permalink ) {
					popupWindow.location = response.permalink;
				}

				isRunning = false;
				updateLabels();
				updateDisabledState();
				return;
			}

			if ( response && response.redirectUrl ) {
				isNavigating = true;
				window.location.href = response.redirectUrl;
				return;
			}

			isRunning = false;
			updateLabels();
			updateDisabledState();
		} ).catch( function() {
			// The save failed or the endpoint errored: the editor
			// already shows its own error notice. We just clean up.
			if ( popupWindow ) {
				popupWindow.close();
			}

			isRunning = false;
			updateLabels();
			updateDisabledState();
		} );
	}

	/* -------------------------------------------------------------- */
	/* Boot                                                           */
	/* -------------------------------------------------------------- */

	currentAction = getDefaultAction();

	if ( ! currentAction && config.actions.length === 1 ) {
		// Only one action, and it is not currently possible (ex: "Save
		// and Next" on the last post): show it, disabled.
		isDummy = true;
		currentAction = config.actions[ 0 ];
	}

	if ( ! currentAction ) {
		return;
	}

	wp.domReady( function() {
		inject();
		updateReplaceState();

		// Gutenberg re-renders its header: re-inject when needed.
		var observer = new MutationObserver( function() {
			inject();
		} );

		observer.observe( document.body, { childList: true, subtree: true } );

		// Keep labels and disabled state in sync with the editor.
		var lastVerb = statusVerb();

		subscribe( function() {
			updateDisabledState();
			updateReplaceState();

			var verb = statusVerb();

			if ( verb !== lastVerb ) {
				lastVerb = verb;
				updateLabels();
			}
		} );
	} );
} )( window.wp );
