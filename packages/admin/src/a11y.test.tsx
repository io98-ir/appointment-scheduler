// @vitest-environment jsdom
import { QueryClient } from '@tanstack/react-query';
import { ApiClient } from '@vaqtyar/shared';
import axe from 'axe-core';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { App } from './App';
import { settingsAnswer } from './setup/settingsAnswers';

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

/**
 * Every screen of the admin app, with no data, through axe (T6.3). jsdom
 * has no layout, so colour contrast and anything that needs one is left to
 * the manual audit (docs/05-delivery/06-i18n-a11y-perf-report.md).
 */
const ROUTES = [
	'/',
	'/calendar',
	'/appointments',
	'/customers',
	'/services',
	'/service-categories',
	'/staff',
	'/resources',
	'/locations',
	'/reports',
	'/holidays',
	'/settings',
	'/status',
	'/setup',
	'/nowhere',
];

const SUMMARY = {
	from: '2026-09-01',
	to: '2026-09-30',
	totals: {
		appointments: 2,
		revenue: 500000,
		cancelled: 1,
		no_show: 0,
		cancel_rate: 33.3,
	},
	statuses: { confirmed: 2, cancelled: 1 },
	days: [ { date: '2026-09-10', appointments: 2, revenue: 500000 } ],
	services: [ { id: 1, appointments: 2, revenue: 500000 } ],
	staff: [ { id: 1, appointments: 2, revenue: 500000 } ],
};

const STATUS = {
	versions: { plugin: '1', wordpress: '6.6', php: '8.3', database: '8' },
	checks: [
		{ id: 'cron', status: 'recommended', label: 'Cron', description: 'x' },
	],
	queue: { pending: 0, late: 0, failed: 0 },
	schema: { kernel: 2 },
	modules: [ { id: 'widget', switchable: true, enabled: true } ],
	errors: [ { at: '2026-09-30 10:00:00', channel: 'x', message: 'm' } ],
};

describe( 'accessibility of the admin screens', () => {
	let container: HTMLElement;
	let root: Root;

	beforeEach( () => {
		container = document.createElement( 'div' );
		document.body.append( container );
		root = createRoot( container );
	} );

	afterEach( () => {
		act( () => root.unmount() );
		container.remove();
		window.location.hash = '';
	} );

	it.each( ROUTES )( '%s has no axe violations', async ( route ) => {
		window.location.hash = '#' + route;
		const fetch = async (
			resource: RequestInfo | URL,
			init?: RequestInit
		) => {
			const url = new URL( String( resource ) );
			const path = url.pathname.replace( '/wp-json/x/v1', '' );

			if ( path === '/reports/summary' ) {
				return new Response( JSON.stringify( SUMMARY ) );
			}
			if ( path === '/status' ) {
				return new Response( JSON.stringify( STATUS ) );
			}

			return (
				settingsAnswer( path, init?.method ?? 'GET' ) ??
				new Response( '[]' )
			);
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
					productName="Product"
				/>
			)
		);
		await flush();

		const { violations } = await axe.run( container, {
			rules: {
				// Needs layout, which jsdom does not have.
				'color-contrast': { enabled: false },
				// The app renders inside wp-admin, which owns the page.
				region: { enabled: false },
			},
		} );

		expect(
			violations.map(
				( violation ) =>
					`${ violation.id }: ${ violation.nodes
						.map( ( node ) => node.target.join( ' ' ) )
						.join( ' | ' ) }`
			)
		).toEqual( [] );
	} );
} );
