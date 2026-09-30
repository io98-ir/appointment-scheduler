import { __ } from '@wordpress/i18n';

/** The default accent: the teal of the maker's own site (ADR-020). */
export const DEFAULT_ACCENT = '#0e7184';

/** The ink on a light accent; the white one is for the dark accents. */
const DARK_INK = '#0b1d25';
const LIGHT_INK = '#ffffff';

export interface Preset {
	id: string;
	label: () => string;
	color: string;
}

/**
 * Ready palettes: one accent each, everything else (hover, soft fills, dark
 * mode) is derived from it in CSS. "Custom" is any #rrggbb.
 */
export const PRESETS: Preset[] = [
	{ id: 'teal', label: () => __( 'Teal', 'vaqtyar' ), color: DEFAULT_ACCENT },
	{
		id: 'indigo',
		label: () => __( 'Indigo', 'vaqtyar' ),
		color: '#4f46e5',
	},
	{
		id: 'emerald',
		label: () => __( 'Emerald', 'vaqtyar' ),
		color: '#047857',
	},
	{ id: 'rose', label: () => __( 'Rose', 'vaqtyar' ), color: '#be123c' },
	{ id: 'amber', label: () => __( 'Amber', 'vaqtyar' ), color: '#b45309' },
	{ id: 'slate', label: () => __( 'Slate', 'vaqtyar' ), color: '#334155' },
];

const HEX = /^#[0-9a-f]{6}$/i;

export function isHexColor( value: string ): boolean {
	return HEX.test( value );
}

/**
 * @param hex A #rrggbb colour.
 */
function channels( hex: string ): [ number, number, number ] {
	return [ 1, 3, 5 ].map( ( at ) =>
		Number.parseInt( hex.slice( at, at + 2 ), 16 )
	) as [ number, number, number ];
}

/**
 * WCAG relative luminance of a #rrggbb colour.
 *
 * @param hex
 */
function luminance( hex: string ): number {
	const [ r, g, b ] = channels( hex ).map( ( value ) => {
		const unit = value / 255;

		return unit <= 0.03928
			? unit / 12.92
			: ( ( unit + 0.055 ) / 1.055 ) ** 2.4;
	} ) as [ number, number, number ];

	return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/**
 * WCAG contrast ratio of two #rrggbb colours, 1 to 21.
 *
 * @param a
 * @param b
 */
export function contrast( a: string, b: string ): number {
	const [ high, low ] = [ luminance( a ), luminance( b ) ].sort(
		( x, y ) => y - x
	) as [ number, number ];

	return ( high + 0.05 ) / ( low + 0.05 );
}

/**
 * The text colour that reads best on an accent: white on a dark one, the
 * dark ink on a light one.
 *
 * @param accent A #rrggbb colour.
 */
export function onAccent( accent: string ): string {
	return contrast( accent, LIGHT_INK ) >= contrast( accent, DARK_INK )
		? LIGHT_INK
		: DARK_INK;
}

/**
 * The CSS variables one accent colour sets: the plugin's own, and WordPress's
 * theme colour, which the components of @wordpress/components paint with.
 *
 * @param color The owner's #rrggbb, or "" for the default.
 */
export function paletteVars( color: string ): Record< string, string > {
	const accent = isHexColor( color ) ? color.toLowerCase() : DEFAULT_ACCENT;

	return {
		'--vqy-accent': accent,
		'--vqy-on-accent': onAccent( accent ),
		'--wp-admin-theme-color': accent,
		'--wp-admin-theme-color--rgb': channels( accent ).join( ', ' ),
		'--wp-admin-theme-color-darker-10': `color-mix(in srgb, ${ accent } 85%, black)`,
		'--wp-admin-theme-color-darker-20': `color-mix(in srgb, ${ accent } 70%, black)`,
	};
}
