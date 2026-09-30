/*
 * The editor side of the booking form and customer panel blocks (T4.5).
 * Plain script, no build: the blocks render on the server, so the editor only
 * needs their settings and a preview. It reads the block names from its own
 * tag (data-blocks), so it defines no global and holds no brand name.
 */
( function ( wp, script ) {
	'use strict';

	var names = [];
	try {
		names = JSON.parse( script.getAttribute( 'data-blocks' ) || '[]' );
	} catch ( error ) {
		names = [];
	}
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var components = wp.components;

	function numberField( props, key, label ) {
		return el( components.TextControl, {
			key: key,
			type: 'number',
			min: 0,
			label: label,
			help: __( '0 lets the customer choose.', 'vaqtyar' ),
			value: String( props.attributes[ key ] || 0 ),
			onChange: function ( value ) {
				var next = {};
				next[ key ] = Math.max( 0, parseInt( value, 10 ) || 0 );
				props.setAttributes( next );
			},
		} );
	}

	function thanksField( props ) {
		return [
			el( components.TextControl, {
				key: 'thanks',
				type: 'url',
				label: __( 'Thank-you page', 'vaqtyar' ),
				help: __(
					'An address on this site to go to after a booking. Empty stays on the page.',
					'vaqtyar'
				),
				value: props.attributes.thanks || '',
				onChange: function ( value ) {
					props.setAttributes( { thanks: value } );
				},
			} ),
		];
	}

	function lookFields( props ) {
		return [
			el( components.SelectControl, {
				key: 'calendar',
				label: __( 'Calendar', 'vaqtyar' ),
				value: props.attributes.calendar,
				options: [
					{ value: '', label: __( 'Site setting', 'vaqtyar' ) },
					{ value: 'jalali', label: __( 'Jalali', 'vaqtyar' ) },
					{ value: 'gregorian', label: __( 'Gregorian', 'vaqtyar' ) },
				],
				onChange: function ( value ) {
					props.setAttributes( { calendar: value } );
				},
			} ),
			el( components.SelectControl, {
				key: 'digits',
				label: __( 'Digits', 'vaqtyar' ),
				value: props.attributes.digits,
				options: [
					{ value: '', label: __( 'Site setting', 'vaqtyar' ) },
					{ value: 'latin', label: '0123456789' },
					{ value: 'persian', label: '۰۱۲۳۴۵۶۷۸۹' },
				],
				onChange: function ( value ) {
					props.setAttributes( { digits: value } );
				},
			} ),
		];
	}

	names.forEach( function ( name ) {
		var isBooking = name.split( '/' ).pop() === 'booking';
		wp.blocks.registerBlockType( name, {
			edit: function ( props ) {
				var fields = isBooking
					? [
							numberField( props, 'service', __( 'Service ID', 'vaqtyar' ) ),
							numberField( props, 'variant', __( 'Duration ID', 'vaqtyar' ) ),
							numberField( props, 'location', __( 'Location ID', 'vaqtyar' ) ),
							numberField( props, 'staff', __( 'Staff ID', 'vaqtyar' ) ),
					  ]
							.concat( lookFields( props ) )
							.concat( thanksField( props ) )
					: lookFields( props );

				return el(
					'div',
					wp.blockEditor.useBlockProps(),
					el(
						wp.blockEditor.InspectorControls,
						null,
						el(
							components.PanelBody,
							{ title: __( 'Settings', 'vaqtyar' ) },
							fields
						)
					),
					el( wp.serverSideRender, {
						block: name,
						attributes: props.attributes,
					} )
				);
			},
			save: function () {
				return null;
			},
		} );
	} );
} )( window.wp, document.currentScript );
