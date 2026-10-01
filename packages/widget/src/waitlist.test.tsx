// @vitest-environment jsdom
import { ApiClient } from '@vaqtyar/shared';
import { render } from 'preact';
import { act } from 'preact/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { WaitlistForm } from './WaitlistForm';

function fakeServer( options: { otp: boolean; fail?: string } ) {
	const posts: { path: string; nonce: string | null; body: unknown }[] = [];
	const fetch = async ( resource: RequestInfo | URL, init?: RequestInit ) => {
		const url = new URL( String( resource ) );
		const path = url.pathname.replace( '/wp-json/x/v1', '' );
		if ( init?.method === 'POST' ) {
			const headers = new Headers( init.headers );
			posts.push( {
				path,
				nonce: headers.get( 'X-WP-Nonce' ),
				body: JSON.parse( String( init.body ) ),
			} );
			if ( path === '/waitlist' && options.fail !== undefined ) {
				return new Response(
					JSON.stringify( {
						code: 'day_not_full',
						message: options.fail,
						data: { status: 409 },
					} ),
					{ status: 409 }
				);
			}
			if ( path === '/otp/verify' ) {
				return new Response(
					JSON.stringify( { token: 'SESSION', expires_at: 'x' } )
				);
			}

			return new Response( JSON.stringify( { id: 1 } ), { status: 201 } );
		}
		if ( path === '/nonce' ) {
			return new Response( JSON.stringify( { nonce: 'fresh' } ) );
		}
		if ( path === '/otp/config' ) {
			return new Response(
				JSON.stringify( { required: options.otp, code_length: 6 } )
			);
		}

		return new Response( JSON.stringify( { a: 1, b: 2, token: 'c' } ) );
	};

	return { posts, fetch };
}

async function settle() {
	for ( let i = 0; i < 5; i++ ) {
		await act( async () => {
			await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		} );
	}
}

describe( 'the waiting-list form', () => {
	let container: HTMLElement;

	beforeEach( () => {
		container = document.createElement( 'div' );
		document.body.append( container );
	} );

	afterEach( () => {
		render( null, container );
		container.remove();
	} );

	async function open( server: ReturnType< typeof fakeServer > ) {
		const api = new ApiClient( {
			baseUrl: 'https://example.test/wp-json/x/v1/',
			fetch: server.fetch,
		} );
		await act( () => {
			render(
				<WaitlistForm
					query={ {
						variant: 100,
						location: 1,
						staff: null,
						date: '2027-01-12',
					} }
					clientFor={ () => api }
					digits="latin"
				/>,
				container
			);
		} );
		await settle();
	}

	function type( selector: string, value: string ) {
		const input = container.querySelector< HTMLInputElement >( selector );
		if ( input ) {
			input.value = value;
			input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		}
	}

	const submit = () =>
		container.querySelector< HTMLButtonElement >( 'button[type="submit"]' );

	it( 'sends the phone and the day asked about, and says it is done', async () => {
		const server = fakeServer( { otp: false } );
		await open( server );
		expect( container.textContent ).toContain( 'Nothing is reserved' );
		expect( submit()?.disabled ).toBe( true );

		await act( () => type( 'input[type="tel"]', '09120000000' ) );
		await act( () => submit()?.click() );
		await settle();

		const post = server.posts.find( ( p ) => p.path === '/waitlist' );
		expect( post?.body ).toMatchObject( {
			variant: 100,
			location: 1,
			staff: null,
			date: '2027-01-12',
			phone: '09120000000',
			session_token: null,
		} );
		expect( container.textContent ).toContain( 'We will message you' );
	} );

	it( 'asks for the code first when the site verifies phones', async () => {
		const server = fakeServer( { otp: true } );
		await open( server );

		await act( () => type( 'input[type="tel"]', '09120000000' ) );

		expect( submit()?.disabled ).toBe( true );
		expect( container.textContent ).toContain( 'Send a verification code' );
	} );

	it( 'shows why the request was refused', async () => {
		const server = fakeServer( {
			otp: false,
			fail: 'The day has a time to book.',
		} );
		await open( server );

		await act( () => type( 'input[type="tel"]', '09120000000' ) );
		await act( () => submit()?.click() );
		await settle();

		expect(
			container.querySelector( '[role="alert"]' )?.textContent
		).toContain( 'The day has a time to book.' );
		expect( container.textContent ).not.toContain( 'We will message you' );
	} );
} );
