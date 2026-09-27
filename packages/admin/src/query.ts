import { MutationCache, QueryClient } from '@tanstack/react-query';
import { ApiError } from '@vaqtyar/shared';
import { __ } from '@wordpress/i18n';

/**
 * A client error (4xx) fails the same way again, so only a network error or
 * a server error (5xx) is retried, twice at most.
 *
 * @param failureCount Failures so far.
 * @param error        The last one.
 */
export function shouldRetry( failureCount: number, error: unknown ): boolean {
	if (
		error instanceof ApiError &&
		error.status >= 400 &&
		error.status < 500
	) {
		return false;
	}

	return failureCount < 2;
}

/**
 * The message to show for an error. The server's own message is already
 * translated (architecture §8); the client's codes are translated here.
 *
 * @param error Whatever was thrown.
 */
export function errorMessage( error: unknown ): string {
	if ( error instanceof ApiError ) {
		if ( error.code === 'network_error' ) {
			return __(
				'The server could not be reached. Check the connection and try again.',
				'vaqtyar'
			);
		}
		if ( error.code !== 'invalid_response' && error.message !== '' ) {
			return error.message;
		}
	}

	return __( 'Something went wrong. Please try again.', 'vaqtyar' );
}

/**
 * The app's query client. A failed mutation is reported through onError, so
 * no screen can forget to say that a save did not happen; a failed query is
 * shown by the screen that asked for it, in place of its data.
 *
 * @param onError Shows a message to the user.
 */
export function createQueryClient(
	onError: ( message: string ) => void
): QueryClient {
	return new QueryClient( {
		defaultOptions: {
			queries: { retry: shouldRetry, refetchOnWindowFocus: false },
			mutations: { retry: false },
		},
		mutationCache: new MutationCache( {
			onError: ( error ) => onError( errorMessage( error ) ),
		} ),
	} );
}
