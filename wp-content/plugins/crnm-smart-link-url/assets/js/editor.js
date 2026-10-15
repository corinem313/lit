( function () {
	if ( ! window.wp || ! wp.hooks || ! wp.compose || ! wp.element || ! wp.blockEditor ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var createPortal = wp.element.createPortal;
	var useLayoutEffect = wp.element.useLayoutEffect;
	var useRef = wp.element.useRef;
	var useState = wp.element.useState;
	var addFilter = wp.hooks.addFilter;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
	var LinkControl = wp.blockEditor.LinkControl;
	var __ = wp.i18n.__;

	var BLOCKS = [ 'core/cover', 'core/group', 'core/column' ];
	var KNOWN_DESTINATIONS = [ 'custom', 'post', 'media', 'lightbox' ];

	function resolveDestination( attributes ) {
		var stored = attributes && typeof attributes.smartLinkDestination === 'string'
			? attributes.smartLinkDestination
			: '';

		if ( KNOWN_DESTINATIONS.indexOf( stored ) !== -1 ) {
			return stored;
		}

		if ( attributes && attributes.smartLinkToCurrentPost ) {
			return 'post';
		}

		if ( attributes && attributes.smartLinkUrl ) {
			return 'custom';
		}

		return '';
	}

	function showsCustomUrl( name, attributes ) {
		if ( BLOCKS.indexOf( name ) === -1 ) {
			return false;
		}

		var destination = resolveDestination( attributes );

		return destination !== 'post' && destination !== 'media' && destination !== 'lightbox';
	}

	function normalizeSmartLinkUrl( url ) {
		url = ( url || '' ).trim();
		var hashWithScheme = url.match( /^https?:\/\/(#.+)$/i );

		if ( hashWithScheme ) {
			return hashWithScheme[ 1 ];
		}

		return url;
	}

	function applyUrl( url, blockName, setAttributes ) {
		url = normalizeSmartLinkUrl( url );

		var next = {
			smartLinkDestination: url ? 'custom' : '',
			smartLinkUrl: url,
			smartLinkToCurrentPost: false,
		};

		if ( blockName === 'core/cover' ) {
			next.smartLinkLightbox = url ? { enabled: false } : {};
		}

		setAttributes( next );
	}

	function clearLink( attributes, blockName, setAttributes ) {
		var destination = resolveDestination( attributes );

		if ( destination && destination !== 'custom' ) {
			setAttributes( {
				smartLinkUrl: '',
				smartLinkNewTab: false,
				smartLinkRel: '',
				smartLinkAriaLabel: '',
			} );
			return;
		}

		var next = {
			smartLinkDestination: '',
			smartLinkUrl: '',
			smartLinkNewTab: false,
			smartLinkRel: '',
			smartLinkAriaLabel: '',
			smartLinkToCurrentPost: false,
		};

		if ( blockName === 'core/cover' ) {
			next.smartLinkLightbox = {};
		}

		setAttributes( next );
	}

	function findCustomPanel() {
		return document.querySelector(
			'.interface-interface-skeleton__sidebar .forwp-smart-link-panel__custom'
		);
	}

	function SmartLinkUrlField( props ) {
		var attributes = props.attributes || {};
		var url = attributes.smartLinkUrl || '';
		var nodeRef = useRef( null );
		var mountedState = useState( false );
		var mounted = mountedState[ 0 ];
		var setMounted = mountedState[ 1 ];

		useLayoutEffect( function () {
			function attach() {
				var root = findCustomPanel();

				if ( ! root ) {
					return;
				}

				if ( ! nodeRef.current ) {
					var node = document.createElement( 'div' );
					node.className = 'crnm-smart-link-url';
					nodeRef.current = node;
				}

				if ( nodeRef.current.parentNode !== root ) {
					root.insertBefore( nodeRef.current, root.firstChild );
				}

				setMounted( true );
			}

			attach();

			var sidebar = document.querySelector( '.interface-interface-skeleton__sidebar' ) || document.body;
			var observer = new MutationObserver( attach );
			observer.observe( sidebar, { childList: true, subtree: true } );

			return function () {
				observer.disconnect();
				if ( nodeRef.current && nodeRef.current.parentNode ) {
					nodeRef.current.parentNode.removeChild( nodeRef.current );
				}
				setMounted( false );
			};
		}, [ props.clientId ] );

		if ( ! mounted || ! nodeRef.current ) {
			return null;
		}

		return createPortal(
			el( LinkControl, {
				searchInputPlaceholder: __( 'Search or type URL', 'crnm-smart-link-url' ),
				value: { url: url },
				settings: [],
				forceIsEditingLink: ! url,
				onChange: function ( nextValue ) {
					applyUrl( ( nextValue && nextValue.url ) || '', props.name, props.setAttributes );
				},
				onRemove: function () {
					clearLink( attributes, props.name, props.setAttributes );
				},
			} ),
			nodeRef.current
		);
	}

	var withSmartLinkUrl = createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			var showField = props.isSelected && showsCustomUrl( props.name, props.attributes );

			return el(
				Fragment,
				null,
				el( BlockEdit, props ),
				showField ? el( SmartLinkUrlField, props ) : null
			);
		};
	}, 'withCrnmSmartLinkUrl' );

	addFilter( 'editor.BlockEdit', 'crnm/smart-link-url', withSmartLinkUrl, 20 );
}() );
