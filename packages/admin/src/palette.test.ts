import { describe, expect, it } from 'vitest';

import {
	contrast,
	DEFAULT_ACCENT,
	isHexColor,
	onAccent,
	paletteVars,
	PRESETS,
} from './palette';

describe( 'palette', () => {
	it( 'puts white on a dark accent and the dark ink on a light one', () => {
		expect( onAccent( '#0e7184' ) ).toBe( '#ffffff' );
		expect( onAccent( '#4f46e5' ) ).toBe( '#ffffff' );
		expect( onAccent( '#fde047' ) ).toBe( '#0b1d25' );
		expect( onAccent( '#a4f8f1' ) ).toBe( '#0b1d25' );
	} );

	it( 'keeps every preset readable as a button colour (WCAG AA, 4.5:1)', () => {
		for ( const preset of PRESETS ) {
			expect( isHexColor( preset.color ) ).toBe( true );
			expect( onAccent( preset.color ) ).toBe( '#ffffff' );
			expect(
				contrast( preset.color, '#ffffff' )
			).toBeGreaterThanOrEqual( 4.5 );
		}
	} );

	it( 'derives the variables from one colour', () => {
		const vars = paletteVars( '#112233' );

		expect( vars[ '--vqy-accent' ] ).toBe( '#112233' );
		expect( vars[ '--vqy-on-accent' ] ).toBe( '#ffffff' );
		expect( vars[ '--wp-admin-theme-color' ] ).toBe( '#112233' );
		expect( vars[ '--wp-admin-theme-color--rgb' ] ).toBe( '17, 34, 51' );
	} );

	it( 'falls back to the default for an empty or malformed colour', () => {
		for ( const value of [ '', 'red', '#12' ] ) {
			expect( paletteVars( value )[ '--vqy-accent' ] ).toBe(
				DEFAULT_ACCENT
			);
		}
	} );
} );
