import { ApiClient, type ServiceStaff } from '@vaqtyar/shared';
import { describe, expect, it } from 'vitest';

import { normalize } from './CatalogList';
import { fetchAll, screenOf } from './crud';
import {
	fitStaffTerms,
	makeDefault,
	newVariant,
	removeVariant,
	setStaffTerms,
	toggleStaff,
} from './service-draft';
import { yearFrom } from './TimeOff';
import { copyToEveryDay } from './WeeklySchedule';

describe( 'screenOf', () => {
	it.each( [
		[ '/staff', 'list' ],
		[ '/staff/new', 'new' ],
		[ '/staff/7', 7 ],
		[ '/staff/07', null ],
		[ '/staff/7/x', null ],
		[ '/staffing', null ],
	] )( '%s is %o', ( route, screen ) => {
		expect( screenOf( route, '/staff' ) ).toBe( screen );
	} );
} );

describe( 'fetchAll', () => {
	it( 'reads page after page until the last', async () => {
		const pages: string[] = [];
		const api = new ApiClient( {
			baseUrl: 'https://example.test/wp-json/x/v1/',
			fetch: async ( url ) => {
				const page = Number(
					new URL( String( url ) ).searchParams.get( 'page' )
				);
				pages.push( String( page ) );

				return new Response( JSON.stringify( [ { id: page } ] ), {
					headers: { 'X-WP-Total': '2', 'X-WP-TotalPages': '2' },
				} );
			},
		} );

		expect( await fetchAll( api, '/staff' ) ).toEqual( [
			{ id: 1 },
			{ id: 2 },
		] );
		expect( pages ).toEqual( [ '1', '2' ] );
	} );
} );

describe( 'a service draft', () => {
	it( 'keeps exactly one default variant', () => {
		const variants = [ newVariant( true ), newVariant( false ) ];

		expect(
			makeDefault( variants, 1 ).map( ( v ) => v.is_default )
		).toEqual( [ false, true ] );
		expect(
			removeVariant( variants, 0 ).map( ( v ) => v.is_default )
		).toEqual( [ true ] );
		// The last one stays.
		expect( removeVariant( [ newVariant( true ) ], 0 ) ).toHaveLength( 1 );
	} );

	it( 'assigns a staff member once and takes every row of theirs away', () => {
		const perVariant: ServiceStaff = {
			staff_id: 3,
			variant_id: 9,
			price: null,
			duration_min: 45,
		};

		const on = toggleStaff( [], 3, true );
		expect( on ).toEqual( [
			{ staff_id: 3, variant_id: null, price: null, duration_min: null },
		] );
		expect( toggleStaff( [ perVariant, ...on ], 3, false ) ).toEqual( [] );
	} );

	it( 'drops own terms for every variant once a second variant comes', () => {
		const staff = setStaffTerms( toggleStaff( [], 3, true ), 3, {
			price: { amount: 5, currency: 'IRR' },
			duration_min: 20,
		} );

		expect( fitStaffTerms( staff, 1 ) ).toBe( staff );
		expect( fitStaffTerms( staff, 2 ) ).toEqual( [
			{ staff_id: 3, variant_id: null, price: null, duration_min: null },
		] );
	} );
} );

describe( 'the weekly schedule', () => {
	it( 'copies one day to every day, replacing theirs', () => {
		const rules = copyToEveryDay(
			[
				{ weekday: 0, start: '09:00', end: '13:00', kind: 'work' },
				{ weekday: 4, start: '10:00', end: '11:00', kind: 'work' },
			],
			0
		);

		expect( rules ).toHaveLength( 7 );
		expect( rules.map( ( rule ) => rule.weekday ) ).toEqual( [
			0, 1, 2, 3, 4, 5, 6,
		] );
		expect( rules.every( ( rule ) => rule.start === '09:00' ) ).toBe(
			true
		);
	} );
} );

describe( 'yearFrom', () => {
	it( 'covers 366 days, the longest range the API reads', () => {
		expect( yearFrom( '2026-03-01' ) ).toEqual( {
			from: '2026-03-01',
			to: '2027-03-01',
		} );
	} );
} );

describe( 'normalize', () => {
	it( 'finds a Persian name typed with Arabic letters', () => {
		expect( normalize( 'علي كريمي' ) ).toBe( normalize( 'علی کریمی' ) );
	} );
} );
