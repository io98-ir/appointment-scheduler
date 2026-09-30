// @vitest-environment jsdom
import { describe, expect, it } from 'vitest';

import { withCode } from './BookingFlow';

describe( 'the thank-you address', () => {
	it( 'keeps what the address has and adds the tracking code', () => {
		expect( withCode( '/thanks/?x=1', 'AB12CD34' ) ).toBe(
			`${ window.location.origin }/thanks/?x=1&code=AB12CD34`
		);
	} );

	it( 'replaces a code already there', () => {
		expect( withCode( '/thanks/?code=OLD', 'AB12CD34' ) ).toBe(
			`${ window.location.origin }/thanks/?code=AB12CD34`
		);
	} );
} );
