/* Favr Events — block editor controls. Build-free (wp.* globals). */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var C = wp.components;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var ServerSideRender = wp.serverSideRender;
	var useSelect = wp.data.useSelect;

	wp.blocks.registerBlockType( 'favr-events/events', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var cats = useSelect( function ( select ) {
				return select( 'core' ).getEntityRecords( 'taxonomy', 'favr_event_cat', { per_page: 100, hide_empty: false, _fields: 'id,name,slug' } );
			}, [] );
			var catOptions = [ { label: __( 'All categories', 'favr-events' ), value: '' } ].concat(
				( cats || [] ).map( function ( t ) {
					return { label: t.name, value: t.slug };
				} )
			);

			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						C.PanelBody,
						{ title: __( 'Events', 'favr-events' ) },
						el( C.TextControl, { label: __( 'Heading', 'favr-events' ), value: a.title, onChange: function ( v ) { set( { title: v } ); } } ),
						el( C.SelectControl, {
							label: __( 'Layout', 'favr-events' ),
							value: a.view,
							options: [
								{ label: __( 'Default (from settings)', 'favr-events' ), value: '' },
								{ label: __( 'List', 'favr-events' ), value: 'list' },
								{ label: __( 'Month calendar', 'favr-events' ), value: 'month' },
								{ label: __( 'Compact list', 'favr-events' ), value: 'compact' }
							],
							onChange: function ( v ) { set( { view: v } ); }
						} ),
						el( C.SelectControl, { label: __( 'Category', 'favr-events' ), value: a.category, options: catOptions, onChange: function ( v ) { set( { category: v } ); } } ),
						el( C.RangeControl, { label: __( 'Events per page', 'favr-events' ), help: __( '0 uses the default from settings.', 'favr-events' ), min: 0, max: 50, value: a.limit, onChange: function ( v ) { set( { limit: v || 0 } ); } } ),
						el( C.ToggleControl, { label: __( 'Featured events only', 'favr-events' ), checked: !! a.featuredOnly, onChange: function ( v ) { set( { featuredOnly: v } ); } } ),
						el( C.ToggleControl, { label: __( 'Show view and category filters', 'favr-events' ), checked: !! a.showFilters, onChange: function ( v ) { set( { showFilters: v } ); } } )
					)
				),
				el( C.Disabled, null, el( ServerSideRender, { block: 'favr-events/events', attributes: a } ) )
			);
		},
		save: function () {
			return null;
		}
	} );

	wp.blocks.registerBlockType( 'favr-events/my-events', {
		edit: function () {
			return el(
				'div',
				useBlockProps(),
				el( C.Placeholder, {
					icon: 'calendar',
					label: __( 'My Events', 'favr-events' ),
					instructions: __( 'Members submit events for approval and manage their submissions here. Others see a login prompt. It’s also a tab in the Favr Members dashboard.', 'favr-events' )
				} )
			);
		},
		save: function () {
			return null;
		}
	} );
} )( window.wp );
