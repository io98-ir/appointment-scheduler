// @vitest-environment jsdom
import { QueryClient } from '@tanstack/react-query';
import { ApiClient } from '@vaqtyar/shared';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { App } from '../App';
import { settingsAnswer } from '../setup/settingsAnswers';

(
	globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }
 ).IS_REACT_ACT_ENVIRONMENT = true;

interface Item {
	id: number;
	code: string;
	type: string;
	value: number;
	active: boolean;
	valid_from: string | null;
	valid_to: string | null;
	max_uses: number | null;
	used: number;
	service_ids: number[] | null;
}

/**
 * `/coupons`, plus empty answers for the other sections the settings screen
 * renders (`/policies/*`, `/fields`, `/time-rules`).
 */
function fakeServer() {
	const items: Item[] = [];
	let next = 1;
	const json = ( body: unknown, status = 200 ) =>
		new Response( body === null ? null : JSON.stringify( body ), {
			status,
		} );

	const fetch = async ( resource: RequestInfo | URL, init?: RequestInit ) => {
		const url = new URL( String( resource ) );
		const path = url.pathname.replace( '/wp-json/x/v1', '' );
		const method = init?.method ?? 'GET';
		const answer = settingsAnswer( path, method );
		if ( answer ) {
			return answer;
		}

		if ( path.startsWith( '/policies/' ) ) {
			return json( { config: null } );
		}
		if ( path === '/fields' || path === '/time-rules' ) {
			return json( [] );
		}
		if ( method === 'GET' ) {
			return json( items );
		}
		if ( method === 'POST' ) {
			const body = JSON.parse( String( init?.body ) ) as Omit<
				Item,
				'id' | 'used'
			>;
			const item: Item = { ...body, used: 0, id: next++ };
			items.unshift( item );

			return json( item, 201 );
		}
		const id = Number( path.split( '/' )[ 2 ] );
		items.splice(
			items.findIndex( ( item ) => item.id === id ),
			1
		);

		return json( null, 204 );
	};

	return { items, fetch };
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

function input( container: HTMLElement, label: string ): HTMLInputElement {
	const found = [ ...container.querySelectorAll( 'label' ) ].find(
		( element ) => element.textContent === label
	);
	const control = found && document.getElementById( found.htmlFor );
	if ( ! control ) {
		throw new Error( `No field labelled ${ label }.` );
	}

	return control as HTMLInputElement;
}

async function type( element: HTMLInputElement, value: string ) {
	await act( async () => {
		const setter = Object.getOwnPropertyDescriptor(
			HTMLInputElement.prototype,
			'value'
		)!.set!;
		setter.call( element, value );
		element.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	} );
}

async function click( element: Element | null | undefined ) {
	await act( async () => {
		( element as HTMLElement ).click();
	} );
	await flush();
}

function button( text: string ): HTMLElement | undefined {
	return [ ...document.querySelectorAll( 'button' ) ].find(
		( element ) => element.textContent === text
	);
}

describe( 'the coupons section of the settings screen', () => {
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

	it( 'adds a coupon, lists it and deletes it', async () => {
		await go( '#/settings' );
		expect( container.textContent ).toContain( 'No coupons yet.' );

		await type( input( container, 'Coupon code' ), 'NOWRUZ' );
		await type( input( container, 'Discount value' ), '15' );
		await click( button( 'Add coupon' ) );

		expect( server.items ).toMatchObject( [
			{
				code: 'NOWRUZ',
				type: 'percent',
				value: 15,
				active: true,
				valid_from: null,
				valid_to: null,
				max_uses: null,
				service_ids: null,
			},
		] );
		expect( container.textContent ).toContain( 'NOWRUZ' );
		expect( container.textContent ).toContain( '15%' );

		await click( button( 'Delete' ) );

		expect( server.items ).toEqual( [] );
	} );

	it( 'sends the limit and the window as UTC, and an unlimited coupon as null', async () => {
		await go( '#/settings' );

		await type( input( container, 'Coupon code' ), 'LIMITED' );
		await type(
			input( container, 'Maximum uses (empty for unlimited)' ),
			'5'
		);
		await type( input( container, 'Valid from' ), '2027-03-20T08:30' );
		await click( button( 'Add coupon' ) );

		const [ coupon ] = server.items;
		expect( coupon?.max_uses ).toBe( 5 );
		expect( coupon?.valid_from ).toBe(
			new Date( '2027-03-20T08:30' ).toISOString()
		);
		expect( coupon?.valid_to ).toBeNull();
	} );
} );
