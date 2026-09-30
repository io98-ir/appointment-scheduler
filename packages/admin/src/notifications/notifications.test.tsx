// @vitest-environment jsdom
import { QueryClient } from '@tanstack/react-query';
import { ApiClient, type NotificationTemplate } from '@vaqtyar/shared';
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

const TEMPLATES: NotificationTemplate[] = [
	{
		id: 1,
		trigger: 'booked',
		audience: 'customer',
		channel: 'email',
		offset_min: null,
		subject: 'Booked',
		body: 'Hello {customer_name}',
		enabled: true,
		sms_patterns: {},
	},
	{
		id: 2,
		trigger: 'reminder',
		audience: 'customer',
		channel: 'sms',
		offset_min: 1440,
		subject: '',
		body: 'Tomorrow at {time}',
		enabled: false,
		sms_patterns: { kavenegar: { code: 'remind', args: [ 'time' ] } },
	},
];

describe( 'the notification templates screen', () => {
	let container: HTMLElement;
	let root: Root;
	let requests: Array< { method: string; path: string; body?: string } >;

	beforeEach( () => {
		requests = [];
		window.location.hash = '#/notifications';
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
			if ( path === '/notification-templates' && method === 'GET' ) {
				return new Response( JSON.stringify( TEMPLATES ) );
			}
			if ( path === '/sms' ) {
				return new Response(
					JSON.stringify( {
						order: [ 'kavenegar' ],
						senders: {},
						otp_patterns: {},
						providers: [
							{ id: 'kavenegar', configured: true, secrets: [] },
							{ id: 'ippanel', configured: false, secrets: [] },
						],
					} )
				);
			}
			if ( path.startsWith( '/notification-templates/' ) ) {
				return new Response( JSON.stringify( TEMPLATES[ 1 ] ) );
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

	const button = ( label: string ) =>
		[ ...container.querySelectorAll( 'button' ) ].find(
			( candidate ) => candidate.textContent?.trim() === label
		);

	it( 'lists every template with its event, audience, channel and state', async () => {
		await flush();

		expect( container.querySelector( 'h1' )?.textContent ).toBe(
			'Notifications'
		);
		const rows = [ ...container.querySelectorAll( 'tbody tr' ) ].map(
			( row ) => row.textContent ?? ''
		);
		expect( rows ).toHaveLength( 2 );
		expect( rows[ 0 ] ).toContain( 'Appointment booked' );
		expect( rows[ 1 ] ).toContain( 'Reminder before the appointment' );
		expect( rows[ 1 ] ).toContain( '1 days before' );
		expect( rows[ 1 ] ).toContain( 'Off' );
	} );

	it( 'edits an SMS template, with its pattern for the provider that is set up', async () => {
		await flush();
		await act( async () => {
			button( 'Edit' )?.click();
		} );
		await flush();
		// The first is the email; open the reminder instead.
		await act( async () => {
			button( 'Cancel' )?.click();
		} );
		await act( async () => {
			[ ...container.querySelectorAll( 'button' ) ]
				.filter(
					( candidate ) => candidate.textContent === 'Edit'
				)[ 1 ]
				?.click();
		} );
		await flush();

		const labels = [ ...container.querySelectorAll( 'label' ) ].map(
			( label ) => label.textContent ?? ''
		);
		expect( labels ).toContain( 'Kavenegar: Pattern code' );
		expect(
			labels.some( ( label ) => label.startsWith( 'IPPanel' ) )
		).toBe( false );
		const code = [ ...container.querySelectorAll( 'input' ) ].find(
			( input ) => input.value === 'remind'
		);
		expect( code ).toBeDefined();

		await act( async () => {
			button( 'Save template' )?.click();
		} );
		await flush();

		const put = requests.find( ( request ) => request.method === 'PUT' );
		expect( put?.path ).toBe( '/notification-templates/2' );
		expect( JSON.parse( put?.body ?? '{}' ) ).toEqual( {
			trigger: 'reminder',
			audience: 'customer',
			channel: 'sms',
			offset_min: 1440,
			subject: '',
			body: 'Tomorrow at {time}',
			enabled: false,
			sms_patterns: { kavenegar: { code: 'remind', args: [ 'time' ] } },
		} );
	} );

	it( 'deletes a template', async () => {
		await flush();
		await act( async () => {
			button( 'Delete' )?.click();
		} );
		await flush();

		expect(
			requests.find( ( request ) => request.method === 'DELETE' )?.path
		).toBe( '/notification-templates/1' );
	} );
} );
