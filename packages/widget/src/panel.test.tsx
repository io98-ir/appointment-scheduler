// @vitest-environment jsdom
import { ApiClient } from '@vaqtyar/shared';
import { render } from 'preact';
import { act } from 'preact/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { Panel } from './Panel';

const ITEM = {
	id: 5,
	code: 'AB12CD34',
	status: 'confirmed',
	payment_status: 'unpaid',
	customer_id: 1,
	customer: null,
	location_id: 1,
	service_id: 10,
	variant_id: 100,
	staff_id: 2,
	start: '2027-01-10T10:00:00+03:30',
	end: '2027-01-10T11:00:00+03:30',
	party_size: 1,
	total: { amount: 1500000, currency: 'IRR' },
	cancel: {
		allowed: true,
		reason_code: null,
		refund_percent: 50,
		refund: { amount: 0, currency: 'IRR' },
	},
	reschedule: {
		allowed: false,
		reason_code: 'policy.reschedule_limit_reached',
		refund_percent: 0,
		refund: { amount: 0, currency: 'IRR' },
	},
};

function fakeServer() {
	const calls: {
		method: string;
		path: string;
		headers: Headers;
		body: unknown;
	}[] = [];
	const json = ( body: unknown, status = 200 ) =>
		new Response( JSON.stringify( body ), { status } );

	const fetch = async ( resource: RequestInfo | URL, init?: RequestInit ) => {
		const url = new URL( String( resource ) );
		const path = url.pathname.replace( '/wp-json/x/v1', '' );
		const method = init?.method ?? 'GET';
		calls.push( {
			method,
			path,
			headers: new Headers( init?.headers ),
			body: init?.body ? JSON.parse( String( init.body ) ) : null,
		} );
		if ( path === '/nonce' ) {
			return json( { nonce: 'fresh' } );
		}
		if ( path === '/my/appointments' ) {
			return json( [ ITEM ] );
		}
		if ( path.endsWith( '/cancel' ) ) {
			return json( { id: 5, status: 'cancelled' } );
		}

		return json( [] );
	};

	return { calls, fetch };
}

async function settle() {
	for ( let i = 0; i < 5; i++ ) {
		await act( async () => {
			await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		} );
	}
}

describe( 'the customer panel', () => {
	let container: HTMLElement;
	let server: ReturnType< typeof fakeServer >;

	beforeEach( () => {
		window.sessionStorage.clear();
		server = fakeServer();
		container = document.createElement( 'div' );
		document.body.append( container );
	} );

	afterEach( () => {
		render( null, container );
		container.remove();
	} );

	async function open() {
		const api = new ApiClient( {
			baseUrl: 'https://example.test/wp-json/x/v1/',
			fetch: server.fetch,
		} );
		await act( () => {
			render(
				<Panel config={ { restUrl: 'x' } } api={ api } />,
				container
			);
		} );
		await settle();
	}

	const text = () => container.textContent ?? '';
	const button = ( label: string ) =>
		[ ...container.querySelectorAll( 'button' ) ].find(
			( element ) => element.textContent === label
		);

	it( 'asks for the phone number first when there is no session', async () => {
		await open();

		expect( text() ).toContain( 'Mobile number' );
		expect( text() ).toContain( 'Send a verification code' );
		expect(
			server.calls.some( ( call ) => call.path === '/my/appointments' )
		).toBe( false );
	} );

	it( 'lists the appointments with what the policy says, and cancels after a confirmation', async () => {
		window.sessionStorage.setItem(
			'vqy-panel-session',
			JSON.stringify( { token: 'TOKEN', expiresAt: Date.now() + 60000 } )
		);
		await open();

		expect( text() ).toContain( 'Confirmed' );
		expect( text() ).toContain( '1,500,000 IRR' );
		expect( text() ).toContain( 'Tracking code: AB12CD34' );
		// The move is refused by the policy: its reason is shown instead of a button.
		expect( button( 'Move' ) ).toBeUndefined();
		expect( text() ).toContain(
			'already been moved as many times as allowed'
		);

		await act( () => button( 'Cancel appointment' )?.click() );
		expect( text() ).toContain( '50% of what you paid is refunded' );
		await act( () => button( 'Yes, cancel it' )?.click() );
		await settle();

		expect(
			server.calls.some(
				( call ) =>
					call.method === 'POST' &&
					call.path === '/my/appointments/5/cancel'
			)
		).toBe( true );
	} );

	it( 'hands the customer a calendar file for a confirmed appointment', async () => {
		window.sessionStorage.setItem(
			'vqy-panel-session',
			JSON.stringify( { token: 'TOKEN', expiresAt: Date.now() + 60000 } )
		);
		const blobs: Blob[] = [];
		URL.createObjectURL = ( blob: Blob | MediaSource ) => {
			blobs.push( blob as Blob );

			return 'blob:x';
		};
		URL.revokeObjectURL = () => undefined;
		const clicked: string[] = [];
		HTMLAnchorElement.prototype.click = function click() {
			clicked.push( this.download );
		};
		await open();

		await act( () => button( 'Add to calendar' )?.click() );

		expect( clicked ).toEqual( [ 'AB12CD34.ics' ] );
		const file = await blobs[ 0 ]?.text();
		expect( file ).toContain( 'BEGIN:VEVENT' );
		expect( file ).toContain( 'UID:AB12CD34' );
		expect( file ).toContain( 'DTSTART:20270110T063000Z' );
	} );

	it( 'signs out and forgets the session', async () => {
		window.sessionStorage.setItem(
			'vqy-panel-session',
			JSON.stringify( { token: 'TOKEN', expiresAt: Date.now() + 60000 } )
		);
		await open();

		await act( () => button( 'Sign out' )?.click() );

		expect(
			window.sessionStorage.getItem( 'vqy-panel-session' )
		).toBeNull();
		expect( text() ).toContain( 'Send a verification code' );
	} );
} );
