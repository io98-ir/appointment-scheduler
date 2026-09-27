// @vitest-environment jsdom
import { QueryClient } from '@tanstack/react-query';
import { ApiClient } from '@vaqtyar/shared';
import { dispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { App, sectionOf } from './App';
import { routeFromHash } from './router';

// React's act() warns unless the test environment says it supports it.
(
	globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }
 ).IS_REACT_ACT_ENVIRONMENT = true;

async function go( hash: string ) {
	await act( async () => {
		window.location.hash = hash;
		window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
	} );
}

describe( 'the admin shell', () => {
	let container: HTMLElement;
	let root: Root;

	beforeEach( () => {
		window.location.hash = '';
		window.localStorage.clear();
		container = document.createElement( 'div' );
		document.body.append( container );
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
				/>
			)
		);
	} );

	afterEach( () => {
		act( () => root.unmount() );
		container.remove();
	} );

	const title = () => container.querySelector( 'h1' )?.textContent;
	const current = () =>
		container.querySelector( '[aria-current="page"]' )?.textContent;

	it( 'renders the dashboard on an empty hash', () => {
		expect( title() ).toBe( 'Dashboard' );
		expect( current() ).toBe( 'Dashboard' );
	} );

	it( 'navigates between sections and marks the current one', async () => {
		const links = [ ...container.querySelectorAll( 'nav a' ) ].map( ( a ) =>
			a.getAttribute( 'href' )
		);
		expect( links ).toContain( '#/appointments' );

		await go( '#/appointments' );
		expect( title() ).toBe( 'Appointments' );
		expect( current() ).toBe( 'Appointments' );

		await go( '#/services/7' );
		expect( current() ).toBe( 'Services' );
	} );

	it( 'says so for an unknown route, and keeps the menu', async () => {
		await go( '#/no-such-page' );

		expect( title() ).toBe( 'Page not found' );
		expect( current() ).toBeUndefined();
		expect( container.querySelector( 'nav' ) ).not.toBeNull();
	} );

	it( 'follows the system theme until the user picks one', async () => {
		const app = () => container.querySelector( '.vqy-admin' );
		expect( app()?.getAttribute( 'data-theme' ) ).toBe( 'auto' );

		const select = container.querySelector( 'select' )!;
		await act( async () => {
			select.value = 'dark';
			select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		expect( app()?.getAttribute( 'data-theme' ) ).toBe( 'dark' );
		expect( window.localStorage.getItem( 'vqy-admin-theme' ) ).toBe(
			'dark'
		);
	} );

	it( 'shows a snackbar notice', async () => {
		await act( async () => {
			await dispatch( noticesStore ).createErrorNotice( 'Not saved', {
				type: 'snackbar',
			} );
		} );

		expect( container.textContent ).toContain( 'Not saved' );
	} );
} );

describe( 'sectionOf', () => {
	it.each( [
		[ '/', '/' ],
		[ '/services', '/services' ],
		[ '/services/7', '/services' ],
		[ '/servicesx', undefined ],
		[ '/nope', undefined ],
	] )( 'puts %j in %j', ( route, path ) => {
		expect( sectionOf( route )?.path ).toBe( path );
	} );
} );

describe( 'routeFromHash', () => {
	it.each( [
		[ '', '/' ],
		[ '#', '/' ],
		[ '#/', '/' ],
		[ '#/services/7', '/services/7' ],
		[ '#top', '/' ],
	] )( 'reads %j as %j', ( hash, route ) => {
		expect( routeFromHash( hash ) ).toBe( route );
	} );
} );
