// @vitest-environment jsdom
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClient } from '@vaqtyar/shared';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { ApiContext } from '../api';
import { BookingRulesForm } from './BookingRulesForm';

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

describe( 'the booking rules form', () => {
	let container: HTMLElement;
	let root: Root;
	let puts: unknown[];

	beforeEach( async () => {
		puts = [];
		container = document.createElement( 'div' );
		document.body.append( container );
		root = createRoot( container );
		const fetch = async (
			_resource: RequestInfo | URL,
			init?: RequestInit
		) => {
			if ( init?.method === 'PUT' ) {
				puts.push( JSON.parse( String( init.body ) ) );

				return new Response( String( init.body ) );
			}

			return new Response(
				JSON.stringify( {
					slot_step_min: 30,
					min_notice_min: 60,
					max_advance_days: 60,
					staff_choice: 'least_busy',
				} )
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
						<BookingRulesForm />
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

	const input = ( label: string ) =>
		[ ...container.querySelectorAll( 'label' ) ]
			.find( ( item ) => item.textContent === label )
			?.closest( '.components-base-control' )
			?.querySelector( 'input' ) as HTMLInputElement;
	const type = async ( element: HTMLInputElement, value: string ) => {
		await act( async () => {
			const setter = Object.getOwnPropertyDescriptor(
				HTMLInputElement.prototype,
				'value'
			)?.set;
			setter?.call( element, value );
			element.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		} );
	};

	it( 'shows what is stored', () => {
		expect( input( 'Start times every (minutes)' ).value ).toBe( '30' );
		expect( input( 'Minimum notice (minutes)' ).value ).toBe( '60' );
		expect( input( 'Open for booking up to (days ahead)' ).value ).toBe(
			'60'
		);
	} );

	it( 'saves the rules as numbers', async () => {
		await type( input( 'Start times every (minutes)' ), '15' );
		await type( input( 'Minimum notice (minutes)' ), '0' );
		await act( async () => {
			[ ...container.querySelectorAll( 'button' ) ]
				.find( ( item ) => item.textContent === 'Save booking rules' )
				?.click();
		} );
		await flush();

		expect( puts ).toEqual( [
			{
				slot_step_min: 15,
				min_notice_min: 0,
				max_advance_days: 60,
				staff_choice: 'least_busy',
			},
		] );
	} );
} );
