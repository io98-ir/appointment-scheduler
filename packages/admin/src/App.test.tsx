// @vitest-environment jsdom
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import { App } from './App';
import { routeFromHash } from './router';

// React's act() warns unless the test environment says it supports it.
(
	globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }
 ).IS_REACT_ACT_ENVIRONMENT = true;

describe( 'the admin shell', () => {
	let container: HTMLElement;
	let root: Root;

	beforeEach( () => {
		window.location.hash = '';
		container = document.createElement( 'div' );
		document.body.append( container );
		root = createRoot( container );
	} );

	afterEach( () => {
		act( () => root.unmount() );
		container.remove();
	} );

	it( 'renders the dashboard on an empty hash', () => {
		act( () => root.render( <App /> ) );

		expect( container.querySelector( 'h1' )?.textContent ).toBe(
			'Dashboard'
		);
	} );

	it( 'follows the hash', async () => {
		act( () => root.render( <App /> ) );

		await act( async () => {
			window.location.hash = '#/no-such-page';
			window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
		} );

		expect( container.querySelector( 'h1' )?.textContent ).toBe(
			'Page not found'
		);
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
