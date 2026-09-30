import { describe, expect, it } from 'vitest';

import {
	formatDate,
	fromJalali,
	latinDigits,
	parseLocalDate,
	parseTypedDate,
	toJalali,
} from './jalali';

describe( 'jalali', () => {
	it( 'converts both ways, around Nowruz and in a leap year', () => {
		const pairs = [
			[
				{ year: 2026, month: 9, day: 25 },
				{ year: 1405, month: 7, day: 3 },
			],
			[
				{ year: 2026, month: 3, day: 21 },
				{ year: 1405, month: 1, day: 1 },
			],
			[
				{ year: 2026, month: 3, day: 20 },
				{ year: 1404, month: 12, day: 29 },
			],
			// 1403 is a leap year: Esfand has 30 days.
			[
				{ year: 2025, month: 3, day: 20 },
				{ year: 1403, month: 12, day: 30 },
			],
		] as const;

		for ( const [ gregorian, jalali ] of pairs ) {
			expect( toJalali( gregorian ) ).toEqual( jalali );
			expect( fromJalali( jalali ) ).toEqual( gregorian );
		}
	} );

	it( 'formats a date as the PHP DateFormatter does', () => {
		expect( formatDate( '2026-09-25', 'jalali', 'persian' ) ).toBe(
			'۱۴۰۵/۰۷/۰۳'
		);
		expect( formatDate( '2026-09-25', 'jalali', 'latin' ) ).toBe(
			'1405/07/03'
		);
		expect( formatDate( '2026-09-25', 'gregorian', 'latin' ) ).toBe(
			'2026-09-25'
		);
		// "/" keeps Persian digits in one bidi run; "-" would show the date reversed.
		expect( formatDate( '2026-09-25', 'gregorian', 'persian' ) ).toBe(
			'۲۰۲۶/۰۹/۲۵'
		);
	} );

	it.each( [ '2026-9-25', '2026-02-30', '25/09/2026', '' ] )(
		'rejects %j',
		( value ) => {
			expect( () => parseLocalDate( value ) ).toThrow( RangeError );
		}
	);
} );

describe( 'typed dates', () => {
	it( 'reads Latin, Persian and Arabic digits with any separator', () => {
		expect( latinDigits( '۱۴۰۵/٠٧/03' ) ).toBe( '1405/07/03' );
		for ( const text of [
			'1405/07/03',
			'1405-7-3',
			'۱۴۰۵/۰۷/۰۳',
			' 1405.07.03 ',
		] ) {
			expect( parseTypedDate( text, 'jalali' ) ).toBe( '2026-09-25' );
		}
		expect( parseTypedDate( '2026-09-25', 'gregorian' ) ).toBe(
			'2026-09-25'
		);
	} );

	it( 'rejects what is not a date of the calendar', () => {
		// 1405 is a common year: Esfand has 29 days, and jalaali-js would roll 30 over.
		for ( const text of [
			'',
			'1405/13/01',
			'1405/12/30',
			'1405/00/10',
			'14/07/03',
			'abc',
		] ) {
			expect( parseTypedDate( text, 'jalali' ) ).toBeNull();
		}
		expect( parseTypedDate( '2026-02-30', 'gregorian' ) ).toBeNull();
		// A leap year has the 30th.
		expect( parseTypedDate( '1403/12/30', 'jalali' ) ).toBe( '2025-03-20' );
	} );
} );
