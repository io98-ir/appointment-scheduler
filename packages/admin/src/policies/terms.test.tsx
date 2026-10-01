// @vitest-environment jsdom
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClient } from '@vaqtyar/shared';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { ApiContext } from '../api';
import { Terms } from './Terms';

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

describe( 'the payment, approval and booking window policies', () => {
	let container: HTMLElement;
	let root: Root;
	let puts: Array< { path: string; body: unknown } >;
	let deletes: string[];
	let stored: Record< string, unknown >;

	beforeEach( async () => {
		puts = [];
		deletes = [];
		stored = {};
		container = document.createElement( 'div' );
		document.body.append( container );
		root = createRoot( container );
		const fetch = async (
			resource: RequestInfo | URL,
			init?: RequestInit
		) => {
			const path = new URL( String( resource ) ).pathname.replace(
				'/wp-json/x/v1',
				''
			);
			if ( init?.method === 'PUT' ) {
				const body = JSON.parse( String( init.body ) );
				puts.push( { path, body } );

				return new Response( JSON.stringify( { config: body } ) );
			}
			if ( init?.method === 'DELETE' ) {
				deletes.push( path );

				return new Response( null, { status: 204 } );
			}

			return new Response(
				JSON.stringify( { config: stored[ path ] ?? null } )
			);
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
						<Terms serviceId={ 7 } />
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

	const fieldset = ( legend: string ) =>
		[ ...container.querySelectorAll( 'fieldset' ) ].find(
			( item ) => item.querySelector( 'legend' )?.textContent === legend
		) as HTMLFieldSetElement;
	const save = async ( legend: string ) => {
		await act( async () => {
			[ ...fieldset( legend ).querySelectorAll( 'button' ) ]
				.find( ( item ) => item.textContent === 'Save' )
				?.click();
		} );
		await flush();
	};

	it( 'says a policy that is not set falls back to the global one', () => {
		expect( container.textContent ).toContain(
			'Not set: the global policy applies.'
		);
	} );

	it( 'saves a percent deposit that must be paid online', async () => {
		const deposit = fieldset( 'Payment when booking online' );
		const toggle = deposit.querySelector< HTMLInputElement >(
			'input[type="checkbox"]'
		);
		await act( async () => {
			toggle?.click();
		} );

		await save( 'Payment when booking online' );

		expect( puts ).toEqual( [
			{
				path: '/policies/deposit/7',
				body: { kind: 'percent', value: 30, required: true },
			},
		] );
	} );

	it( 'asks for no amount when the whole price is asked for', async () => {
		const deposit = fieldset( 'Payment when booking online' );
		const select = deposit.querySelector( 'select' ) as HTMLSelectElement;
		await act( async () => {
			select.value = 'none';
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		expect(
			deposit.querySelectorAll( 'input[type="number"]' )
		).toHaveLength( 0 );
		await save( 'Payment when booking online' );

		expect( puts[ 0 ]?.body ).toEqual( {
			kind: 'none',
			value: 0,
			required: false,
		} );
	} );

	it( 'saves that staff approve bookings', async () => {
		await save( 'Approval' );

		expect( puts ).toEqual( [
			{ path: '/policies/approval/7', body: { required: true } },
		] );
	} );

	it( 'saves a booking window that keeps the site’s opening time', async () => {
		await save( 'Booking window' );

		expect( puts ).toEqual( [
			{
				path: '/policies/booking_window/7',
				body: { min_notice_min: null, max_advance_days: null },
			},
		] );
	} );
} );
