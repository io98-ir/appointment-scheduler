/**
 * The digits numbers are shown with; the values of the PHP Digits enum.
 */
export type Digits = 'persian' | 'latin';

const PERSIAN = [ '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' ];

/**
 * Rewrites the Latin digits in a text; everything else stays.
 *
 * @param text
 * @param digits
 */
export function formatDigits( text: string, digits: Digits ): string {
	if ( digits === 'latin' ) {
		return text;
	}

	return text.replace(
		/[0-9]/g,
		( digit ) => PERSIAN[ Number( digit ) ] ?? digit
	);
}
