import { describe, expect, it, vi } from 'vitest';

import { ApiClient, ApiError } from './api-client';

type Call = [ string, RequestInit ];

function clientReturning( response: Response | Error, nonce?: string ) {
	const fetch = vi.fn( async () => {
		if ( response instanceof Error ) {
			throw response;
		}
		return response;
	} );
	const client = new ApiClient( {
		baseUrl: 'https://example.test/wp-json/plugin/v1/',
		fetch: fetch as unknown as typeof globalThis.fetch,
		...( nonce !== undefined && { nonce } ),
	} );

	return { client, calls: () => fetch.mock.calls as unknown as Call[] };
}

function json(
	body: unknown,
	status = 200,
	headers: Record< string, string > = {}
): Response {
	return new Response( JSON.stringify( body ), {
		status,
		headers: { 'Content-Type': 'application/json', ...headers },
	} );
}

describe( 'ApiClient', () => {
	it( 'builds URLs under the plugin namespace, with pretty permalinks or without', () => {
		const pretty = new ApiClient( {
			baseUrl: 'https://example.test/wp-json/plugin/v1/',
		} );
		const plain = new ApiClient( {
			baseUrl: 'https://example.test/?rest_route=/plugin/v1/',
		} );

		expect(
			pretty.url( '/services', {
				page: 2,
				search: 'a b',
				skip: undefined,
			} )
		).toBe(
			'https://example.test/wp-json/plugin/v1/services?page=2&search=a+b'
		);
		expect( plain.url( '/services', { page: 2 } ) ).toBe(
			'https://example.test/?rest_route=%2Fplugin%2Fv1%2Fservices&page=2'
		);
	} );

	it( 'sends JSON with the nonce and returns the parsed body', async () => {
		const { client, calls } = clientReturning(
			json( { id: 7 }, 201 ),
			'abc123'
		);

		const result = await client.post< { id: number } >( '/holds', {
			slot: '10:00',
		} );

		expect( result ).toEqual( { id: 7 } );
		const [ url, init ] = calls()[ 0 ] ?? [];
		expect( url ).toBe( 'https://example.test/wp-json/plugin/v1/holds' );
		expect( init?.method ).toBe( 'POST' );
		expect( init?.body ).toBe( '{"slot":"10:00"}' );
		expect( init?.credentials ).toBe( 'same-origin' );
		expect( init?.headers ).toMatchObject( {
			'X-WP-Nonce': 'abc123',
			'Content-Type': 'application/json',
		} );
	} );

	it( 'sends no nonce header for a guest', async () => {
		const { client, calls } = clientReturning( json( [] ) );

		await client.get( '/availability' );

		expect( calls()[ 0 ]?.[ 1 ].headers ).not.toHaveProperty(
			'X-WP-Nonce'
		);
	} );

	it( 'turns the error envelope into an ApiError', async () => {
		const { client } = clientReturning(
			json(
				{
					code: 'slot_taken',
					message: 'The slot is taken.',
					data: {
						status: 409,
						details: { slot: '10:00' },
						request_id: 'r1',
					},
				},
				409
			)
		);

		const error = await client
			.post( '/holds' )
			.catch( ( e: unknown ) => e );

		expect( error ).toBeInstanceOf( ApiError );
		expect( error ).toMatchObject( {
			code: 'slot_taken',
			message: 'The slot is taken.',
			status: 409,
			details: { slot: '10:00' },
			requestId: 'r1',
		} );
	} );

	it( 'accepts a WordPress error without details or request id', async () => {
		const { client } = clientReturning(
			json(
				{
					code: 'rest_no_route',
					message: 'No route.',
					data: { status: 404 },
				},
				404
			)
		);

		await expect( client.get( '/nothing' ) ).rejects.toMatchObject( {
			code: 'rest_no_route',
			status: 404,
			details: {},
			requestId: null,
		} );
	} );

	it( 'reports a response that is not JSON', async () => {
		const { client } = clientReturning(
			new Response( '<html>Fatal error</html>', { status: 500 } )
		);

		await expect( client.get( '/services' ) ).rejects.toMatchObject( {
			code: 'invalid_response',
			status: 500,
		} );
	} );

	it( 'reports an error body that is not the envelope', async () => {
		const { client } = clientReturning( json( { error: 'nope' }, 502 ) );

		await expect( client.get( '/services' ) ).rejects.toMatchObject( {
			code: 'invalid_response',
			status: 502,
		} );
	} );

	it( 'reports a network failure with status 0', async () => {
		const { client } = clientReturning(
			new TypeError( 'Failed to fetch' )
		);

		await expect( client.get( '/services' ) ).rejects.toMatchObject( {
			code: 'network_error',
			status: 0,
		} );
	} );

	it( 'returns undefined for an empty body', async () => {
		const { client } = clientReturning(
			new Response( null, { status: 204 } )
		);

		await expect( client.delete( '/holds/1' ) ).resolves.toBeUndefined();
	} );

	it( 'reads a page with its totals', async () => {
		const { client, calls } = clientReturning(
			json( [ { id: 1 }, { id: 2 } ], 200, {
				'X-WP-Total': '45',
				'X-WP-TotalPages': '3',
			} )
		);

		const page = await client.list< { id: number } >( '/services', {
			page: 2,
			per_page: 20,
		} );

		expect( page ).toEqual( {
			items: [ { id: 1 }, { id: 2 } ],
			total: 45,
			totalPages: 3,
		} );
		expect( calls()[ 0 ]?.[ 0 ] ).toBe(
			'https://example.test/wp-json/plugin/v1/services?page=2&per_page=20'
		);
	} );

	it( 'rejects a list that is not an array', async () => {
		const { client } = clientReturning( json( { id: 1 } ) );

		await expect( client.list( '/services' ) ).rejects.toMatchObject( {
			code: 'invalid_response',
		} );
	} );
} );
