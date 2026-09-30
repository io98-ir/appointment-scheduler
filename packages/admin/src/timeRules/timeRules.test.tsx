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
	service_id: number | null;
	priority: number;
	active: boolean;
	weekdays: number[];
	from: string;
	to: string;
	valid_from: string | null;
	valid_to: string | null;
	percent: number;
}

/**
 * `/time-rules`, plus empty answers for the other sections the settings
 * screen renders (`/policies/*`, `/fields`, `/coupons`).
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
		if ( path === '/fields' || path === '/coupons' ) {
			return json( [] );
		}
		if ( method === 'GET' ) {
			return json( items );
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

describe( 'the time-based prices section of the settings screen', () => {
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

	it( 'adds a rule for chosen weekdays, lists it and deletes it', async () => {
		await go( '#/settings' );
		expect( container.textContent ).toContain(
			'No time-based prices yet.'
		);

		await click( input( container, 'Friday' ) );
		await click( input( container, 'Saturday' ) );
		await type(
			input( container, 'Price change (%, negative for a discount)' ),
			'20'
		);
		await click( button( 'Add time-based price' ) );

		expect( server.items ).toMatchObject( [
			{
				service_id: null,
				weekdays: [ 0, 6 ],
				from: '18:00',
				to: '22:00',
				valid_from: null,
				valid_to: null,
				percent: 20,
				active: true,
			},
		] );
		expect( container.textContent ).toContain( '+20%' );

		await click( button( 'Delete' ) );

		expect( server.items ).toEqual( [] );
	} );

	it( 'sends no weekdays for every day, and a negative percent as a discount', async () => {
		await go( '#/settings' );

		await type( input( container, 'Starts from' ), '09:00' );
		await type( input( container, 'Starts before' ), '12:00' );
		// Nowruz 1406 is 2027-03-21.
		await type( input( container, 'Applies from date' ), '1406/01/01' );
		await type(
			input( container, 'Price change (%, negative for a discount)' ),
			'-10'
		);
		await click( button( 'Add time-based price' ) );

		expect( server.items ).toMatchObject( [
			{
				weekdays: [],
				from: '09:00',
				to: '12:00',
				valid_from: '2027-03-21',
				valid_to: null,
				percent: -10,
			},
		] );
	} );
} );
