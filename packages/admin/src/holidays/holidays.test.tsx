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
	calendar: string;
	date: string;
	title: string;
	source: 'manual' | 'dataset';
}

/** The holidays routes over an array, enough for the holidays screen. */
function fakeServer() {
	const items: Item[] = [];
	const json = ( body: unknown, status = 200 ) =>
		new Response( body === null ? '' : JSON.stringify( body ), {
			status,
		} );

	const fetch = async ( resource: RequestInfo | URL, init?: RequestInit ) => {
		const url = new URL( String( resource ) );
		const method = init?.method ?? 'GET';
		if ( method === 'GET' ) {
			const calendar = url.searchParams.get( 'calendar' );

			return json(
				items.filter( ( item ) => item.calendar === calendar )
			);
		}
		if ( method === 'POST' ) {
			const body = JSON.parse( String( init?.body ) ) as Omit<
				Item,
				'source'
			>;
			const index = items.findIndex(
				( item ) =>
					item.calendar === body.calendar && item.date === body.date
			);
			const item: Item = { ...body, source: 'manual' };
			if ( index === -1 ) {
				items.push( item );
			} else {
				items[ index ] = item;
			}

			return json( item, 201 );
		}
		// DELETE /holidays/{calendar}/{date}
		const path = url.pathname.replace( '/wp-json/x/v1', '' );
		const [ , , calendar, date ] = path.split( '/' );
		const index = items.findIndex(
			( item ) => item.calendar === calendar && item.date === date
		);
		if ( index === -1 ) {
			return json(
				{
					code: 'holiday_not_found',
					message: 'No holiday has this calendar and date.',
					data: { status: 404 },
				},
				404
			);
		}
		items.splice( index, 1 );

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

describe( 'the holidays screen', () => {
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

	it( 'adds, lists and deletes a holiday of the chosen calendar', async () => {
		await go( '#/holidays' );
		expect( container.textContent ).toContain(
			'No holidays in this calendar yet.'
		);

		await type( input( container, 'Date' ), '2026-10-05' );
		await type( input( container, 'Title' ), 'Test day' );
		await click( button( 'Add' ) );

		expect( server.items ).toMatchObject( [
			{ calendar: 'ir', date: '2026-10-05', title: 'Test day' },
		] );
		expect( container.textContent ).toContain( 'Test day' );

		await click( button( 'Delete' ) );

		expect( server.items ).toEqual( [] );
	} );

	it( 'reads another calendar when the key changes', async () => {
		server.items.push( {
			calendar: 'work',
			date: '2026-11-01',
			title: 'Company day',
			source: 'manual',
		} );

		await go( '#/holidays' );
		expect( container.textContent ).not.toContain( 'Company day' );

		await type( input( container, 'Calendar' ), 'work' );
		await flush();

		expect( container.textContent ).toContain( 'Company day' );
	} );
} );
