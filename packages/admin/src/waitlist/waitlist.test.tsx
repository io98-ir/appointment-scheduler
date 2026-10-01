// @vitest-environment jsdom
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClient } from '@vaqtyar/shared';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { ApiContext } from '../api';
import { WaitlistPage } from './WaitlistPage';

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

const ROWS = [
	{
		id: 3,
		customer_id: 9,
		customer_name: 'Sara Ahmadi',
		customer_phone: '+989120000000',
		variant_id: 100,
		location_id: 1,
		staff_id: null,
		date: '2027-01-12',
		status: 'waiting',
		notified_at: null,
		created_at: '2027-01-10T08:00:00+00:00',
	},
	{
		id: 2,
		customer_id: 8,
		customer_name: null,
		customer_phone: null,
		variant_id: 100,
		location_id: 1,
		staff_id: null,
		date: '2027-01-11',
		status: 'notified',
		notified_at: '2027-01-10T09:00:00+00:00',
		created_at: '2027-01-09T08:00:00+00:00',
	},
];

describe( 'the waiting list screen', () => {
	let container: HTMLElement;
	let root: Root;
	let calls: string[];

	beforeEach( async () => {
		calls = [];
		container = document.createElement( 'div' );
		document.body.append( container );
		root = createRoot( container );
		const fetch = async (
			resource: RequestInfo | URL,
			init?: RequestInit
		) => {
			const url = new URL( String( resource ) );
			const path = url.pathname.replace( '/wp-json/x/v1', '' );
			calls.push( `${ init?.method ?? 'GET' } ${ path }` );
			if ( init?.method === 'DELETE' ) {
				return new Response( null, { status: 204 } );
			}
			if ( path === '/waitlist' ) {
				return new Response( JSON.stringify( ROWS ), {
					headers: { 'X-WP-Total': '2', 'X-WP-TotalPages': '1' },
				} );
			}
			if ( path === '/services' ) {
				return new Response(
					JSON.stringify( [
						{
							id: 10,
							name: 'Haircut',
							variants: [
								{ id: 100, label: '30 min', is_default: true },
							],
						},
					] )
				);
			}

			return new Response( '[]' );
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
						<WaitlistPage />
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

	it( 'lists who waits, for which service and day, and who was told', () => {
		const rows = [ ...container.querySelectorAll( 'tbody tr' ) ];

		expect( rows ).toHaveLength( 2 );
		expect( rows[ 0 ]?.textContent ).toContain( 'Sara Ahmadi' );
		expect( rows[ 0 ]?.textContent ).toContain( '+989120000000' );
		expect( rows[ 0 ]?.textContent ).toContain( 'Haircut' );
		expect( rows[ 0 ]?.textContent ).toContain( 'Waiting' );
		expect( rows[ 1 ]?.textContent ).toContain( 'Told' );
	} );

	it( 'removes a request', async () => {
		const remove = [ ...container.querySelectorAll( 'button' ) ].find(
			( element ) => element.textContent === 'Remove'
		);

		await act( async () => {
			remove?.click();
		} );
		await flush();

		expect( calls ).toContain( 'DELETE /waitlist/3' );
	} );
} );
