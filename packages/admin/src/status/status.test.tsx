// @vitest-environment jsdom
import { QueryClient } from '@tanstack/react-query';
import { ApiClient, type SystemStatus } from '@vaqtyar/shared';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { App } from '../App';

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

const STATUS: SystemStatus = {
	versions: {
		plugin: '1.0.0',
		wordpress: '6.6.1',
		php: '8.3.0',
		database: '8.0.36',
	},
	checks: [
		{
			id: 'database_engine',
			status: 'good',
			label: 'The database uses InnoDB',
			description: 'Bookings are written in transactions.',
		},
		{
			id: 'cron',
			status: 'recommended',
			label: 'WordPress cron runs on page visits',
			description: 'Use a system cron.',
		},
	],
	queue: { pending: 3, late: 0, failed: 1 },
	schema: { kernel: 2, booking: 5 },
	modules: [
		{ id: 'booking', switchable: false, enabled: true },
		{ id: 'notifications', switchable: true, enabled: true },
		{ id: 'widget', switchable: true, enabled: true },
	],
	errors: [
		{
			at: '2026-09-30 10:00:00',
			channel: 'payments',
			message: 'Verify failed',
		},
	],
};

describe( 'the system status screen', () => {
	let container: HTMLElement;
	let root: Root;
	let requests: Array< { method: string; path: string; body?: string } >;

	beforeEach( () => {
		requests = [];
		window.location.hash = '#/status';
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
			requests.push( { method, path, body: init?.body as string } );
			if ( path === '/status' ) {
				return new Response( JSON.stringify( STATUS ) );
			}
			if ( path === '/modules/widget' ) {
				return new Response(
					JSON.stringify( {
						modules: STATUS.modules.map( ( module ) =>
							module.id === 'widget'
								? { ...module, enabled: false }
								: module
						),
					} )
				);
			}

			return new Response( '[]' );
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
				/>
			)
		);
	} );

	afterEach( () => {
		act( () => root.unmount() );
		container.remove();
		window.location.hash = '';
	} );

	it( 'shows the checks, the queue, the errors and the versions', async () => {
		await flush();

		const text = container.textContent ?? '';
		expect( container.querySelector( 'h1' )?.textContent ).toBe(
			'System status'
		);
		expect( text ).toContain( 'The database uses InnoDB' );
		expect( text ).toContain( 'Should be improved' );
		expect(
			container.querySelector( '.vqy-admin__check--recommended' )
		).not.toBeNull();
		expect( text ).toContain( 'Verify failed' );
		expect( text ).toContain( '8.3.0' );
		expect( text ).toContain( 'booking: 5' );
	} );

	it( 'offers a toggle for the optional modules only', async () => {
		await flush();

		const toggles = [
			...container.querySelectorAll( 'input[type="checkbox"]' ),
		];
		expect( toggles ).toHaveLength( 2 );
	} );

	it( 'turns a module off and shows it off', async () => {
		await flush();
		const widget = container.querySelectorAll(
			'input[type="checkbox"]'
		)[ 1 ] as HTMLInputElement;
		expect( widget.checked ).toBe( true );

		await act( async () => {
			widget.click();
		} );
		await flush();

		expect(
			requests.find( ( request ) => request.method === 'PUT' )
		).toEqual( {
			method: 'PUT',
			path: '/modules/widget',
			body: JSON.stringify( { enabled: false } ),
		} );
		expect(
			(
				container.querySelectorAll(
					'input[type="checkbox"]'
				)[ 1 ] as HTMLInputElement
			 ).checked
		).toBe( false );
	} );
} );
