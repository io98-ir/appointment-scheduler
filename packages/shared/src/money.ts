import type { Money } from './api-types';
import { formatDigits, type Digits } from './digits';

/**
 * The amount with thousands separators: 1,500,000 or ۱٬۵۰۰٬۰۰۰. The unit
 * (rial, toman) is the caller's, as translated text.
 *
 * Amounts are integers (principles: money is never a float); anything else
 * is a bug in the API or the caller and throws.
 *
 * @param money
 * @param digits
 */
export function formatAmount( money: Money, digits: Digits ): string {
	if ( ! Number.isSafeInteger( money.amount ) ) {
		throw new RangeError( `Not an integer amount: ${ money.amount }` );
	}
	const separator = digits === 'persian' ? '٬' : ',';
	const grouped = String( Math.abs( money.amount ) ).replace(
		/\B(?=(\d{3})+$)/g,
		separator
	);

	return ( money.amount < 0 ? '-' : '' ) + formatDigits( grouped, digits );
}
