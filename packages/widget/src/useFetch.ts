import { useEffect, useState } from 'preact/hooks';

export interface Fetched< T > {
	data: T | undefined;
	error: string | undefined;
	loading: boolean;
}

/**
 * Loads once per key and drops a result whose key has changed meanwhile. A
 * null key loads nothing (an input is still missing).
 *
 * @param key  What the request depends on; a new key loads again.
 * @param load Runs the request.
 */
export function useFetch< T >(
	key: string | null,
	load: () => Promise< T >
): Fetched< T > {
	const [ state, setState ] = useState< Fetched< T > >( {
		data: undefined,
		error: undefined,
		loading: key !== null,
	} );

	useEffect( () => {
		if ( key === null ) {
			setState( { data: undefined, error: undefined, loading: false } );

			return undefined;
		}
		let current = true;
		setState( { data: undefined, error: undefined, loading: true } );
		load().then(
			( data ) => {
				if ( current ) {
					setState( { data, error: undefined, loading: false } );
				}
			},
			( error: unknown ) => {
				if ( current ) {
					setState( {
						data: undefined,
						error:
							error instanceof Error
								? error.message
								: String( error ),
						loading: false,
					} );
				}
			}
		);

		return () => {
			current = false;
		};
		// `load` is a new closure every render; the key names what it reads.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ key ] );

	return state;
}
