import {
	useMutation,
	useQuery,
	useQueryClient,
	type UseQueryResult,
} from '@tanstack/react-query';
import type { ApiClient } from '@vaqtyar/shared';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import { useApi } from '../api';

/** The largest page the catalog routes serve (docs/api.md). */
const PER_PAGE = 100;

/**
 * Every item of a catalog resource, page after page. A catalog is small (a
 * few dozen services or staff), so the lists filter, sort and page in the
 * browser, and the forms can offer every location or staff member.
 *
 * @param api
 * @param base e.g. "/locations".
 */
export async function fetchAll< T >(
	api: ApiClient,
	base: string
): Promise< T[] > {
	const items: T[] = [];
	for ( let page = 1; ; page++ ) {
		const result = await api.list< T >( base, {
			page,
			per_page: PER_PAGE,
		} );
		items.push( ...result.items );
		if ( page >= result.totalPages ) {
			return items;
		}
	}
}

export function useAll< T >( base: string ): UseQueryResult< T[] > {
	const api = useApi();

	return useQuery( {
		queryKey: [ base ],
		queryFn: () => fetchAll< T >( api, base ),
	} );
}

export function useItem< T >(
	base: string,
	id: number | null
): UseQueryResult< T > {
	const api = useApi();

	return useQuery( {
		queryKey: [ base, id ],
		queryFn: () => api.get< T >( `${ base }/${ id }` ),
		enabled: id !== null,
	} );
}

/**
 * Creates (id null) or replaces an item, then refreshes the lists. A failure
 * is reported by the query client (query.ts).
 *
 * @param base
 */
export function useSave< T extends { id: number } >( base: string ) {
	const api = useApi();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );

	return useMutation( {
		mutationFn: ( {
			id,
			item,
		}: {
			id: number | null;
			item: Omit< T, 'id' >;
		} ) =>
			id === null
				? api.post< T >( base, item )
				: api.put< T >( `${ base }/${ id }`, item ),
		onSuccess: ( saved ) => {
			client.setQueryData( [ base, saved.id ], saved );
			void client.invalidateQueries( {
				queryKey: [ base ],
				exact: true,
			} );
			void createSuccessNotice( __( 'Saved.', 'vaqtyar' ), {
				type: 'snackbar',
			} );
		},
	} );
}

export function useRemove( base: string ) {
	const api = useApi();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );

	return useMutation( {
		mutationFn: ( id: number ) => api.delete< null >( `${ base }/${ id }` ),
		onSuccess: () => {
			void client.invalidateQueries( { queryKey: [ base ] } );
			void createSuccessNotice( __( 'Deleted.', 'vaqtyar' ), {
				type: 'snackbar',
			} );
		},
	} );
}

/**
 * What a catalog route below its base shows: the list ("/staff"), a new
 * item ("/staff/new") or one item ("/staff/7"); null for anything else.
 *
 * @param route From useRoute().
 * @param base  e.g. "/staff".
 */
export function screenOf(
	route: string,
	base: string
): 'list' | 'new' | number | null {
	if ( route === base ) {
		return 'list';
	}
	if ( ! route.startsWith( base + '/' ) ) {
		return null;
	}
	const rest = route.slice( base.length + 1 );
	if ( rest === 'new' ) {
		return 'new';
	}

	return /^[1-9]\d*$/.test( rest ) ? Number( rest ) : null;
}
