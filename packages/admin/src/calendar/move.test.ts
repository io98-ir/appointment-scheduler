import { ApiClient } from '@vaqtyar/shared';
import { describe, expect, it } from 'vitest';

import { reschedule } from './move';

function client( status: number, body: unknown ) {
	const sent: { url: string; body: unknown }[] = [];
	const api = new ApiClient( {
		baseUrl: 'https://example.test/wp-json/x/v1/',
		fetch: async ( url, init ) => {
			sent.push( {
				url: String( url ),
				body: JSON.parse( String( init?.body ) ),
			} );

			return new Response( JSON.stringify( body ), { status } );
		},
	} );

	return { api, sent };
}

const REQUEST = {
	id: 7,
	start: '2026-10-03T12:00:00+03:30',
	staff: 2,
};

describe( 'reschedule', () => {
	it( 'moves an appointment', async () => {
		const { api, sent } = client( 200, { id: 7 } );

		await expect( reschedule( api, REQUEST ) ).resolves.toEqual( {
			moved: true,
		} );
		expect( sent[ 0 ]?.url ).toContain( '/appointments/7/reschedule' );
		expect( sent[ 0 ]?.body ).toEqual( {
			start: REQUEST.start,
			staff: 2,
		} );
	} );

	it( 'returns a policy refusal, so staff can override it', async () => {
		const { api } = client( 409, {
			code: 'policy.reschedule_window_passed',
			message: 'Too late to move.',
			data: { status: 409 },
		} );

		await expect( reschedule( api, REQUEST ) ).resolves.toEqual( {
			moved: false,
			refusal: 'Too late to move.',
		} );
	} );

	it( 'sends the override with its reason', async () => {
		const { api, sent } = client( 200, { id: 7 } );

		await reschedule( api, { ...REQUEST, override: true, reason: 'VIP' } );
		expect( sent[ 0 ]?.body ).toMatchObject( {
			override: true,
			reason: 'VIP',
		} );
	} );

	it( 'throws any other error, for the snackbar', async () => {
		const { api } = client( 409, {
			code: 'slot_taken',
			message: 'Taken.',
			data: { status: 409 },
		} );

		await expect( reschedule( api, REQUEST ) ).rejects.toMatchObject( {
			code: 'slot_taken',
		} );
	} );
} );
