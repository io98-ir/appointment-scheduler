// @vitest-environment jsdom
import { QueryClient } from '@tanstack/react-query';
import { ApiClient } from '@vaqtyar/shared';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { App } from '../App';
import { daysAgo } from './dates';

(
	globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }
 ).IS_REACT_ACT_ENVIRONMENT = true;

const APPOINTMENT = {
	id: 5,
	code: 'AB12CD34',
	status: 'confirmed',
	payment_status: 'unpaid',
	customer_id: 1,
	customer: {
		id: 1,
		name: '=cmd|calc',
		phone: '+989121234567',
		deleted: false,
	},
	location_id: 1,
	service_id: 10,
	variant_id: 100,
	staff_id: 2,
	start: '2026-10-01T10:00:00+03:30',
	end: '2026-10-01T11:00:00+03:30',
	party_size: 1,
	total: { amount: 1500000, currency: 'IRR' },
};

function summary( from: string, to: string ) {
	const days = Array.from( { length: 30 }, ( _, i ) => ( {
		date: daysAgo( 29 - i ),
		appointments: i === 29 ? 3 : 1,
		revenue: i === 29 ? 300 : 100,
	} ) );

	return {
		from,
		to,
		totals: {
			appointments: 32,
			revenue: 3200,
			cancelled: 4,
			no_show: 0,
			cancel_rate: 12.5,
		},
		statuses: { confirmed: 32, cancelled: 4 },
		days,
		services: [ { id: 10, appointments: 32, revenue: 3200 } ],
		staff: [ { id: 2, appointments: 32, revenue: 3200 } ],
	};
}

function fakeServer() {
	const requests: string[] = [];
	const json = ( body: unknown, headers: Record< string, string > = {} ) =>
		new Response( JSON.stringify( body ), { status: 200, headers } );

	const fetch = async ( resource: RequestInfo | URL ) => {
		const url = new URL( String( resource ) );
		const path = url.pathname.replace( '/wp-json/x/v1', '' );
		requests.push( `${ path }${ url.search }` );

		if ( path === '/reports/summary' ) {
			return json(
				summary(
					url.searchParams.get( 'from' ) ?? '',
					url.searchParams.get( 'to' ) ?? ''
				)
			);
		}
		if ( path === '/appointments' ) {
			return json( [ APPOINTMENT ], {
				'X-WP-Total': '1',
				'X-WP-TotalPages': '1',
			} );
		}
		if ( path === '/services' ) {
			return json( [ { id: 10, name: 'Consultation', variants: [] } ] );
		}
		if ( path === '/staff' ) {
			return json( [ { id: 2, name: 'Sara' } ] );
		}

		return json( [] );
	};

	return { requests, fetch };
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

describe( 'the dashboard and reports', () => {
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
		vi.restoreAllMocks();
	} );

	it( 'shows today, the week, the month and the cancellation rate', async () => {
		await go( '#/' );

		const text = container.textContent ?? '';
		expect( text ).toContain( '3 appointments' );
		expect( text ).toContain( '12.5%' );
		expect( text ).toContain( '4 cancelled' );
		expect(
			server.requests.some(
				( r ) =>
					r.startsWith( '/reports/summary?' ) &&
					r.includes( `to=${ daysAgo( 0 ) }` ) &&
					r.includes( `from=${ daysAgo( 29 ) }` )
			)
		).toBe( true );
		expect(
			container.querySelector( 'a[href="#/appointments/5"]' )
		).not.toBeNull();
	} );

	it( 'names the services and staff in the report', async () => {
		await go( '#/reports' );

		const text = container.textContent ?? '';
		expect( text ).toContain( 'Consultation' );
		expect( text ).toContain( 'Sara' );
		expect( text ).toContain( '32 booked appointments' );
	} );

	it( 'exports the range as a CSV with formulas defused', async () => {
		let saved = '';
		let name = '';
		Object.assign( URL, {
			createObjectURL: ( blob: Blob ) => {
				// Blob.text() drops the byte order mark; keep it to test it.
				void blob.arrayBuffer().then( ( bytes ) => {
					saved = new TextDecoder( 'utf-8', {
						ignoreBOM: true,
					} ).decode( bytes );
				} );

				return 'blob:x';
			},
			revokeObjectURL: () => undefined,
		} );
		vi.spyOn( HTMLAnchorElement.prototype, 'click' ).mockImplementation(
			function ( this: HTMLAnchorElement ) {
				name = this.download;
			}
		);
		await go( '#/reports' );

		const button = [ ...container.querySelectorAll( 'button' ) ].find(
			( element ) => element.textContent === 'Export appointments (CSV)'
		);
		await act( async () => {
			button?.click();
		} );
		await flush();

		expect( name ).toBe(
			`appointments-${ daysAgo( 29 ) }-${ daysAgo( 0 ) }.csv`
		);
		expect( saved.startsWith( '﻿code,start,status' ) ).toBe( true );
		expect( saved ).toContain(
			`AB12CD34,2026-10-01T10:00:00+03:30,confirmed,unpaid,'=cmd|calc,+989121234567,Consultation,Sara,1,1500000`
		);
	} );
} );
