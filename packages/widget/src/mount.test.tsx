// @vitest-environment jsdom
import { afterEach, describe, expect, it } from 'vitest';

import { mount, readConfig } from './mount';

describe( 'mount', () => {
	afterEach( () => {
		document.body.innerHTML = '';
	} );

	it( 'renders a widget into each element and removes it again', () => {
		const first = document.createElement( 'div' );
		const second = document.createElement( 'div' );
		document.body.append( first, second );

		mount( first, {} );
		const unmount = mount( second, {} );

		expect( first.querySelector( 'section.vqy-widget' ) ).not.toBeNull();
		expect(
			second.querySelector( 'section' )?.getAttribute( 'aria-label' )
		).toBe( 'Book an appointment' );

		unmount();

		expect( second.innerHTML ).toBe( '' );
		expect( first.querySelector( 'section' ) ).not.toBeNull();
	} );
} );

describe( 'readConfig', () => {
	it.each( [
		[ '{"service":7}', { service: 7 } ],
		[ null, {} ],
		[ 'not json', {} ],
		[ '[1,2]', {} ],
		[ '"text"', {} ],
	] )( 'reads %j', ( value, config ) => {
		const element = document.createElement( 'div' );
		if ( value !== null ) {
			element.setAttribute( 'data-config', value );
		}

		expect( readConfig( element, 'data-config' ) ).toEqual( config );
	} );
} );
