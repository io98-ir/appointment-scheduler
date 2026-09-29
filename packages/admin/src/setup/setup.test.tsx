// @vitest-environment jsdom
import { QueryClient } from '@tanstack/react-query';
import { ApiClient } from '@vaqtyar/shared';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { App } from '../App';
import { readConfig } from '../config';
import { settingsAnswer } from './settingsAnswers';
import { defaultWeek } from './SetupWizard';

(
	globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }
 ).IS_REACT_ACT_ENVIRONMENT = true;

async function flush() {
	for ( let i = 0; i < 5; i++ ) {
		await act( async () => {
			await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		} );
	}
}

describe( 'the working week of a new business', () => {
	it( 'is open Saturday to Thursday and closed on Friday', () => {
		const week = defaultWeek( '09:00', '17:00' );

		expect( week.map( ( rule ) => rule.weekday ) ).toEqual( [
			0, 1, 2, 3, 4, 5,
		] );
		expect( week.every( ( rule ) => rule.kind === 'work' ) ).toBe( true );
	} );
} );

describe( 'the admin config', () => {
	const element = ( config: unknown ) => {
		const div = document.createElement( 'div' );
		div.dataset.config = JSON.stringify( config );

		return div;
	};

	it( 'carries the brand and the product name', () => {
		const config = readConfig(
			element( {
				restUrl: 'https://x.test/',
				nonce: 'n',
				product_name: 'Product',
				brand: {
					name: 'Mine',
					logo_url: 'https://x.test/l.png',
					color: '#112233',
				},
			} )
		);

		expect( config.brand ).toEqual( {
			name: 'Mine',
			logo_url: 'https://x.test/l.png',
			color: '#112233',
		} );
		expect( config.productName ).toBe( 'Product' );
	} );

	it( 'reads a page without a brand as the defaults', () => {
		const config = readConfig(
			element( { restUrl: 'https://x.test/', nonce: 'n' } )
		);

		expect( config.brand ).toEqual( {
			name: '',
			logo_url: '',
			color: '',
		} );
		expect( config.productName ).toBe( '' );
	} );
} );

describe( 'the setup screens', () => {
	let container: HTMLElement;
	let root: Root;
	let requests: string[];
	let onboarding: boolean;

	beforeEach( () => {
		requests = [];
		onboarding = false;
		window.location.hash = '';
		container = document.createElement( 'div' );
		document.body.append( container );
		root = createRoot( container );
		const fetch = async (
			resource: RequestInfo | URL,
			init?: RequestInit
		) => {
			const url = new URL( String( resource ) );
			const path = url.pathname.replace( '/wp-json/x/v1', '' );
			const method = init?.method ?? 'GET';
			requests.push( `${ method } ${ path }` );
			if ( path === '/onboarding' && method === 'GET' ) {
				return new Response( JSON.stringify( { done: onboarding } ) );
			}
			if ( path === '/onboarding' ) {
				onboarding = true;

				return new Response( JSON.stringify( { done: true } ) );
			}

			if ( path === '/reports/summary' ) {
				return new Response(
					JSON.stringify( {
						days: [],
						totals: {
							appointments: 0,
							revenue: 0,
							cancel_rate: 0,
							cancelled: 0,
						},
					} )
				);
			}

			return settingsAnswer( path, method ) ?? new Response( '[]' );
		};
		act( () =>
			root.render(
				<App
					api={
						new ApiClient( {
							baseUrl: 'https://example.test/wp-json/x/v1/',
							fetch: fetch as typeof globalThis.fetch,
						} )
					}
					queryClient={ new QueryClient() }
					brand={ { name: 'Salon', logo_url: '', color: '#112233' } }
					productName="Product"
				/>
			)
		);
	} );

	afterEach( () => {
		act( () => root.unmount() );
		container.remove();
	} );

	it( "shows the owner's name instead of the product name and their accent colour", async () => {
		await flush();

		expect(
			container.querySelector( '.vqy-admin__brand' )?.textContent
		).toBe( 'Salon' );
		expect(
			(
				container.querySelector( '.vqy-admin' ) as HTMLElement
			 ).style.getPropertyValue( '--vqy-accent' )
		).toBe( '#112233' );
	} );

	it( 'falls back to the product name for an empty brand name', async () => {
		act( () => root.unmount() );
		root = createRoot( container );
		act( () =>
			root.render(
				<App
					api={
						new ApiClient( {
							baseUrl: 'https://example.test/wp-json/x/v1/',
						} )
					}
					queryClient={ new QueryClient() }
					productName="Product"
				/>
			)
		);

		expect(
			container.querySelector( '.vqy-admin__brand' )?.textContent
		).toBe( 'Product' );
	} );

	it( 'leads a new site from the dashboard to the wizard', async () => {
		await flush();
		expect( container.textContent ).toContain(
			'Your booking site is not set up yet.'
		);
		const link = container.querySelector( 'a[href="#/setup"]' );
		expect( link ).not.toBeNull();

		await act( async () => {
			window.location.hash = '#/setup';
			window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
		} );
		await flush();

		expect( container.querySelector( 'h1' )?.textContent ).toBe( 'Setup' );
		expect(
			container.querySelector( '[aria-current="step"]' )?.textContent
		).toBe( 'Your brand' );
	} );

	it( 'stops nudging once the wizard is done', async () => {
		onboarding = true;
		await flush();

		expect( container.textContent ).not.toContain( 'not set up yet' );
	} );
} );
