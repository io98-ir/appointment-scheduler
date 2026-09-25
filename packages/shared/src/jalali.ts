import { toGregorian, toJalaali } from 'jalaali-js';

import { formatDigits, type Digits } from './digits';

/** The calendar dates are shown in; the values of the PHP Calendar enum. */
export type Calendar = 'jalali' | 'gregorian';

export interface DateParts {
	year: number;
	month: number;
	day: number;
}

const LOCAL_DATE = /^(\d{4})-(\d{2})-(\d{2})$/;

/**
 * A date as the API sends it: "2026-09-25", with no time zone.
 *
 * @param localDate
 */
export function parseLocalDate( localDate: string ): DateParts {
	const match = LOCAL_DATE.exec( localDate );
	const [ , year, month, day ] = match ?? [];
	if ( year === undefined || month === undefined || day === undefined ) {
		throw new RangeError( `Not a local date: ${ localDate }` );
	}
	const parts = {
		year: Number( year ),
		month: Number( month ),
		day: Number( day ),
	};
	// Rejects 2026-02-30 and the like, which Date would roll over.
	const date = new Date( Date.UTC( parts.year, parts.month - 1, parts.day ) );
	if (
		date.getUTCMonth() !== parts.month - 1 ||
		date.getUTCDate() !== parts.day
	) {
		throw new RangeError( `Not a local date: ${ localDate }` );
	}

	return parts;
}

/**
 * @param date A Gregorian date.
 */
export function toJalali( date: DateParts ): DateParts {
	const { jy, jm, jd } = toJalaali( date.year, date.month, date.day );

	return { year: jy, month: jm, day: jd };
}

/**
 * @param date A Jalali date.
 */
export function fromJalali( date: DateParts ): DateParts {
	const { gy, gm, gd } = toGregorian( date.year, date.month, date.day );

	return { year: gy, month: gm, day: gd };
}

/**
 * The numeric date, as the PHP DateFormatter::date() writes it: 1405/07/03,
 * or 2026-09-25 (2026/09/25 with Persian digits: "-" would break the bidi
 * run of Persian digits and show the date reversed).
 *
 * @param localDate "2026-09-25"
 * @param calendar
 * @param digits
 */
export function formatDate(
	localDate: string,
	calendar: Calendar,
	digits: Digits
): string {
	if ( calendar === 'gregorian' ) {
		parseLocalDate( localDate );

		return formatDigits(
			digits === 'persian' ? localDate.replaceAll( '-', '/' ) : localDate,
			digits
		);
	}
	const { year, month, day } = toJalali( parseLocalDate( localDate ) );

	return formatDigits(
		`${ year }/${ pad( month ) }/${ pad( day ) }`,
		digits
	);
}

function pad( value: number ): string {
	return String( value ).padStart( 2, '0' );
}
