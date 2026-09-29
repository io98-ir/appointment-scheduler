/**
 * "YYYY-MM-DD" of a moment in the browser's own timezone, which is the
 * admin's: the API's report ranges are local dates.
 *
 * @param date
 */
export function localDate( date: Date ): string {
	const pad = ( value: number ) => String( value ).padStart( 2, '0' );

	return `${ date.getFullYear() }-${ pad( date.getMonth() + 1 ) }-${ pad(
		date.getDate()
	) }`;
}

/**
 * @param days How many days back from today; 0 is today.
 */
export function daysAgo( days: number ): string {
	const date = new Date();
	date.setDate( date.getDate() - days );

	return localDate( date );
}
