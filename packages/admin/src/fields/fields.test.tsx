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

interface Item {
	id: number;
	scope: string;
	service_id: number | null;
	field_key: string;
	type: string;
	label: string;
	required: boolean;
	options: string[];
	show_if: { field: string; equals: string } | null;
	sort: number;
}

/**
 * `/fields` and `/policies/*`, enough for the settings screen (SettingsPage
 * renders both Policies and Fields). Policies always reads as "not set",
 * since these tests only exercise Fields.
 */
function fakeServer() {
	const items: Item[] = [];
	const requests: string[] = [];
	let next = 1;
	const json = ( body: unknown, status = 200 ) =>
		new Response( body === null ? null : JSON.stringify( body ), {
			status,
		} );

	const fetch = async ( resource: RequestInfo | URL, init?: RequestInit ) => {
		const url = new URL( String( resource ) );
		const path = url.pathname.replace( '/wp-json/x/v1', '' );
		const method = init?.method ?? 'GET';
		requests.push(
			`${ method } ${ path }${ url.search }`.replace( /\?$/, '' )
		);

		if ( path.startsWith( '/policies/' ) ) {
			return json( { config: null } );
		}
		if ( method === 'GET' ) {
			const scope = url.searchParams.get( 'scope' );

			return json( items.filter( ( item ) => item.scope === scope ) );
		}
		if ( method === 'POST' ) {
			const body = JSON.parse( String( init?.body ) ) as Omit<
				Item,
				'id'
			>;
			const item: Item = { ...body, id: next++ };
			items.push( item );

			return json( item, 201 );
		}
		const id = Number( path.split( '/' )[ 2 ] );
		const index = items.findIndex( ( item ) => item.id === id );
		items.splice( index, 1 );

		return json( null, 204 );
	};

	return { items, requests, fetch };
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

describe( 'the global custom fields section', () => {
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

	it( 'adds a text field, lists it and deletes it, scoped to global', async () => {
		await go( '#/settings' );
		expect( container.textContent ).toContain( 'No custom fields yet.' );

		await type( input( container, 'Key' ), 'allergies' );
		await type( input( container, 'Label' ), 'Allergies' );
		await click( button( 'Add' ) );

		expect( server.items ).toMatchObject( [
			{
				scope: 'global',
				service_id: null,
				field_key: 'allergies',
				label: 'Allergies',
			},
		] );
		expect( container.textContent ).toContain( 'Allergies' );
		expect(
			server.requests.some(
				( r ) =>
					r.startsWith( 'GET /fields?' ) &&
					r.includes( 'scope=global' )
			)
		).toBe( true );

		await click( button( 'Delete' ) );

		expect( server.items ).toEqual( [] );
	} );

	it( 'only shows the options field for a choice type, and sends the parsed list', async () => {
		await go( '#/settings' );

		const typeSelect = input(
			container,
			'Type'
		) as unknown as HTMLSelectElement;
		expect( () =>
			input( container, 'Options (comma separated)' )
		).toThrow();

		await act( async () => {
			typeSelect.value = 'select';
			typeSelect.dispatchEvent(
				new Event( 'change', { bubbles: true } )
			);
		} );
		await flush();

		await type( input( container, 'Key' ), 'source' );
		await type( input( container, 'Label' ), 'Source' );
		await type(
			input( container, 'Options (comma separated)' ),
			' friend , ad ,friend'
		);
		await click( button( 'Add' ) );

		expect( server.items ).toMatchObject( [
			{
				field_key: 'source',
				type: 'select',
				options: [ 'friend', 'ad', 'friend' ],
			},
		] );
	} );

	it( 'builds show_if only when a key is given', async () => {
		await go( '#/settings' );

		await type( input( container, 'Key' ), 'a' );
		await type( input( container, 'Label' ), 'A' );
		await click( button( 'Add' ) );

		expect( () => input( container, '…equals' ) ).toThrow();
		await type( input( container, 'Show only when field…' ), 'a' );
		expect( input( container, '…equals' ) ).toBeTruthy();

		await type( input( container, 'Key' ), 'b' );
		await type( input( container, 'Label' ), 'B' );
		await type( input( container, '…equals' ), '1' );
		await click( button( 'Add' ) );

		expect( server.items ).toMatchObject( [
			{ field_key: 'a', show_if: null },
			{ field_key: 'b', show_if: { field: 'a', equals: '1' } },
		] );
	} );
} );
