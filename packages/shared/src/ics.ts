import { SLUG } from './identity';

export interface IcsEvent {
	/** A stable id, so a calendar updates the event instead of duplicating it. */
	uid: string;
	/** ISO 8601 with an offset, as the API gives appointment times. */
	start: string;
	end: string;
	summary: string;
	description?: string;
	location?: string;
}

/**
 * "2026-09-25T10:30:00+03:30" as the UTC "20260925T070000Z" an iCalendar wants.
 *
 * @param iso
 */
function utc( iso: string ): string {
	const date = new Date( iso );
	if ( Number.isNaN( date.getTime() ) ) {
		throw new RangeError( `Not a date: ${ iso }` );
	}

	return date.toISOString().replace( /[-:]|\.\d{3}/g, '' );
}

/**
 * RFC 5545 §3.3.11: backslash, semicolon, comma and line breaks.
 *
 * @param text
 */
function escapeText( text: string ): string {
	return text
		.replace( /\\/g, '\\\\' )
		.replace( /;/g, '\\;' )
		.replace( /,/g, '\\,' )
		.replace( /\r?\n/g, '\\n' );
}

/**
 * Lines longer than 75 octets are folded with a CRLF and a space (RFC 5545 §3.1).
 *
 * @param line
 */
function fold( line: string ): string[] {
	const encoder = new TextEncoder();
	const parts: string[] = [];
	let current = '';
	let size = 0;
	for ( const character of line ) {
		const bytes = encoder.encode( character ).length;
		// A continuation line starts with a space, which counts too.
		const limit = parts.length === 0 ? 75 : 74;
		if ( size + bytes > limit ) {
			parts.push( current );
			current = '';
			size = 0;
		}
		current += character;
		size += bytes;
	}
	parts.push( current );

	return parts.map( ( part, index ) =>
		index === 0 ? part : ` ${ part }`
	);
}

/**
 * One appointment as an iCalendar (.ics) file, which any calendar app opens.
 *
 * @param event The appointment.
 * @param now   The moment it is made (DTSTAMP); a parameter so a test can fix it.
 */
export function buildIcs( event: IcsEvent, now: Date = new Date() ): string {
	const lines = [
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		`PRODID:-//${ SLUG }//Appointments//EN`,
		'CALSCALE:GREGORIAN',
		'METHOD:PUBLISH',
		'BEGIN:VEVENT',
		`UID:${ escapeText( event.uid ) }`,
		`DTSTAMP:${ utc( now.toISOString() ) }`,
		`DTSTART:${ utc( event.start ) }`,
		`DTEND:${ utc( event.end ) }`,
		`SUMMARY:${ escapeText( event.summary ) }`,
	];
	if ( event.description ) {
		lines.push( `DESCRIPTION:${ escapeText( event.description ) }` );
	}
	if ( event.location ) {
		lines.push( `LOCATION:${ escapeText( event.location ) }` );
	}
	lines.push( 'END:VEVENT', 'END:VCALENDAR' );

	return lines.flatMap( fold ).join( '\r\n' ) + '\r\n';
}

/**
 * Hands the browser a .ics file to open or save.
 *
 * @param event    The appointment.
 * @param filename Without the extension.
 */
export function downloadIcs( event: IcsEvent, filename: string ): void {
	const blob = new Blob( [ buildIcs( event ) ], {
		type: 'text/calendar;charset=utf-8',
	} );
	const url = URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = `${ filename }.ics`;
	document.body.append( link );
	link.click();
	link.remove();
	URL.revokeObjectURL( url );
}
