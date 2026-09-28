import { describe, expect, it } from 'vitest';

import { viewOf } from './CalendarPage';
import { nearest } from './QuickBook';

describe( 'calendar screen', () => {
	it( 'reads the view and date from the route', () => {
		expect( viewOf( '/calendar' ) ).toEqual( { view: 'day', date: null } );
		expect( viewOf( '/calendar/week/2026-10-03' ) ).toEqual( {
			view: 'week',
			date: '2026-10-03',
		} );
		expect( viewOf( '/calendar/day/nonsense' ) ).toEqual( {
			view: 'day',
			date: null,
		} );
	} );

	it( 'offers the start nearest to the click', () => {
		const starts = [
			'2026-10-03T09:00:00+03:30',
			'2026-10-03T09:30:00+03:30',
			'2026-10-03T11:00:00+03:30',
		];
		expect( nearest( starts, 600 ) ).toBe( starts[ 1 ] );
		expect( nearest( starts, 650 ) ).toBe( starts[ 2 ] );
		expect( nearest( [], 600 ) ).toBe( '' );
	} );
} );
