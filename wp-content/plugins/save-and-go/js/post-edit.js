/**
 * Save and Go — classic editor script.
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

( function( $ ) {
	'use strict';

	var SNG = window.SaveAndGo,
		$tmpDiv = $( '<div/>' );

	/**
	 * When the dom loads: initializes everything if we are on
	 * the correct page and the config exists.
	 */
	$( function() {
		var config = SNG.config,
			$form = $( '#post' );

		if ( config && $form.length ) {
			new SNG.PostEditForm( $form, config );
		}

		// If the page was reloaded after a "Save and View (new window)"
		// save, (re)load the popup with the post's frontend page.
		if ( config && config.viewPopup && config.viewPopup.reloadPopup && config.viewPopup.permalink ) {
			window.open( config.viewPopup.permalink, config.viewPopup.windowName );
		}
	} );

	/**
	 * Utility function to "unescape" strings. Takes a string
	 * with HTML encoded characters and returns the string
	 * with those characters replaced with their real value.
	 *
	 * @param {string} escaped
	 * @return {string}
	 */
	function htmlUnescape( escaped ) {
		return $tmpDiv.html( escaped ).text();
	}

	/**
	 * Class that represents the post edit form.
	 *
	 * @param {jQuery} $form  The post edit form.
	 * @param {Object} config The configuration object.
	 */
	SNG.PostEditForm = function( $form, config ) {
		if ( ! config.actions || config.actions.length === 0 ) {
			return;
		}

		this.$form = $form;
		this.config = config;
		this.action = null;

		/**
		 * The default action to show on the button. May be overwritten
		 * below in the special case where only one action is available
		 * and it is not enabled.
		 *
		 * @type {Object}
		 */
		var defaultAction = this.getDefaultAction();

		/*
		 * Special case: if there is only one action, and it is not
		 * enabled (ex: only "Save and Previous", but there is no
		 * previous post), we save in the configuration that the
		 * button set is "dummy" (i.e. we show it, but it won't do
		 * anything).
		 */
		if ( config.actions.length === 1 && ! config.actions[ 0 ].enabled ) {
			this.config.newButtonSetIsDummy = true;
			defaultAction = config.actions[ 0 ];
		}

		this.$actionInput = this.createActionInput();
		this.$originalPublishButton = this.getOriginalPublishButton();
		this.newPublishButtonSet = new SNG.PublishButtonSet( this );
		this.$spinner = this.$form.find( '#publishing-action .spinner' );

		this.setupForm();
		this.setupOriginalPublishButton();
		this.newPublishButtonSet.setAction( defaultAction );
		this.setupFormListeners();
		this.setupWordPressListeners();
		this.setupViewPopupListener();
		this.newPublishButtonSet.hideMenu();
		this.insertNewPublishButtonSet();
		this.setupSpinner();

		if ( this.config.newButtonSetIsDummy ) {
			this.newPublishButtonSet.disable( true );
		}
	};

	/**
	 * Cookie name used to store the last used action.
	 *
	 * @type {string}
	 */
	SNG.PostEditForm.LAST_USED_COOKIE_NAME = 'sng-last-used-action';

	SNG.PostEditForm.prototype = {
		/**
		 * Returns the publish (submit) button created by WordPress.
		 *
		 * @return {jQuery}
		 */
		getOriginalPublishButton: function() {
			return this.$form.find( '#publish' );
		},

		/**
		 * Adds classes to the spinner (shown when saving) and
		 * positions it near the original button.
		 */
		setupSpinner: function() {
			this.$spinner.addClass( 'sng-spinner' );
			this.$originalPublishButton.before( this.$spinner );
		},

		/**
		 * Returns true when Save & Go must fully replace the original
		 * publish button. Never the case for a "dummy" button set (the
		 * user must keep a way to save).
		 *
		 * @return {boolean}
		 */
		isReplacingOriginal: function() {
			return !! ( this.config.replaceDefault && ! this.config.newButtonSetIsDummy );
		},

		/**
		 * Inserts the new publish button elements after or before
		 * the original publish button, depending on whether the
		 * new button should be displayed as the default one or not.
		 */
		insertNewPublishButtonSet: function() {
			var $container = this.newPublishButtonSet.$container,
				$separator = $( '<div class="sng-separator"></div>' );

			if ( this.config.setAsDefault ) {
				this.$originalPublishButton.after( $container );

				// No separator in replace mode: the original button is
				// hidden, so no gap should remain.
				if ( ! this.isReplacingOriginal() ) {
					this.$originalPublishButton.after( $separator );
				}
			} else {
				this.$originalPublishButton
					.before( $container )
					.before( $separator );
			}
		},

		/**
		 * Creates and returns the hidden field that will contain the
		 * action chosen by the user.
		 *
		 * @return {jQuery}
		 */
		createActionInput: function() {
			return $( '<input type="hidden" />' ).attr( 'name', this.config.httpParamAction );
		},

		/**
		 * Initializes the form. Only prepends the hidden action input.
		 */
		setupForm: function() {
			this.$form.prepend( this.$actionInput );
		},

		/**
		 * Updates the look of the original publish button, depending
		 * on whether the new publish button must be displayed as the
		 * default or not.
		 */
		setupOriginalPublishButton: function() {
			if ( this.config.setAsDefault && ! this.config.newButtonSetIsDummy ) {
				this.$originalPublishButton
					.removeClass( 'button-primary' )
					.removeAttr( 'accesskey' );
			}

			/*
			 * Replace mode: the original button is hidden but stays in
			 * the DOM — Save & Go submits the form by clicking it, and
			 * its label ("Publish"/"Update") feeds our button labels.
			 */
			if ( this.isReplacingOriginal() ) {
				this.$originalPublishButton.addClass( 'sng-replaced-original' );
			}
		},

		/**
		 * Saves in the form field the action selected by the user.
		 * Called just before form submit. Also saves the action in
		 * the cookie.
		 *
		 * @param {Object} newAction
		 */
		setAction: function( newAction ) {
			this.action = newAction;
			window.wpCookies.set( SNG.PostEditForm.LAST_USED_COOKIE_NAME, newAction.id, 365 * 24 * 3600, this.config.cookiePath );

			// The plain save option (replace mode) sends no action, so
			// WordPress does its default: save and stay on this page.
			if ( this.config.stayActionId && newAction.id === this.config.stayActionId ) {
				this.$actionInput.val( '' );
			} else {
				this.$actionInput.val( newAction.id );
			}
		},

		/**
		 * Returns the action that was set by setAction.
		 *
		 * @return {Object} The action.
		 */
		getAction: function() {
			return this.action;
		},

		/**
		 * Submits the form through the use of the action button.
		 */
		submit: function() {
			this.setAction( this.newPublishButtonSet.getAction() );

			var customSubmitEvent = $.Event( 'save-and-go:submit' );

			/*
			 * The 'save-and-go:submit' event is triggered on the $form.
			 * A listener can prevent the form submission by calling
			 * preventDefault() on the event.
			 */
			this.$form.trigger( customSubmitEvent, this );

			if ( customSubmitEvent.isDefaultPrevented() ) {
				return;
			}

			this.$form.data( 'sng-button-submitted', true );

			// We trigger a click on the original button, so its name
			// is correctly sent in the HTTP request.
			this.getOriginalPublishButton().click();
		},

		/**
		 * Sets up listeners on the form submit (no matter which
		 * button/trigger submitted it).
		 */
		setupFormListeners: function() {
			var self = this;

			this.$form.on( 'submit.sng-post-edit', function( event ) {
				var isSngSubmitted = self.$form.data( 'sng-button-submitted' );

				self.$form.removeData( 'sng-button-submitted' );

				if ( event.isDefaultPrevented() ) {
					return;
				}

				if ( isSngSubmitted === true ) {
					// When the form is effectively submitted, we move the
					// spinner just before the new button container.
					self.newPublishButtonSet.$container.before( self.$spinner );
				}
			} );
		},

		/**
		 * When the "Save and View (new window)" action is used, opens
		 * the popup right away (so it is not blocked by the browser)
		 * with a "please wait" message. After the save, the reloaded
		 * page points this popup to the post's page.
		 */
		setupViewPopupListener: function() {
			var config = this.config;

			if ( ! config.viewPopup ) {
				return;
			}

			this.$form.on( 'save-and-go:submit', function( event, postEditForm ) {
				var action = postEditForm.getAction(),
					popupWindow;

				if ( ! action || action.id !== config.viewPopup.actionId ) {
					return;
				}

				popupWindow = window.open( '', config.viewPopup.windowName );

				if ( popupWindow ) {
					popupWindow.document.open();
					popupWindow.document.write( config.viewPopup.waitMessage );
					popupWindow.document.close();
				}
			} );
		},

		/**
		 * Reads and returns from the config the default action. If the
		 * default action is '_last', reads it from the cookie.
		 *
		 * @return {Object} The default action.
		 */
		getDefaultAction: function() {
			var defaultActionId = this.config.defaultActionId,
				defaultAction = null,
				fallbackAction;

			/*
			 * We define the fallback action as the first enabled action.
			 * If the normal way to determine the action gives an invalid
			 * one, this one will be returned.
			 */
			$.each( this.config.actions, function( i, action ) {
				if ( action.enabled ) {
					fallbackAction = action;
					return false; // Break the $.each().
				}
			} );

			if ( ! defaultActionId ) {
				return fallbackAction;
			}

			// If it is '_last', we get it from the cookie.
			if ( this.config.actionLastId === defaultActionId ) {
				var cookieVal = window.wpCookies.get( SNG.PostEditForm.LAST_USED_COOKIE_NAME );

				if ( ! cookieVal ) {
					return fallbackAction;
				}

				defaultActionId = cookieVal;
			}

			defaultAction = this.getActionFromId( defaultActionId );

			if ( ! defaultAction || ! defaultAction.enabled ) {
				defaultAction = fallbackAction;
			}

			return defaultAction;
		},

		/**
		 * Returns an action's data from the config from its id.
		 *
		 * @param {string} id
		 * @return {Object} The action information.
		 */
		getActionFromId: function( id ) {
			var foundAction = null;

			$.each( this.config.actions, function( i, action ) {
				if ( action.id === id ) {
					foundAction = action;
					return false; // Break the $.each().
				}
			} );

			return foundAction;
		},

		/**
		 * WordPress updates the original publish button or its label on
		 * some events. We subscribe to those same events so we can update
		 * our button and its label.
		 */
		setupWordPressListeners: function() {
			var self = this;

			// When the form is submitted, we disable the button. Some
			// submit buttons must not disable the button (like the
			// preview button).
			// @see wordpress/wp-admin/js/post.js
			this.$form.on( 'click.sng-post-edit', ':submit, a.submitdelete, #post-preview', function() {
				var $button = $( this );

				if ( $button.hasClass( 'disabled' ) ) {
					return;
				}

				if ( $button.hasClass( 'submitdelete' ) || $button.is( '#post-preview' ) ) {
					return;
				}

				self.$form.one( 'submit.sng-post-edit', function( event ) {
					if ( event.isDefaultPrevented() ) {
						return;
					}

					self.newPublishButtonSet.disable( true );
				} );
			} );

			// Disable button while auto saving.
			// @see wordpress/wp-admin/js/post.js
			$( document ).on( 'autosave-disable-buttons.edit-post', function() {
				self.newPublishButtonSet.disable( true );
			} ).on( 'autosave-enable-buttons.edit-post', function() {
				if ( ! window.wp.heartbeat || ! window.wp.heartbeat.hasConnectionError() ) {
					if ( ! self.config.newButtonSetIsDummy ) {
						self.newPublishButtonSet.disable( false );
					}
				}
			} );

			// All the events that trigger an updateText call in post.js.
			// We update the labels here.
			this.$form.on(
				'click',
				'#post-visibility-select .cancel-post-visibility,' +
					'#post-visibility-select .save-post-visibility,' +
					'#timestampdiv .cancel-timestamp,' +
					'#timestampdiv .save-timestamp,' +
					'#post-status-select .save-post-status,' +
					'#post-status-select .cancel-post-status',
				function() {
					self.newPublishButtonSet.updateLabels();
				}
			);
		}
	};

	/**
	 * Class that represents the new publish button that this plugin
	 * creates.
	 *
	 * @param {SaveAndGo.PostEditForm} postEditForm The PostEditForm where we add the button.
	 */
	SNG.PublishButtonSet = function( postEditForm ) {
		this.postEditForm = postEditForm;
		this.config = this.postEditForm.config;
		this.action = null;

		this.$mainButton = this.createMainButton();
		this.$dropdownButton = this.createDropdownButton();
		this.$dropdownMenu = this.createDropdownMenu();
		this.$container = this.createContainer();

		this.setupDocumentClickListener();
		this.setupMainButtonListeners();
		this.setupDropdownButtonListeners();
		this.setupDropdownMenuListeners();
	};

	SNG.PublishButtonSet.prototype = {

		/**
		 * Creates and returns the main button (part of the new publish
		 * button set).
		 *
		 * @return {jQuery} The main button.
		 */
		createMainButton: function() {
			var $mainButton = $( '<button type="button"/>' );

			$mainButton.attr( 'class', 'button button-large sng-main-button' );

			if ( this.config.setAsDefault && ! this.config.newButtonSetIsDummy ) {
				$mainButton.addClass( 'button-primary' );
			} else {
				$mainButton.removeAttr( 'accesskey' );
			}

			return $mainButton;
		},

		/**
		 * Creates the dropdown button that opens the dropdown menu.
		 * Shows a small chevron.
		 *
		 * @return {jQuery}
		 */
		createDropdownButton: function() {
			var $dropdownButton = $( '<button type="button" aria-haspopup="true" aria-expanded="false"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>' );

			$dropdownButton.attr( 'class', this.$mainButton.attr( 'class' ) );
			$dropdownButton
				.removeClass( 'sng-main-button' )
				.addClass( 'sng-dropdown-button' );

			return $dropdownButton;
		},

		/**
		 * Creates and returns the menu element that is used as the
		 * dropdown and that contains all the enabled actions.
		 *
		 * @return {jQuery}
		 */
		createDropdownMenu: function() {
			var $dropdownMenu = $( '<ul class="sng-dropdown-menu" role="menu"></ul>' ),
				self = this;

			$.each( this.config.actions, function( i, actionData ) {
				var $item = $( '<li role="menuitem"></li>' )
					.attr( 'data-sng-value', actionData.id )
					.html( self.generateButtonLabel( actionData.buttonLabelPattern ) );

				if ( actionData.title ) {
					$item.attr( 'title', htmlUnescape( actionData.title ) );
				}

				if ( actionData.enabled ) {
					$item.data( 'sngActionData', actionData );
				} else {
					$item.addClass( 'disabled' );
				}

				$dropdownMenu.append( $item );
			} );

			return $dropdownMenu;
		},

		/**
		 * Creates and returns the container element that encompasses
		 * the main button, the dropdown button and the dropdown menu.
		 *
		 * @return {jQuery}
		 */
		createContainer: function() {
			var $container = $( '<span class="sng-container"></span>' );

			$container.append( this.$mainButton );

			if ( this.config.actions.length > 1 ) {
				$container
					.addClass( 'sng-with-dropdown' )
					.append( this.$dropdownButton )
					.append( this.$dropdownMenu );
			}

			if ( this.config.setAsDefault ) {
				$container.addClass( 'sng-set-as-default' );
			}

			return $container;
		},

		/**
		 * Sets up a click listener on the document. Used to close the
		 * dropdown menu when we click outside.
		 */
		setupDocumentClickListener: function() {
			var self = this;

			$( document ).on( 'click.sng-post-edit', function() {
				if ( self.menuShown() ) {
					self.hideMenu();
				}
			} );
		},

		/**
		 * Sets up a click listener on the main button: saves the current
		 * action in the form and submits it.
		 */
		setupMainButtonListeners: function() {
			var self = this;

			this.$mainButton.on( 'click', function() {
				if ( $( this ).hasClass( 'disabled' ) || self.config.newButtonSetIsDummy ) {
					return;
				}

				self.postEditForm.submit();
			} );
		},

		/**
		 * Sets up a click listener on the dropdown button: opens the
		 * dropdown menu.
		 */
		setupDropdownButtonListeners: function() {
			var self = this;

			this.$dropdownButton.on( 'click', function( event ) {
				if ( ! self.menuShown() ) {
					self.showMenu();
					event.stopPropagation();
				}
			} );
		},

		/**
		 * Sets up click listeners on elements of the dropdown menu:
		 * sets the action and triggers a click on the main button.
		 */
		setupDropdownMenuListeners: function() {
			var self = this;

			this.$dropdownMenu.on( 'click', 'li', function() {
				if ( $( this ).hasClass( 'disabled' ) || self.config.newButtonSetIsDummy ) {
					return;
				}

				self.setAction( $( this ).data( 'sngActionData' ) );
				self.$mainButton.click();
			} );
		},

		/**
		 * Returns true if the dropdown menu is shown.
		 *
		 * @return {boolean}
		 */
		menuShown: function() {
			return this.$container.hasClass( 'sng-dropdown-menu-shown' );
		},

		/**
		 * Shows the dropdown menu.
		 */
		showMenu: function() {
			this.$container.addClass( 'sng-dropdown-menu-shown' );
			this.$dropdownButton.attr( 'aria-expanded', 'true' );
		},

		/**
		 * Hides the dropdown menu.
		 */
		hideMenu: function() {
			this.$container.removeClass( 'sng-dropdown-menu-shown' );
			this.$dropdownButton.attr( 'aria-expanded', 'false' );
		},

		/**
		 * Sets the current active action (the one shown on the main
		 * button).
		 *
		 * @param {Object} action The action.
		 */
		setAction: function( action ) {
			this.action = action;
			this.updateLabels();
		},

		/**
		 * Returns the action.
		 *
		 * @return {Object} The action.
		 */
		getAction: function() {
			return this.action;
		},

		/**
		 * Updates the label of the main button and the dropdown menu
		 * elements based on the currently set action and the value of
		 * the original button. Also updates the title attribute.
		 */
		updateLabels: function() {
			var self = this;

			this.$mainButton.html( this.generateButtonLabel( this.action.buttonLabelPattern ) );
			this.$mainButton.attr( 'title', htmlUnescape( this.action.title ? this.action.title : '' ) );

			$.each( this.config.actions, function( i, actionData ) {
				var $li = self.$dropdownMenu.find( '[data-sng-value="' + actionData.id + '"]' );
				$li.html( self.generateButtonLabel( actionData.buttonLabelPattern ) );
			} );
		},

		/**
		 * Takes a string pattern and replaces '%s' with the label of
		 * the original publish button.
		 *
		 * @param {string} pattern
		 * @return {string}
		 */
		generateButtonLabel: function( pattern ) {
			return pattern.replace( '%s', this.postEditForm.$originalPublishButton.val() );
		},

		/**
		 * Shows the button set as disabled (or not) and hides the
		 * dropdown menu.
		 *
		 * @param {boolean} disabled True to disable, false to enable.
		 */
		disable: function( disabled ) {
			this.$mainButton.toggleClass( 'disabled', disabled );
			this.$dropdownButton.toggleClass( 'disabled', disabled );
		}
	};
} )( jQuery );
