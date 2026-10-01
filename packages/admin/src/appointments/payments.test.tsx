// @vitest-environment jsdom
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClient, type PaymentLedger } from '@vaqtyar/shared';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import {
	afterEach,
	beforeEach,
	describe,
	expect,
	it,
	vi,
	type Mock,
} from 'vitest';

import { ApiContext } from '../api';
import { Payments } from './Payments';

(
	globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }
 ).IS_REACT_ACT_ENVIRONMENT = true;

async function flush() {
	for ( let i = 0; i < 4; i++ ) {
		await act( async () => {
			await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		} );
	}
}

const LEDGER: PaymentLedger = {
	items: [
		{
			id: 4,
			appointment_id: 12,
			gateway: 'zibal',
			amount: { amount: 300000, currency: 'IRR' },
			status: 'succeeded',
			ref_id: 'R1',
			refunded: 0,
		},
		{
			id: 5,
			appointment_id: 12,
			gateway: 'zarinpal',
			amount: { amount: 100000, currency: 'IRR' },
			status: 'failed',
			ref_id: null,
			refunded: 0,
		},
	],
	paid: 300000,
	refunded: 0,
};

describe( 'the payments of an appointment', () => {
	let container: HTMLElement;
	let root: Root;
	let posts: Array< { path: string; body: unknown } >;
	let changed: Mock< () => void >;

	beforeEach( async () => {
		posts = [];
		changed = vi.fn< () => void >();
		container = document.createElement( 'div' );
		document.body.append( container );
		root = createRoot( container );
		const fetch = async (
			resource: RequestInfo | URL,
			init?: RequestInit
		) => {
			const url = new URL( String( resource ) );
			const path = url.pathname.replace( '/wp-json/x/v1', '' );
			if ( init?.method === 'POST' ) {
				posts.push( { path, body: JSON.parse( String( init.body ) ) } );

				return new Response( JSON.stringify( { id: 9 } ), {
					status: 201,
				} );
			}

			return new Response( JSON.stringify( LEDGER ) );
		};
		act( () =>
			root.render(
				<ApiContext.Provider
					value={
						new ApiClient( {
							baseUrl: 'https://example.test/wp-json/x/v1/',
							fetch: fetch as typeof globalThis.fetch,
						} )
					}
				>
					<QueryClientProvider client={ new QueryClient() }>
						<Payments
							appointmentId={ 12 }
							total={ { amount: 1000000, currency: 'IRR' } }
							onChanged={ changed }
						/>
					</QueryClientProvider>
				</ApiContext.Provider>
			)
		);
		await flush();
	} );

	afterEach( () => {
		act( () => root.unmount() );
		container.remove();
	} );

	const button = ( label: string ) =>
		[ ...container.querySelectorAll( 'button' ) ].find(
			( item ) => item.textContent === label
		);
	const type = async ( label: string, value: string ) => {
		const input = [ ...container.querySelectorAll( 'label' ) ]
			.find( ( item ) => item.textContent === label )
			?.closest( '.components-base-control' )
			?.querySelector( 'input' ) as HTMLInputElement;
		await act( async () => {
			const setter = Object.getOwnPropertyDescriptor(
				HTMLInputElement.prototype,
				'value'
			)?.set;
			setter?.call( input, value );
			input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );
	};

	it( 'lists the payments at their gateways and what is left to pay', () => {
		expect( container.textContent ).toContain( 'Zibal' );
		expect( container.textContent ).toContain( 'Zarinpal' );
		expect( container.textContent ).toContain( 'Failed' );
		expect( container.textContent ).toContain( '300,000 IRR' );
		expect( container.textContent ).toContain( 'Left to pay: 700,000 IRR' );
	} );

	it( 'records money received, by default the whole of what is left', async () => {
		await act( async () => {
			button( 'Record payment' )?.click();
		} );
		await flush();

		expect( posts ).toEqual( [
			{
				path: '/payments/offline',
				body: { appointment_id: 12, amount: 700000 },
			},
		] );
		expect( changed ).toHaveBeenCalled();
	} );

	it( 'records another amount when staff type one', async () => {
		await type( 'Money received (IRR)', '250000' );
		await act( async () => {
			button( 'Record payment' )?.click();
		} );
		await flush();

		expect( posts[ 0 ]?.body ).toEqual( {
			appointment_id: 12,
			amount: 250000,
		} );
	} );

	it( 'records a refund of a payment that went through, never more than it', async () => {
		// Only the payment that went through can be refunded.
		expect(
			[ ...container.querySelectorAll( 'button' ) ].filter(
				( item ) => item.textContent === 'Record a refund'
			)
		).toHaveLength( 1 );
		await act( async () => {
			button( 'Record a refund' )?.click();
		} );
		await type( 'Refund amount (IRR)', '999999' );
		await type( 'Reason', 'Cancelled by phone' );
		await act( async () => {
			button( 'Save refund' )?.click();
		} );
		await flush();

		expect( posts ).toEqual( [
			{
				path: '/payments/refunds',
				body: {
					payment_id: 4,
					amount: 300000,
					reason: 'Cancelled by phone',
				},
			},
		] );
	} );
} );
