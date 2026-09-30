import { describe, expect, it } from 'vitest';

import { buildIcs } from './ics';

const NOW = new Date( '2026-09-24T08:00:00Z' );

describe( 'buildIcs', () => {
	it( 'writes the appointment in UTC, whatever offset the API gave', () => {
		const ics = buildIcs(
			{
				uid: 'AB12CD34',
				start: '2026-09-25T10:30:00+03:30',
				end: '2026-09-25T11:30:00+03:30',
				summary: 'Haircut',
			},
			NOW
		);

		expect( ics.split( '\r\n' ) ).toEqual( [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//vaqtyar//Appointments//EN',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'BEGIN:VEVENT',
			'UID:AB12CD34',
			'DTSTAMP:20260924T080000Z',
			'DTSTART:20260925T070000Z',
			'DTEND:20260925T080000Z',
			'SUMMARY:Haircut',
			'END:VEVENT',
			'END:VCALENDAR',
			'',
		] );
	} );

	it( 'escapes the characters that would end a value', () => {
		const ics = buildIcs(
			{
				uid: 'x',
				start: '2026-09-25T10:00:00Z',
				end: '2026-09-25T11:00:00Z',
				summary: 'Cut, colour; wash',
				description: 'Line one\nLine two \\ done',
				location: 'Main St, 1',
			},
			NOW
		);

		expect( ics ).toContain( 'SUMMARY:Cut\\, colour\\; wash\r\n' );
		expect( ics ).toContain(
			'DESCRIPTION:Line one\\nLine two \\\\ done\r\n'
		);
		expect( ics ).toContain( 'LOCATION:Main St\\, 1\r\n' );
	} );

	it( 'folds a long line at 75 octets, never inside a letter', () => {
		const summary = 'نوبت '.repeat( 30 );
		const ics = buildIcs(
			{
				uid: 'x',
				start: '2026-09-25T10:00:00Z',
				end: '2026-09-25T11:00:00Z',
				summary,
			},
			NOW
		);

		const lines = ics.split( '\r\n' );
		const encoder = new TextEncoder();
		for ( const line of lines ) {
			expect( encoder.encode( line ).length ).toBeLessThanOrEqual( 75 );
		}
		const at = lines.findIndex( ( line ) => line.startsWith( 'SUMMARY:' ) );
		expect( lines[ at + 1 ]?.startsWith( ' ' ) ).toBe( true );
		// Unfolding gives the text back whole.
		expect( ics.replace( /\r\n /g, '' ) ).toContain(
			`SUMMARY:${ summary }`
		);
	} );

	it( 'rejects a time it cannot read', () => {
		expect( () =>
			buildIcs(
				{ uid: 'x', start: 'soon', end: 'later', summary: 'X' },
				NOW
			)
		).toThrow( RangeError );
	} );
} );
