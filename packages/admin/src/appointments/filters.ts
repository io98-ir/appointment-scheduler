import type { AppointmentStatus, Query } from '@vaqtyar/shared';

/** Rows of a list page. */
export const PER_PAGE = 20;

const STATUSES: AppointmentStatus[] = [
	'pending_approval',
	'pending_payment',
	'confirmed',
	'completed',
	'no_show',
	'cancelled',
	'expired',
];

/**
 * The list's filters, kept in the route ("#/appointments?status=confirmed&
 * page=2") so a reload, the back button or a shared link keeps them.
 */
export interface Filters {
	status: AppointmentStatus | '';
	staff: number | null;
	/** "YYYY-MM-DD", the location's own day. */
	from: string;
	to: string;
	search: string;
	page: number;
}

export const NO_FILTERS: Filters = {
	status: '',
	staff: null,
	from: '',
	to: '',
	search: '',
	page: 1,
};

const DATE = /^\d{4}-\d{2}-\d{2}$/;

/**
 * The filters of a list route; anything unknown or malformed is dropped.
 *
 * @param route From useRoute().
 */
export function filtersOf( route: string ): Filters {
	const query = new URLSearchParams( route.split( '?' )[ 1 ] ?? '' );
	const status = query.get( 'status' ) ?? '';
	const staff = Number( query.get( 'staff' ) );
	const page = Number( query.get( 'page' ) );
	const date = ( key: string ) => {
		const value = query.get( key ) ?? '';

		return DATE.test( value ) ? value : '';
	};

	return {
		status: STATUSES.includes( status as AppointmentStatus )
			? ( status as AppointmentStatus )
			: '',
		staff: Number.isInteger( staff ) && staff > 0 ? staff : null,
		from: date( 'from' ),
		to: date( 'to' ),
		search: ( query.get( 'search' ) ?? '' ).slice( 0, 100 ),
		page: Number.isInteger( page ) && page > 1 ? page : 1,
	};
}

/**
 * The route of a list with these filters; defaults are left out.
 *
 * @param filters
 */
export function routeOf( filters: Filters ): string {
	const query = new URLSearchParams();
	if ( filters.status !== '' ) {
		query.set( 'status', filters.status );
	}
	if ( filters.staff !== null ) {
		query.set( 'staff', String( filters.staff ) );
	}
	for ( const key of [ 'from', 'to', 'search' ] as const ) {
		if ( filters[ key ] !== '' ) {
			query.set( key, filters[ key ] );
		}
	}
	if ( filters.page > 1 ) {
		query.set( 'page', String( filters.page ) );
	}
	const text = query.toString();

	return text === '' ? '/appointments' : `/appointments?${ text }`;
}

/**
 * GET /appointments parameters (docs/api.md): newest start first.
 *
 * @param filters
 */
export function queryOf( filters: Filters ): Query {
	return {
		page: filters.page,
		per_page: PER_PAGE,
		status: filters.status || undefined,
		staff: filters.staff ?? undefined,
		from: filters.from || undefined,
		to: filters.to || undefined,
		search: filters.search.trim() || undefined,
	};
}

/**
 * The appointment a detail route names, e.g. "/appointments/42".
 *
 * @param route From useRoute().
 */
export function detailOf( route: string ): number | null {
	const match = /^\/appointments\/(\d+)$/.exec( route );

	return match ? Number( match[ 1 ] ) : null;
}
