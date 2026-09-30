// @vitest-environment jsdom
import { render } from 'preact';
import { act } from 'preact/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { DateInput } from './DateInput';

describe( 'the widget date input', () => {
	let container: HTMLElement;
	let values: string[];

	beforeEach( () => {
		values = [];
		container = document.createElement( 'div' );
		document.body.append( container );
	} );

	afterEach( () => {
		render( null, container );
		container.remove();
	} );

	const show = ( calendar: 'jalali' | 'gregorian', value = '' ) =>
		act( () =>
			render(
				<DateInput
					id="d"
					value={ value }
					calendar={ calendar }
					digits="persian"
					onChange={ ( next ) => values.push( next ) }
				/>,
				container
			)
		);
	const type = ( text: string ) =>
		act( () => {
			const input = container.querySelector(
				'input'
			) as HTMLInputElement;
			input.value = text;
			input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );

	it( 'reads a typed Jalali date in any digits as the Gregorian one', async () => {
		show( 'jalali' );

		type( '۱۴۰۵/۰۷/۰۳' );

		expect( values ).toEqual( [ '2026-09-25' ] );
	} );

	it( 'reports no date, and says so, while the text is not one', async () => {
		show( 'jalali' );

		type( '1405/13/40' );

		expect( values ).toEqual( [ '' ] );
		expect( container.querySelector( '[role="alert"]' )?.textContent ).toBe(
			'Enter a date like 1405/07/03.'
		);
	} );

	it( 'shows the date it understood, in the chosen digits', () => {
		show( 'jalali', '2026-09-25' );

		expect(
			container.querySelector( '.vqy-widget__hint' )?.textContent
		).toBe( '۱۴۰۵/۰۷/۰۳' );
	} );

	it( 'is the browser’s date input in the Gregorian calendar', () => {
		show( 'gregorian', '2026-09-25' );

		const input = container.querySelector( 'input' ) as HTMLInputElement;
		expect( input.type ).toBe( 'date' );
		expect( input.value ).toBe( '2026-09-25' );
	} );
} );
