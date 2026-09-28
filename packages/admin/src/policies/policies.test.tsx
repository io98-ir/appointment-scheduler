// @vitest-environment jsdom
import { QueryClient } from '@tanstack/react-query';
import { ApiClient } from '@vaqtyar/shared';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { App } from '../App';

(
	globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }
 ).IS_REACT_ACT_ENVIRONMENT = true;

/**
 * GET|PUT|DELETE /policies/{type}/{service_id}, enough for the settings
 * screen: one config per path, like PolicyRoutes.php's upsert.
 */
function fakeServer() {
	const store = new Map< string, unknown >();
	const requests: string[] = [];
	// A 204 must have a null body, not even "": the Response constructor
	// throws otherwise (a real fetch() never hits this, only this fake).
	const json = ( body: unknown, status = 200 ) =>
		new Response( body === null ? null : JSON.stringify( body ), {
			status,
		} );

	const fetch = async ( resource: RequestInfo | URL, init?: RequestInit ) => {
		const url = new URL( String( resource ) );
		const path = url.pathname.replace( '/wp-json/x/v1', '' );
		const method = init?.method ?? 'GET';
		requests.push( `${ method } ${ path }` );

		if ( method === 'GET' ) {
			return json( { config: store.get( path ) ?? null } );
		}
		if ( method === 'PUT' ) {
			const body = init?.body ? JSON.parse( String( init.body ) ) : {};
			store.set( path, body );

			return json( { config: body } );
		}
		store.delete( path );

		return json( null, 204 );
	};

	return { store, requests, fetch };
}

async function flush() {
	for ( let i = 0; i < 5; i++ ) {
		await act( async () => {
			await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		} );
	}
}

async function go( hash: string ) {
	await act( async () => {
		window.location.hash = hash;
		window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
	} );
	await flush();
}

function within( heading: string ): HTMLElement {
	const legend = [ ...document.querySelectorAll( 'legend' ) ].find(
		( element ) => element.textContent === heading
	);
	const fieldset = legend?.closest( 'fieldset' );
	if ( ! fieldset ) {
		throw new Error( `No "${ heading }" section.` );
	}

	return fieldset as HTMLElement;
}

async function click( element: Element | null | undefined ) {
	await act( async () => {
		( element as HTMLElement ).click();
	} );
	await flush();
}

describe( 'the global policy settings screen', () => {
	let container: HTMLElement;
	let root: Root;
	let server: ReturnType< typeof fakeServer >;

	beforeEach( () => {
		window.location.hash = '';
		server = fakeServer();
		container = document.createElement( 'div' );
		document.body.append( container );
		root = createRoot( container );
		act( () =>
			root.render(
				<App
					api={
						new ApiClient( {
							baseUrl: 'https://example.test/wp-json/x/v1/',
							fetch: server.fetch,
						} )
					}
					queryClient={
						new QueryClient( {
							defaultOptions: { queries: { retry: false } },
						} )
					}
				/>
			)
		);
	} );

	afterEach( () => {
		act( () => root.unmount() );
		container.remove();
	} );

	it( 'reads an unset policy as lenient, saves it, then clears it back', async () => {
		await go( '#/settings' );
		const cancellation = within( 'Cancellation' );
		expect( cancellation.textContent ).toContain(
			'cancelling and rescheduling are free until the start, fully refunded'
		);

		await click(
			[ ...cancellation.querySelectorAll( 'button' ) ].find(
				( button ) => button.textContent === 'Add a tier'
			)
		);
		await click(
			[ ...cancellation.querySelectorAll( 'button' ) ].find(
				( button ) => button.textContent === 'Save'
			)
		);

		expect( server.requests ).toContain( 'PUT /policies/cancellation/0' );
		expect( server.store.get( '/policies/cancellation/0' ) ).toEqual( {
			notice_hours: 24,
			refund: [
				{ hours: 48, percent: 100 },
				{ hours: 24, percent: 50 },
				{ hours: 0, percent: 100 },
			],
		} );
		expect( cancellation.textContent ).not.toContain( 'Not set' );

		await click(
			[ ...cancellation.querySelectorAll( 'button' ) ].find(
				( button ) => button.textContent === 'Clear'
			)
		);
		expect( server.requests ).toContain(
			'DELETE /policies/cancellation/0'
		);
		expect( cancellation.textContent ).toContain(
			'cancelling and rescheduling are free until the start, fully refunded'
		);
	} );
} );
