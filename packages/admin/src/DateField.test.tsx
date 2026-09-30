// @vitest-environment jsdom
import type { DisplaySettings } from '@vaqtyar/shared';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { DateField, DateTimeField } from './DateField';
import { DEFAULT_DISPLAY, DisplayContext, whenIn } from './display';

(
	globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }
 ).IS_REACT_ACT_ENVIRONMENT = true;

describe( 'the date fields', () => {
	let container: HTMLElement;
	let root: Root;
	let changes: string[];

	beforeEach( () => {
		changes = [];
		container = document.createElement( 'div' );
		document.body.append( container );
		root = createRoot( container );
	} );

	afterEach( () => {
		act( () => root.unmount() );
		container.remove();
	} );

	const show = (
		value: string,
		display: DisplaySettings = DEFAULT_DISPLAY,
		min?: string
	) =>
		act( () =>
			root.render(
				<DisplayContext.Provider value={ display }>
					<DateField
						label="Day"
						value={ value }
						min={ min }
						onChange={ ( next ) => changes.push( next ) }
					/>
				</DisplayContext.Provider>
			)
		);
	const input = () =>
		container.querySelector< HTMLInputElement >(
			'input'
		) as HTMLInputElement;
	const typeInto = async ( value: string ) => {
		await act( async () => {
			const setter = Object.getOwnPropertyDescriptor(
				HTMLInputElement.prototype,
				'value'
			)?.set;
			setter?.call( input(), value );
			input().dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );
	};
	const click = async ( element: Element | undefined ) => {
		await act( async () => {
			element?.dispatchEvent(
				new MouseEvent( 'click', { bubbles: true } )
			);
		} );
	};
	const buttonNamed = ( name: string ) =>
		[ ...container.querySelectorAll( 'button' ) ].find( ( button ) =>
			button.textContent?.includes( name )
		);

	it( 'shows a Jalali date in the calendar the owner chose', async () => {
		await show( '2026-09-25' );
		expect( input().value ).toBe( '1405/07/03' );

		await show( '2026-09-25', { ...DEFAULT_DISPLAY, digits: 'persian' } );
		expect( input().value ).toBe( '۱۴۰۵/۰۷/۰۳' );
	} );

	it( 'reads a typed Jalali date, in any digits, as the Gregorian one the API takes', async () => {
		await show( '' );

		await typeInto( '۱۴۰۵/۰۷/۰۳' );

		expect( changes ).toEqual( [ '2026-09-25' ] );
	} );

	it( 'waits for a whole date and clears on empty text', async () => {
		await show( '2026-09-25' );

		await typeInto( '1405/07/' );
		await typeInto( '1405/12/30' );
		expect( changes ).toEqual( [] );

		await typeInto( '' );
		expect( changes ).toEqual( [ '' ] );
	} );

	it( 'does not take a date before the earliest allowed', async () => {
		await show( '', DEFAULT_DISPLAY, '2026-09-25' );

		await typeInto( '1405/07/02' );
		expect( changes ).toEqual( [] );
		await typeInto( '1405/07/03' );
		expect( changes ).toEqual( [ '2026-09-25' ] );
	} );

	it( 'picks a day from the month grid of the value', async () => {
		await show( '2026-09-25' );

		await click( buttonNamed( 'Calendar' ) );

		expect( container.textContent ).toContain( 'Mehr 1405' );
		const day = [
			...container.querySelectorAll< HTMLButtonElement >(
				'.vqy-date__day'
			),
		];
		// Mehr has 30 days and 3 is the one the value is on.
		expect( day ).toHaveLength( 30 );
		expect( day[ 2 ]?.getAttribute( 'aria-pressed' ) ).toBe( 'true' );

		await click( day[ 9 ] );

		expect( changes ).toEqual( [ '2026-10-02' ] );
		expect( container.querySelector( '.vqy-date__popup' ) ).toBeNull();
	} );

	it( 'moves between months', async () => {
		await show( '2026-09-25' );
		await click( buttonNamed( 'Calendar' ) );

		await click( buttonNamed( 'Next month' ) );
		expect( container.textContent ).toContain( 'Aban 1405' );
		await click( buttonNamed( 'Previous month' ) );
		await click( buttonNamed( 'Previous month' ) );
		expect( container.textContent ).toContain( 'Shahrivar 1405' );
	} );

	it( 'is the browser’s own date input in the Gregorian calendar', async () => {
		await show( '2026-09-25', {
			...DEFAULT_DISPLAY,
			calendar: 'gregorian',
		} );

		expect( input().type ).toBe( 'date' );
		expect( input().value ).toBe( '2026-09-25' );
		expect( container.querySelector( '.vqy-date' ) ).toBeNull();
	} );

	it( 'joins a date and a time into the local date-time the API takes', async () => {
		const values: string[] = [];
		await act( () =>
			root.render(
				<DateTimeField
					label="Valid from"
					value=""
					onChange={ ( next ) => values.push( next ) }
				/>
			)
		);

		await typeInto( '1406/01/01' );

		expect( values ).toEqual( [ '2027-03-21T00:00' ] );
	} );
} );

describe( 'whenIn', () => {
	it( 'writes the date and the clock time in the chosen calendar and digits', () => {
		const iso = '2026-09-25T10:30:00+03:30';

		expect( whenIn( iso, DEFAULT_DISPLAY ) ).toBe( '1405/07/03 10:30' );
		expect( whenIn( iso, { ...DEFAULT_DISPLAY, digits: 'persian' } ) ).toBe(
			'۱۴۰۵/۰۷/۰۳ ۱۰:۳۰'
		);
		expect(
			whenIn( iso, { ...DEFAULT_DISPLAY, calendar: 'gregorian' } )
		).toBe( '2026-09-25 10:30' );
	} );
} );
