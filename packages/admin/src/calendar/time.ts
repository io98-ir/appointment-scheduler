/**
 * The calendar's times. The grid shows the wall clock of the location, which
 * the API's ISO times already carry ("2026-10-03T10:00:00+03:30"), so the
 * browser's own zone never enters the picture.
 */

/** Minutes of a day. */
export const DAY = 1440;

/**
 * @param iso ISO 8601 with the location's offset.
 */
export function wallClock( iso: string ): { date: string; minutes: number } {
	return {
		date: iso.slice( 0, 10 ),
		minutes:
			Number( iso.slice( 11, 13 ) ) * 60 + Number( iso.slice( 14, 16 ) ),
	};
}

/**
 * A wall-clock time as the API takes it; 1440 minutes is the next midnight.
 *
 * @param date    "YYYY-MM-DD"
 * @param minutes Since midnight.
 * @param offset  "+03:30"
 */
export function isoAt( date: string, minutes: number, offset: string ): string {
	const day = addDays( date, Math.floor( minutes / DAY ) );
	const rest = ( ( minutes % DAY ) + DAY ) % DAY;

	return `${ day }T${ pad( Math.floor( rest / 60 ) ) }:${ pad(
		rest % 60
	) }:00${ offset }`;
}

/**
 * The UTC offset of a zone at noon of a date, "+03:30".
 *
 * @param timezone An IANA name.
 * @param date     "YYYY-MM-DD"
 */
export function offsetOf( timezone: string, date: string ): string {
	const name =
		new Intl.DateTimeFormat( 'en-US', {
			timeZone: timezone,
			timeZoneName: 'longOffset',
		} )
			.formatToParts( new Date( date + 'T12:00:00Z' ) )
			.find( ( part ) => part.type === 'timeZoneName' )?.value ?? 'GMT';
	const match = /GMT([+-])(\d{2}):(\d{2})/.exec( name );

	return match ? `${ match[ 1 ] }${ match[ 2 ] }:${ match[ 3 ] }` : '+00:00';
}

/**
 * Today in a zone, "YYYY-MM-DD".
 *
 * @param timezone An IANA name.
 */
export function todayIn( timezone: string ): string {
	return new Intl.DateTimeFormat( 'en-CA', { timeZone: timezone } ).format(
		new Date()
	);
}

export function addDays( date: string, days: number ): string {
	const day = new Date( date + 'T00:00:00Z' );
	day.setUTCDate( day.getUTCDate() + days );

	return day.toISOString().slice( 0, 10 );
}

/**
 * The seven dates of the week a date falls in, from Saturday (the Iranian
 * week, as the weekly hours number it).
 *
 * @param date "YYYY-MM-DD"
 */
export function weekOf( date: string ): string[] {
	// getUTCDay(): Saturday is 6.
	const back = ( new Date( date + 'T00:00:00Z' ).getUTCDay() + 1 ) % 7;
	const first = addDays( date, -back );

	return Array.from( { length: 7 }, ( _, i ) => addDays( first, i ) );
}

/**
 * The nearest step to a point of the day, as a start the day can hold.
 *
 * @param minutes
 * @param step
 */
export function snap( minutes: number, step: number ): number {
	return Math.min(
		DAY - step,
		Math.max( 0, Math.round( minutes / step ) * step )
	);
}

/**
 * Side-by-side lanes for items that overlap: each takes the first lane free
 * at its start, and a group of items that overlap one another shares the
 * width equally.
 *
 * @param items Of one column.
 */
export function lanes(
	items: { id: number; from: number; to: number }[]
): Map< number, { lane: number; of: number } > {
	const placed = new Map< number, { lane: number; of: number } >();
	const sorted = [ ...items ].sort(
		( a, b ) => a.from - b.from || b.to - a.to
	);
	let group: number[] = [];
	let ends: number[] = [];
	let groupEnd = -1;
	const close = () => {
		for ( const id of group ) {
			placed.set( id, {
				lane: placed.get( id )?.lane ?? 0,
				of: ends.length,
			} );
		}
		group = [];
		ends = [];
	};
	for ( const item of sorted ) {
		if ( item.from >= groupEnd ) {
			close();
		}
		let lane = ends.findIndex( ( end ) => end <= item.from );
		if ( lane === -1 ) {
			lane = ends.length;
		}
		ends[ lane ] = item.to;
		placed.set( item.id, { lane, of: 0 } );
		group.push( item.id );
		groupEnd = Math.max( groupEnd, item.to );
	}
	close();

	return placed;
}

function pad( value: number ): string {
	return String( value ).padStart( 2, '0' );
}
