import {
	fromJalali,
	parseLocalDate,
	toJalali,
	type Calendar,
	type DateParts,
} from '@vaqtyar/shared';
import { __ } from '@wordpress/i18n';

/** A month of the shown calendar: Jalali 1405/07, or Gregorian 2026/09. */
export interface MonthCursor {
	year: number;
	month: number;
}

export interface MonthGrid {
	/** The Gregorian date the month starts on, for the availability query. */
	start: string;
	days: number;
	/** Empty cells before the first day; weeks start on Saturday. */
	blanks: number;
	/** The Gregorian date of each day of the month, in order. */
	dates: string[];
}

function iso( { year, month, day }: DateParts ): string {
	return [ year, month, day ]
		.map( ( part, index ) =>
			String( part ).padStart( index === 0 ? 4 : 2, '0' )
		)
		.join( '-' );
}

function gregorian( calendar: Calendar, parts: DateParts ): DateParts {
	return calendar === 'jalali' ? fromJalali( parts ) : parts;
}

export function shiftMonth( cursor: MonthCursor, delta: number ): MonthCursor {
	const index = cursor.year * 12 + ( cursor.month - 1 ) + delta;

	return { year: Math.floor( index / 12 ), month: ( index % 12 ) + 1 };
}

/**
 * The month a Gregorian local date ("2026-09-25") falls in, in the calendar.
 *
 * @param calendar
 * @param localDate
 */
export function cursorOf( calendar: Calendar, localDate: string ): MonthCursor {
	const parts = parseLocalDate( localDate );
	const shown = calendar === 'jalali' ? toJalali( parts ) : parts;

	return { year: shown.year, month: shown.month };
}

/**
 * @param calendar
 * @param cursor
 */
export function monthGrid(
	calendar: Calendar,
	cursor: MonthCursor
): MonthGrid {
	const first = gregorian( calendar, { ...cursor, day: 1 } );
	const next = gregorian( calendar, { ...shiftMonth( cursor, 1 ), day: 1 } );
	const startMs = Date.UTC( first.year, first.month - 1, first.day );
	const days = Math.round(
		( Date.UTC( next.year, next.month - 1, next.day ) - startMs ) /
			86_400_000
	);
	const dates = Array.from( { length: days }, ( _, index ) =>
		iso( gregorian( calendar, { ...cursor, day: index + 1 } ) )
	);

	return {
		start: iso( first ),
		days,
		// getUTCDay(): 0 is Sunday … 6 is Saturday; Saturday is the first column.
		blanks: ( new Date( startMs ).getUTCDay() + 1 ) % 7,
		dates,
	};
}

/**
 * The name of a month in the calendar.
 *
 * @param calendar
 * @param month    1 to 12.
 */
export function monthName( calendar: Calendar, month: number ): string {
	const names =
		calendar === 'jalali'
			? [
					__( 'Farvardin', 'vaqtyar' ),
					__( 'Ordibehesht', 'vaqtyar' ),
					__( 'Khordad', 'vaqtyar' ),
					__( 'Tir', 'vaqtyar' ),
					__( 'Mordad', 'vaqtyar' ),
					__( 'Shahrivar', 'vaqtyar' ),
					__( 'Mehr', 'vaqtyar' ),
					__( 'Aban', 'vaqtyar' ),
					__( 'Azar', 'vaqtyar' ),
					__( 'Dey', 'vaqtyar' ),
					__( 'Bahman', 'vaqtyar' ),
					__( 'Esfand', 'vaqtyar' ),
				]
			: [
					__( 'January', 'vaqtyar' ),
					__( 'February', 'vaqtyar' ),
					__( 'March', 'vaqtyar' ),
					__( 'April', 'vaqtyar' ),
					__( 'May', 'vaqtyar' ),
					__( 'June', 'vaqtyar' ),
					__( 'July', 'vaqtyar' ),
					__( 'August', 'vaqtyar' ),
					__( 'September', 'vaqtyar' ),
					__( 'October', 'vaqtyar' ),
					__( 'November', 'vaqtyar' ),
					__( 'December', 'vaqtyar' ),
				];

	return names[ month - 1 ] ?? '';
}

/** Saturday first, matching the grid. */
export function weekdayNames(): string[] {
	return [
		__( 'Sat', 'vaqtyar' ),
		__( 'Sun', 'vaqtyar' ),
		__( 'Mon', 'vaqtyar' ),
		__( 'Tue', 'vaqtyar' ),
		__( 'Wed', 'vaqtyar' ),
		__( 'Thu', 'vaqtyar' ),
		__( 'Fri', 'vaqtyar' ),
	];
}
