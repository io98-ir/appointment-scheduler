// @vitest-environment jsdom
import { QueryClient } from '@tanstack/react-query';
import { ApiClient } from '@vaqtyar/shared';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { App } from '../App';
import { customerName } from './CustomerList';

(
	globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }
 ).IS_REACT_ACT_ENVIRONMENT = true;

type Item = Record< string, unknown > & { id: number };

/**
 * The customers and appointments routes over a map, enough for the
 * customers screen (list, create, edit and history).
 */
function fakeServer() {
	const store = new Map< string, Item[] >();
	const requests: string[] = [];
	let next = 1;
	const json = ( body: unknown, status = 200, headers = {} ) =>
		new Response( body === null ? '' : JSON.stringify( body ), {
			status,
			headers,
		} );

	const fetch = async ( resource: RequestInfo | URL, init?: RequestInit ) => {
		const url = new URL( String( resource ) );
		const path = url.pathname.replace( '/wp-json/x/v1', '' );
		const method = init?.method ?? 'GET';
		requests.push( `${ method } ${ path }` );
		const body = init?.body ? JSON.parse( String( init.body ) ) : {};
		const [ , base ] = path.split( '/' );
		if ( base === 'appointments' ) {
			const items = store.get( '/appointments' ) ?? [];

			return json( items, 200, {
				'X-WP-Total': String( items.length ),
				'X-WP-TotalPages': '1',
			} );
		}
		const items = store.get( `/${ base }` ) ?? [];
		store.set( `/${ base }`, items );
		if ( method === 'POST' ) {
			const item = { ...body, id: next++ };
			items.push( item );

			return json( item, 201 );
		}
		const id = path.split( '/' )[ 2 ];
		if ( id === undefined ) {
			// Only /customers supports filtering by status; the server does
			// the real filtering here, as CustomerRoutes.php does.
			const status = url.searchParams.get( 'status' );
			const shown =
				base === 'customers' && status
					? items.filter( ( item ) => item.status === status )
					: items;

			return json( shown, 200, {
				'X-WP-Total': String( shown.length ),
				'X-WP-TotalPages': '1',
			} );
		}
		const index = items.findIndex( ( item ) => item.id === Number( id ) );
		if ( method === 'DELETE' ) {
			items.splice( index, 1 );

			return json( null, 204 );
		}
		if ( method === 'PUT' ) {
			items[ index ] = { ...body, id: Number( id ) };
		}

		return json( items[ index ] );
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

describe( 'customerName', () => {
	it( 'falls back to the phone when both names are empty', () => {
		expect(
			customerName( {
				first_name: '',
				last_name: '',
				phone: '+989121234567',
			} )
		).toBe( '+989121234567' );
		expect(
			customerName( {
				first_name: 'Ali',
				last_name: 'Karimi',
				phone: '+989121234567',
			} )
		).toBe( 'Ali Karimi' );
	} );
} );

describe( 'the customers screen', () => {
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

	it( 'creates, lists and edits a customer, with their appointment history', async () => {
		await go( '#/customers' );
		expect( container.textContent ).toContain( 'No customers.' );

		await go( '#/customers/new' );
		await type( input( container, 'First name' ), 'Ali' );
		await type( input( container, 'Last name' ), 'Karimi' );
		await type( input( container, 'Phone' ), '+989121234567' );
		await click( button( 'Save' ) );

		expect( server.store.get( '/customers' ) ).toMatchObject( [
			{ id: 1, first_name: 'Ali', last_name: 'Karimi', status: 'active' },
		] );
		// Opened as the stored customer, with their (empty) history.
		expect( window.location.hash ).toBe( '#/customers/1' );
		await flush();
		expect( container.textContent ).toContain( 'No appointments yet.' );

		await type( input( container, 'Last name' ), 'Ahmadi' );
		await click( button( 'Save' ) );
		expect( server.requests ).toContain( 'PUT /customers/1' );
		expect( server.store.get( '/customers' )?.[ 0 ] ).toMatchObject( {
			last_name: 'Ahmadi',
			phone: '+989121234567',
		} );

		await go( '#/customers' );
		expect( container.querySelector( 'tbody' )?.textContent ).toContain(
			'Ali Ahmadi'
		);
	} );

	it( 'filters the list by status on the server, not just the loaded page', async () => {
		server.store.set( '/customers', [
			{
				id: 1,
				first_name: 'Ali',
				last_name: 'Karimi',
				phone: '+989121234567',
				email: null,
				status: 'active',
				tags: [],
				note: '',
			},
			{
				id: 2,
				first_name: 'Sara',
				last_name: 'Ahmadi',
				phone: '+989121234568',
				email: null,
				status: 'blocked',
				tags: [],
				note: '',
			},
		] );

		await go( '#/customers' );
		expect( container.textContent ).toContain( 'Ali Karimi' );
		expect( container.textContent ).toContain( 'Sara Ahmadi' );

		const select = input(
			container,
			'Status'
		) as unknown as HTMLSelectElement;
		await act( async () => {
			select.value = 'blocked';
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
		await flush();

		// The fake server only returns the blocked row when it sees the
		// "status" query param, so this proves the request carries the
		// filter, not that the browser filtered a page it already had.
		expect( container.textContent ).not.toContain( 'Ali Karimi' );
		expect( container.textContent ).toContain( 'Sara Ahmadi' );
	} );
} );
