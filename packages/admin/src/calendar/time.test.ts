import { describe, expect, it } from 'vitest';

import {
	addDays,
	isoAt,
	lanes,
	offsetOf,
	snap,
	wallClock,
	weekOf,
} from './time';

describe( 'calendar time', () => {
	it( 'reads the wall clock of the location from an ISO time', () => {
		expect( wallClock( '2026-10-03T10:45:00+03:30' ) ).toEqual( {
			date: '2026-10-03',
			minutes: 645,
		} );
	} );

	it( 'writes a wall-clock time back with its offset', () => {
		expect( isoAt( '2026-10-03', 645, '+03:30' ) ).toBe(
			'2026-10-03T10:45:00+03:30'
		);
		expect( isoAt( '2026-10-03', 1440, '+03:30' ) ).toBe(
			'2026-10-04T00:00:00+03:30'
		);
	} );

	it( 'finds the offset of a zone on a date', () => {
		expect( offsetOf( 'Asia/Tehran', '2026-10-03' ) ).toBe( '+03:30' );
		expect( offsetOf( 'UTC', '2026-10-03' ) ).toBe( '+00:00' );
		expect( offsetOf( 'America/New_York', '2026-01-10' ) ).toBe( '-05:00' );
	} );

	it( 'adds days across months and years', () => {
		expect( addDays( '2026-12-31', 1 ) ).toBe( '2027-01-01' );
		expect( addDays( '2026-03-01', -1 ) ).toBe( '2026-02-28' );
	} );

	it( 'gives the week from Saturday', () => {
		// 2026-10-03 is a Saturday, 2026-10-07 a Wednesday.
		const week = weekOf( '2026-10-07' );
		expect( week ).toHaveLength( 7 );
		expect( week[ 0 ] ).toBe( '2026-10-03' );
		expect( week[ 6 ] ).toBe( '2026-10-09' );
		expect( weekOf( '2026-10-03' )[ 0 ] ).toBe( '2026-10-03' );
	} );

	it( 'snaps minutes to the step, inside the day', () => {
		expect( snap( 607, 15 ) ).toBe( 600 );
		expect( snap( 608, 15 ) ).toBe( 615 );
		expect( snap( -20, 15 ) ).toBe( 0 );
		expect( snap( 1439, 15 ) ).toBe( 1425 );
	} );

	it( 'puts overlapping items side by side', () => {
		const placed = lanes( [
			{ id: 1, from: 600, to: 660 },
			{ id: 2, from: 630, to: 690 },
			{ id: 3, from: 660, to: 720 },
			{ id: 4, from: 800, to: 830 },
		] );
		expect( placed.get( 1 ) ).toEqual( { lane: 0, of: 2 } );
		expect( placed.get( 2 ) ).toEqual( { lane: 1, of: 2 } );
		// Starts when 1 ends, so it reuses its lane.
		expect( placed.get( 3 ) ).toEqual( { lane: 0, of: 2 } );
		expect( placed.get( 4 ) ).toEqual( { lane: 0, of: 1 } );
	} );
} );
