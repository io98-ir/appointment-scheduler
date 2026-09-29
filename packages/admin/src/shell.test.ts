import { ApiError } from '@vaqtyar/shared';
import { describe, expect, it } from 'vitest';

import { readConfig } from './config';
import { createQueryClient, errorMessage, shouldRetry } from './query';
import { readTheme } from './theme';

describe( 'shouldRetry', () => {
	it( 'gives up on a client error at once', () => {
		expect( shouldRetry( 0, new ApiError( 'forbidden', 'No', 403 ) ) ).toBe(
			false
		);
	} );

	it.each( [
		new ApiError( 'network_error', '', 0 ),
		new ApiError( 'internal', 'Oops', 500 ),
		new Error( 'boom' ),
	] )( 'retries %o twice', ( error ) => {
		expect( shouldRetry( 0, error ) ).toBe( true );
		expect( shouldRetry( 1, error ) ).toBe( true );
		expect( shouldRetry( 2, error ) ).toBe( false );
	} );
} );

describe( 'errorMessage', () => {
	it( "shows the server's message", () => {
		expect(
			errorMessage( new ApiError( 'slot_taken', 'Slot taken', 409 ) )
		).toBe( 'Slot taken' );
	} );

	it.each( [
		[ new ApiError( 'network_error', 'raw', 0 ), 'could not be reached' ],
		[ new ApiError( 'invalid_response', 'raw', 502 ), 'went wrong' ],
		[ new Error( 'TypeError: x is undefined' ), 'went wrong' ],
		[ 'a string', 'went wrong' ],
	] )( 'words %o for the user', ( error, text ) => {
		expect( errorMessage( error ) ).toContain( text );
	} );
} );

describe( 'createQueryClient', () => {
	it( 'reports a failed mutation', async () => {
		const messages: string[] = [];
		const client = createQueryClient( ( m ) => messages.push( m ) );

		await expect(
			client
				.getMutationCache()
				.build( client, {
					mutationFn: () =>
						Promise.reject(
							new ApiError( 'bad', 'Bad input', 422 )
						),
				} )
				.execute( undefined )
		).rejects.toThrow();

		expect( messages ).toEqual( [ 'Bad input' ] );
	} );
} );

describe( 'readConfig', () => {
	const element = ( config?: string ) => {
		const dataset: Record< string, string > = {};
		if ( config !== undefined ) {
			dataset.config = config;
		}

		return { dataset } as unknown as HTMLElement;
	};

	it( 'reads the config the page rendered', () => {
		expect(
			readConfig( element( '{"restUrl":"https://a.test/","nonce":"n"}' ) )
		).toEqual( {
			restUrl: 'https://a.test/',
			nonce: 'n',
			brand: { name: '', logo_url: '', color: '' },
			productName: '',
		} );
	} );

	it.each( [ undefined, '', 'not json', '{"restUrl":1,"nonce":"n"}' ] )(
		'rejects %j',
		( config ) => {
			expect( () => readConfig( element( config ) ) ).toThrow();
		}
	);
} );

describe( 'readTheme', () => {
	const storage = ( value: string | null ) =>
		( { getItem: () => value } ) as unknown as Storage;

	it.each( [
		[ 'dark', 'dark' ],
		[ 'light', 'light' ],
		[ 'auto', 'auto' ],
		[ 'purple', 'auto' ],
		[ null, 'auto' ],
	] )( 'reads %j as %j', ( value, theme ) => {
		expect( readTheme( storage( value ) ) ).toBe( theme );
	} );

	it( 'falls back when storage throws or is missing', () => {
		const throwing = {
			getItem: () => {
				throw new Error( 'denied' );
			},
		} as unknown as Storage;

		expect( readTheme( throwing ) ).toBe( 'auto' );
		expect( readTheme( undefined ) ).toBe( 'auto' );
	} );
} );
