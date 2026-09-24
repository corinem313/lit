/**
 * Smart Link host mode: navigate when the user clicks non-interactive areas.
 */
( function () {
	'use strict';

	function navigate( host ) {
		var url = host.getAttribute( 'data-forwp-smart-link-url' );

		if ( ! url ) {
			return;
		}

		var target = host.getAttribute( 'data-forwp-smart-link-target' ) || '_self';
		var rel = host.getAttribute( 'data-forwp-smart-link-rel' ) || '';

		if ( '_blank' === target ) {
			window.open( url, target, rel || 'noopener,noreferrer' );
			return;
		}

		window.location.assign( url );
	}

	function getEventElement( target ) {
		if ( ! target ) {
			return null;
		}

		return target.nodeType === 1 ? target : target.parentElement;
	}

	function shouldIgnoreClick( host, target ) {
		if ( ! target || ! host.contains( target ) ) {
			return true;
		}

		var el = getEventElement( target );

		if ( ! el || ! el.closest ) {
			return false;
		}

		return Boolean(
			el.closest(
				'a, button, input, select, textarea, label, summary, [role="button"], [role="link"]'
			)
		);
	}

	function initHost( host ) {
		if ( host.dataset.forwpSmartLinkBound === '1' ) {
			return;
		}

		host.dataset.forwpSmartLinkBound = '1';

		if ( ! host.style.cursor ) {
			host.style.cursor = 'pointer';
		}

		host.addEventListener( 'click', function ( event ) {
			if ( shouldIgnoreClick( host, event.target ) ) {
				return;
			}

			navigate( host );
		} );

		host.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' !== event.key && ' ' !== event.key ) {
				return;
			}

			if ( shouldIgnoreClick( host, event.target ) ) {
				return;
			}

			event.preventDefault();
			navigate( host );
		} );
	}

	function init() {
		var urlHosts = document.querySelectorAll( '[data-forwp-smart-link-url]' );
		var i;

		for ( i = 0; i < urlHosts.length; i++ ) {
			initHost( urlHosts[ i ] );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
