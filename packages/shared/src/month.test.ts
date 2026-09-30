import { describe, expect, it } from 'vitest';

import { cursorOf, monthGrid, shiftMonth } from './month';

describe( 'monthGrid', () => {
	it( 'lays out Mehr 1405 from its Gregorian start, Saturday first', () => {
		const grid = monthGrid( 'jalali', { year: 1405, month: 7 } );

		expect( grid.start ).toBe( '2026-09-23' );
		expect( grid.days ).toBe( 30 );
		// 1405/07/01 is a Wednesday: Saturday to Tuesday are blank.
		expect( grid.blanks ).toBe( 4 );
		expect( grid.dates[ 0 ] ).toBe( '2026-09-23' );
		expect( grid.dates.at( -1 ) ).toBe( '2026-10-22' );
	} );

	it.each( [
		[ 1, 31 ],
		[ 6, 31 ],
		[ 7, 30 ],
		[ 11, 30 ],
	] )( 'gives Jalali month %d %d days', ( month, days ) => {
		expect( monthGrid( 'jalali', { year: 1405, month } ).days ).toBe(
			days
		);
	} );

	it( 'gives Esfand 29 days in a common year and 30 in a leap year', () => {
		expect( monthGrid( 'jalali', { year: 1404, month: 12 } ).days ).toBe(
			29
		);
		expect( monthGrid( 'jalali', { year: 1403, month: 12 } ).days ).toBe(
			30
		);
	} );

	it( 'lays out a Gregorian month', () => {
		const grid = monthGrid( 'gregorian', { year: 2028, month: 2 } );

		expect( grid.start ).toBe( '2028-02-01' );
		expect( grid.days ).toBe( 29 );
		// 2028-02-01 is a Tuesday.
		expect( grid.blanks ).toBe( 3 );
	} );
} );

describe( 'shiftMonth and cursorOf', () => {
	it( 'moves across a year boundary both ways', () => {
		expect( shiftMonth( { year: 1405, month: 12 }, 1 ) ).toEqual( {
			year: 1406,
			month: 1,
		} );
		expect( shiftMonth( { year: 1405, month: 1 }, -1 ) ).toEqual( {
			year: 1404,
			month: 12,
		} );
	} );

	it( 'finds the month a date falls in, in either calendar', () => {
		expect( cursorOf( 'jalali', '2026-09-23' ) ).toEqual( {
			year: 1405,
			month: 7,
		} );
		expect( cursorOf( 'gregorian', '2026-09-23' ) ).toEqual( {
			year: 2026,
			month: 9,
		} );
	} );
} );
