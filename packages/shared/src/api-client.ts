import type { ErrorEnvelope } from './api-types';

export interface ApiClientConfig {
	/**
	 * The plugin's REST base from PHP's rest_url(): ".../wp-json/{namespace}/",
	 * or ".../?rest_route=/{namespace}/" on a site without pretty permalinks.
	 */
	baseUrl: string;
	/**
	 * A wp_rest nonce, for a logged-in user only. WordPress checks a nonce a
	 * guest sends too, so a stale one baked into a cached page would turn every
	 * guest request into a 403: leave it out for guests.
	 */
	nonce?: string;
	/**
	 * A phone session token (POST /otp/verify), for the customer panel: sent as
	 * X-Phone-Session, which names the customer.
	 */
	session?: string;
	/** For tests. */
	fetch?: typeof fetch;
}

export type Query = Record< string, string | number | boolean | undefined >;

export interface Page< T > {
	items: T[];
	total: number;
	totalPages: number;
}

/**
 * Every failure of a request: the error envelope the server sent
 * (architecture §8), or a code of our own when there was none:
 * network_error (status 0) or invalid_response.
 */
export class ApiError extends Error {
	constructor(
		readonly code: string,
		message: string,
		readonly status: number,
		readonly details: Record< string, unknown > = {},
		readonly requestId: string | null = null
	) {
		super( message );
		this.name = 'ApiError';
	}
}

/**
 * JSON over fetch against the plugin's REST API, for the admin and the
 * widget alike (so no @wordpress/api-fetch, which the front end would have
 * to load).
 */
export class ApiClient {
	private readonly fetch: typeof fetch;

	constructor( private readonly config: ApiClientConfig ) {
		this.fetch = config.fetch ?? globalThis.fetch.bind( globalThis );
	}

	async get< T >( path: string, query: Query = {} ): Promise< T > {
		return ( await this.send( 'GET', path, query ) ).body as T;
	}

	async post< T >( path: string, body?: unknown ): Promise< T > {
		return ( await this.send( 'POST', path, {}, body ) ).body as T;
	}

	async put< T >( path: string, body?: unknown ): Promise< T > {
		return ( await this.send( 'PUT', path, {}, body ) ).body as T;
	}

	async delete< T >( path: string ): Promise< T > {
		return ( await this.send( 'DELETE', path ) ).body as T;
	}

	/**
	 * A paginated list (the X-WP-Total headers, as Pagination sends them).
	 *
	 * @param path
	 * @param query
	 */
	async list< T >( path: string, query: Query = {} ): Promise< Page< T > > {
		const { body, headers } = await this.send( 'GET', path, query );
		if ( ! Array.isArray( body ) ) {
			throw invalidResponse( 200 );
		}

		return {
			items: body as T[],
			total: Number( headers.get( 'X-WP-Total' ) ?? body.length ),
			totalPages: Number( headers.get( 'X-WP-TotalPages' ) ?? 1 ),
		};
	}

	/**
	 * The URL of a route, e.g. url( '/services', { page: 2 } ).
	 *
	 * @param path
	 * @param query
	 */
	url( path: string, query: Query = {} ): string {
		const url = new URL( this.config.baseUrl );
		const route = url.searchParams.get( 'rest_route' );
		if ( route !== null ) {
			url.searchParams.set( 'rest_route', trimSlash( route ) + path );
		} else {
			url.pathname = trimSlash( url.pathname ) + path;
		}
		for ( const [ key, value ] of Object.entries( query ) ) {
			if ( value !== undefined ) {
				url.searchParams.set( key, String( value ) );
			}
		}

		return url.toString();
	}

	private async send(
		method: string,
		path: string,
		query: Query = {},
		body?: unknown
	): Promise< { body: unknown; headers: Headers } > {
		const headers: Record< string, string > = {
			Accept: 'application/json',
		};
		if ( this.config.nonce !== undefined ) {
			headers[ 'X-WP-Nonce' ] = this.config.nonce;
		}
		if ( this.config.session !== undefined ) {
			headers[ 'X-Phone-Session' ] = this.config.session;
		}
		if ( body !== undefined ) {
			headers[ 'Content-Type' ] = 'application/json';
		}

		let response: Response;
		try {
			response = await this.fetch( this.url( path, query ), {
				method,
				headers,
				credentials: 'same-origin',
				...( body !== undefined && { body: JSON.stringify( body ) } ),
			} );
		} catch {
			throw new ApiError(
				'network_error',
				'The server could not be reached.',
				0
			);
		}

		const text = await response.text();
		let parsed: unknown;
		if ( text !== '' ) {
			try {
				parsed = JSON.parse( text );
			} catch {
				throw invalidResponse( response.status );
			}
		}

		if ( ! response.ok ) {
			throw isEnvelope( parsed )
				? new ApiError(
						parsed.code,
						parsed.message,
						parsed.data.status,
						parsed.data.details ?? {},
						parsed.data.request_id ?? null
					)
				: invalidResponse( response.status );
		}

		return { body: parsed, headers: response.headers };
	}
}

function invalidResponse( status: number ): ApiError {
	return new ApiError(
		'invalid_response',
		'The server sent a response that is not valid.',
		status
	);
}

function isEnvelope( value: unknown ): value is ErrorEnvelope {
	if ( typeof value !== 'object' || value === null ) {
		return false;
	}
	const { code, message, data } = value as Record< string, unknown >;

	return (
		typeof code === 'string' &&
		typeof message === 'string' &&
		typeof data === 'object' &&
		data !== null &&
		typeof ( data as Record< string, unknown > ).status === 'number'
	);
}

function trimSlash( value: string ): string {
	return value.replace( /\/+$/, '' );
}
