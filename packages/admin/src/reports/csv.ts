/**
 * One CSV cell. A text that a spreadsheet would run as a formula (it starts
 * with =, +, -, @, a tab or a carriage return) gets a leading apostrophe;
 * numbers, and a phone number like "+989121234567", are written as they are.
 *
 * @param value
 */
export function csvCell( value: string | number ): string {
	let text = String( value );
	const isFormulaLike =
		/^[=+\-@\t\r]/.test( text ) && ! /^[+-]\d+$/.test( text );
	if ( typeof value === 'string' && isFormulaLike ) {
		text = `'${ text }`;
	}

	return /[",\r\n]/.test( text ) ? `"${ text.replace( /"/g, '""' ) }"` : text;
}

/**
 * @param rows Each row a list of cells, the first row usually the header.
 */
export function toCsv( rows: ( string | number )[][] ): string {
	return (
		rows.map( ( row ) => row.map( csvCell ).join( ',' ) ).join( '\r\n' ) +
		'\r\n'
	);
}

/**
 * Saves the CSV as a file. A byte order mark lets Excel read Persian text.
 *
 * @param filename
 * @param csv
 */
export function downloadCsv( filename: string, csv: string ): void {
	const url = URL.createObjectURL(
		new Blob( [ '﻿', csv ], { type: 'text/csv;charset=utf-8' } )
	);
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = filename;
	document.body.append( link );
	link.click();
	link.remove();
	URL.revokeObjectURL( url );
}
