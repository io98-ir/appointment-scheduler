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

type Item = Record< string, unknown > & { id: number };

/**
 * The catalog and schedule routes over a map, enough for the screens.
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
		if ( base === 'schedules' ) {
			return json( { rules: method === 'PUT' ? body.rules : [] } );
		}
		if ( base === 'schedule-exceptions' ) {
			return json( [] );
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
			return json( items, 200, {
				'X-WP-Total': String( items.length ),
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

describe( 'the catalog screens', () => {
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

	it( 'creates, lists, edits and deletes a location', async () => {
		await go( '#/locations' );
		expect( container.textContent ).toContain( 'Nothing here yet.' );

		await go( '#/locations/new' );
		await type( input( container, 'Name' ), 'Main branch' );
		await click( button( 'Save' ) );

		expect( server.store.get( '/locations' ) ).toMatchObject( [
			{ id: 1, name: 'Main branch', timezone: 'Asia/Tehran' },
		] );
		// Opened as the stored item, with its weekly hours.
		expect( window.location.hash ).toBe( '#/locations/1' );
		await flush();
		expect( container.textContent ).toContain( 'Weekly hours' );

		await type( input( container, 'Name' ), 'North branch' );
		await click( button( 'Save' ) );
		expect( server.requests ).toContain( 'PUT /locations/1' );
		// PUT replaces the item: the fields the form keeps went along.
		expect( server.store.get( '/locations' )?.[ 0 ] ).toMatchObject( {
			name: 'North branch',
			holiday_calendar: 'ir',
			sort: 0,
		} );

		await go( '#/locations' );
		expect( container.querySelector( 'tbody' )?.textContent ).toContain(
			'North branch'
		);
		await click( button( 'Delete' ) );
		// The modal asks first; its own Delete confirms.
		const confirm = [
			...document.querySelectorAll( '.components-modal__content button' ),
		].find( ( element ) => element.textContent === 'Delete' );
		await click( confirm );
		expect( server.store.get( '/locations' ) ).toEqual( [] );
	} );

	it( 'saves a weekly schedule for a staff member', async () => {
		server.store.set( '/staff', [
			{
				id: 4,
				name: 'Dr. Karimi',
				color: '#112233',
				wp_user_id: null,
				location_id: null,
				title: '',
				email: null,
				phone: null,
				avatar_id: null,
				bio: '',
				status: 'active',
				sort: 0,
			},
		] );
		await go( '#/staff/4' );

		await click( button( 'Add hours' ) );
		await click( button( 'Save hours' ) );

		expect( server.requests ).toContain( 'PUT /schedules/staff/4' );
		expect( container.textContent ).toContain( 'Time off and extra hours' );
	} );
} );
