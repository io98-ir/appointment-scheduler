import { describe, expect, it } from 'vitest';

import { formatDigits } from './digits';
import { formatAmount } from './money';

describe( 'formatAmount', () => {
	it.each( [
		[ 0, '0', '۰' ],
		[ 999, '999', '۹۹۹' ],
		[ 1500000, '1,500,000', '۱٬۵۰۰٬۰۰۰' ],
		[ -250000, '-250,000', '-۲۵۰٬۰۰۰' ],
	] )( 'formats %i', ( amount, latin, persian ) => {
		expect( formatAmount( { amount, currency: 'IRR' }, 'latin' ) ).toBe(
			latin
		);
		expect( formatAmount( { amount, currency: 'IRR' }, 'persian' ) ).toBe(
			persian
		);
	} );

	it.each( [ 1.5, Number.NaN, 2 ** 53 ] )(
		'refuses %d, which is not a safe integer',
		( amount ) => {
			expect( () =>
				formatAmount( { amount, currency: 'IRR' }, 'latin' )
			).toThrow( RangeError );
		}
	);
} );

describe( 'formatDigits', () => {
	it( 'rewrites only the digits', () => {
		expect( formatDigits( 'ساعت 09:30', 'persian' ) ).toBe( 'ساعت ۰۹:۳۰' );
		expect( formatDigits( 'ساعت 09:30', 'latin' ) ).toBe( 'ساعت 09:30' );
	} );
} );
