( function () {
	if ( ! window.wp || ! wp.editSite || ! wp.plugins || ! wp.editor || ! wp.components ) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var __ = wp.i18n.__;
	var NumberControl = wp.components.__experimentalNumberControl;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginSidebar = wp.editor.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editor.PluginSidebarMoreMenuItem;
	var apiFetch = wp.apiFetch;

	function BreakpointSidebar() {
		var initial = crnmNbpSettings && crnmNbpSettings.breakpoint ? String( crnmNbpSettings.breakpoint ) : '600';
		var valueState = useState( initial );
		var value = valueState[ 0 ];
		var setValue = valueState[ 1 ];
		var statusState = useState( '' );
		var status = statusState[ 0 ];
		var setStatus = statusState[ 1 ];

		function save() {
			var next = value === '' || value === null ? 600 : parseInt( value, 10 );

			setStatus( 'saving' );

			apiFetch( {
				path: '/wp/v2/settings',
				method: 'POST',
				data: { crnm_nbp_breakpoint: next },
			} )
				.then( function ( result ) {
					var saved = result && result.crnm_nbp_breakpoint ? result.crnm_nbp_breakpoint : next;
					setValue( String( saved ) );
					setStatus( 'saved' );
				} )
				.catch( function () {
					setStatus( 'error' );
				} );
		}

		return el(
			wp.element.Fragment,
			null,
			el(
				PluginSidebarMoreMenuItem,
				{ target: 'crnm-nav-breakpoint' },
				__( 'Navigation breakpoint', 'crnm-nav-breakpoint' )
			),
			el(
				PluginSidebar,
				{
					name: 'crnm-nav-breakpoint',
					title: __( 'Navigation breakpoint', 'crnm-nav-breakpoint' ),
					icon: 'menu',
				},
				el(
					'div',
					{ className: 'crnm-nbp-sidebar' },
					el( NumberControl, {
						label: __( 'Mobile breakpoint (px)', 'crnm-nav-breakpoint' ),
						help: __(
							'Navigation blocks set to Overlay: Mobile use a hamburger below this width. Overlay: Always and Overlay: Off are unchanged.',
							'crnm-nav-breakpoint'
						),
						value: value,
						min: 320,
						max: 1600,
						step: 1,
						onChange: function ( nextValue ) {
							setValue( nextValue );
							setStatus( '' );
						},
					} ),
					el(
						Button,
						{
							variant: 'primary',
							onClick: save,
							disabled: status === 'saving',
						},
						status === 'saving'
							? __( 'Saving…', 'crnm-nav-breakpoint' )
							: __( 'Save', 'crnm-nav-breakpoint' )
					),
					status === 'saved'
						? el(
								'p',
								{ className: 'crnm-nbp-sidebar__status' },
								__( 'Saved. Reload the editor to preview the new width.', 'crnm-nav-breakpoint' )
						  )
						: null,
					status === 'error'
						? el(
								Notice,
								{ status: 'error', isDismissible: false },
								__( 'Could not save the breakpoint.', 'crnm-nav-breakpoint' )
						  )
						: null
				)
			)
		);
	}

	registerPlugin( 'crnm-nav-breakpoint', {
		render: BreakpointSidebar,
	} );
} )();
