// @vitest-environment jsdom
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiClient } from '@vaqtyar/shared';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { ApiContext } from '../api';
import { DEFAULT_DISPLAY, DisplayContext } from '../display';
import { PRESETS } from '../palette';
import { BrandForm } from './BrandForm';
import { DisplayForm } from './DisplayForm';

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

describe( 'the display and palette forms', () => {
	let container: HTMLElement;
	let root: Root;
	let sent: Array< { method: string; path: string; body: unknown } >;

	beforeEach( () => {
		sent = [];
		container = document.createElement( 'div' );
		document.body.append( container );
		root = createRoot( container );
	} );

	afterEach( () => {
		act( () => root.unmount() );
		container.remove();
	} );

	const render = ( ui: React.ReactNode, brand = '' ) => {
		const fetch = async (
			resource: RequestInfo | URL,
			init?: RequestInit
		) => {
			const path = new URL( String( resource ) ).pathname.replace(
				'/wp-json/x/v1',
				''
			);
			const method = init?.method ?? 'GET';
			const body =
				typeof init?.body === 'string' ? JSON.parse( init.body ) : null;
			sent.push( { method, path, body } );

			return new Response(
				JSON.stringify(
					path === '/brand'
						? { name: '', logo_url: '', color: brand }
						: body
				)
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
						<DisplayContext.Provider value={ DEFAULT_DISPLAY }>
							{ ui }
						</DisplayContext.Provider>
					</QueryClientProvider>
				</ApiContext.Provider>
			)
		);
	};
	const select = ( label: string ) =>
		[ ...container.querySelectorAll( 'label' ) ]
			.find( ( item ) => item.textContent === label )
			?.closest( '.components-base-control' )
			?.querySelector( 'select' ) as HTMLSelectElement;
	const choose = async ( label: string, value: string ) => {
		await act( async () => {
			const field = select( label );
			field.value = value;
			field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
	};
	const submit = async ( name: string ) => {
		const button = [ ...container.querySelectorAll( 'button' ) ].find(
			( item ) => item.textContent === name
		);
		await act( async () => {
			button?.click();
		} );
		await flush();
	};

	it( 'saves the calendar and digits and needs no reload', async () => {
		render( <DisplayForm /> );

		await choose( 'Calendar', 'gregorian' );
		await choose( 'Digits', 'persian' );
		await submit( 'Save language and calendar' );

		expect( sent ).toEqual( [
			{
				method: 'PUT',
				path: '/general',
				body: {
					calendar: 'gregorian',
					digits: 'persian',
					language: 'auto',
				},
			},
		] );
		expect( container.textContent ).not.toContain( 'Reload the page' );
	} );

	it( 'asks for a reload after a new language, and does not move a wizard on', async () => {
		const moveOn = vi.fn();
		render( <DisplayForm onSaved={ moveOn } /> );

		await choose( 'Language', 'fa' );
		await submit( 'Save language and calendar' );

		expect( sent[ 0 ]?.body ).toMatchObject( { language: 'fa' } );
		expect( container.textContent ).toContain(
			'Reload the page to use the new language.'
		);
		expect( moveOn ).not.toHaveBeenCalled();
	} );

	it( 'moves a wizard on when only the calendar changed', async () => {
		const moveOn = vi.fn();
		render( <DisplayForm onSaved={ moveOn } /> );

		await choose( 'Calendar', 'gregorian' );
		await submit( 'Save language and calendar' );

		expect( moveOn ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'saves the colour of a palette the owner picks', async () => {
		render( <BrandForm /> );
		await flush();

		const indigo = PRESETS.find( ( preset ) => preset.id === 'indigo' );
		const swatch = [
			...container.querySelectorAll< HTMLButtonElement >(
				'.vqy-palette__option'
			),
		].find( ( item ) => item.textContent === 'Indigo' );
		expect(
			container.querySelectorAll( '.vqy-palette__option' )
		).toHaveLength( PRESETS.length );
		// The default palette starts pressed.
		expect(
			container.querySelector(
				'.vqy-palette__option[aria-pressed="true"]'
			)?.textContent
		).toBe( 'Teal' );

		await act( async () => {
			swatch?.click();
		} );
		await submit( 'Save look' );

		expect(
			sent.find( ( item ) => item.method === 'PUT' )?.body
		).toMatchObject( {
			color: indigo?.color,
		} );
	} );
} );
