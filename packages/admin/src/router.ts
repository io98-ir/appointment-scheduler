import { useSyncExternalStore } from '@wordpress/element';

/**
 * A hash router (tech-stack §2: a few pages, no library). The route lives in
 * the hash, "#/services/7", so wp-admin's own ?page= query stays untouched
 * and a reload or a shared link opens the same screen.
 */

/**
 * The current route: "/" when the hash is empty or not a route.
 *
 * @param hash location.hash
 */
export function routeFromHash( hash: string ): string {
	const path = hash.replace( /^#/, '' );

	return path.startsWith( '/' ) ? path : '/';
}

function subscribe( onChange: () => void ): () => void {
	window.addEventListener( 'hashchange', onChange );

	return () => window.removeEventListener( 'hashchange', onChange );
}

export function useRoute(): string {
	return useSyncExternalStore( subscribe, () =>
		routeFromHash( window.location.hash )
	);
}
