import type { ApiClient } from '@vaqtyar/shared';
import { createContext, useContext } from '@wordpress/element';

export const ApiContext = createContext< ApiClient | null >( null );

/**
 * The REST client, for the screens' queries and mutations.
 */
export function useApi(): ApiClient {
	const api = useContext( ApiContext );
	if ( api === null ) {
		throw new Error( 'useApi() outside of <App>.' );
	}

	return api;
}
