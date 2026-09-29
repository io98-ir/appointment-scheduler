import { describe, expect, it } from 'vitest';

import { csvCell, toCsv } from './csv';

describe( 'csvCell', () => {
	it( 'leaves plain text and numbers alone', () => {
		expect( csvCell( 'Ali' ) ).toBe( 'Ali' );
		expect( csvCell( 1500000 ) ).toBe( '1500000' );
		expect( csvCell( -5 ) ).toBe( '-5' );
	} );

	it( 'quotes a comma, a quote and a line break', () => {
		expect( csvCell( 'a,b' ) ).toBe( '"a,b"' );
		expect( csvCell( 'say "hi"' ) ).toBe( '"say ""hi"""' );
		expect( csvCell( 'one\ntwo' ) ).toBe( '"one\ntwo"' );
	} );

	it( 'defuses text a spreadsheet would run as a formula', () => {
		expect( csvCell( '=SUM(A1)' ) ).toBe( "'=SUM(A1)" );
		expect( csvCell( '+1+1' ) ).toBe( "'+1+1" );
		expect( csvCell( '+98912' ) ).toBe( '+98912' );
		expect( csvCell( '-1+1' ) ).toBe( "'-1+1" );
		expect( csvCell( '@cmd' ) ).toBe( "'@cmd" );
		expect( csvCell( '=1,2' ) ).toBe( `"'=1,2"` );
	} );

	it( 'keeps Persian text as it is', () => {
		expect( csvCell( 'علی رضایی' ) ).toBe( 'علی رضایی' );
	} );
} );

describe( 'toCsv', () => {
	it( 'writes CRLF rows, ending with one', () => {
		expect(
			toCsv( [
				[ 'code', 'total' ],
				[ 'AB12CD34', 1000 ],
			] )
		).toBe( 'code,total\r\nAB12CD34,1000\r\n' );
	} );
} );
