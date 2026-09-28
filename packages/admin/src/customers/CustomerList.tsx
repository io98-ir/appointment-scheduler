import { keepPreviousData, useQuery } from '@tanstack/react-query';
import type { Customer, CustomerStatus, Page } from '@vaqtyar/shared';
import {
	Button,
	Notice,
	SearchControl,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { useApi } from '../api';
import { SIZE } from '../catalog/fields';
import { errorMessage } from '../query';

/** Rows of a page. */
const PER_PAGE = 20;

function statusLabel( status: CustomerStatus ): string {
	return status === 'active'
		? __( 'Active', 'vaqtyar' )
		: __( 'Blocked', 'vaqtyar' );
}

/**
 * The customer name as the list and history show it: the phone when both
 * names are empty, since a phone is the only field every customer has.
 *
 * @param customer
 * @param customer.first_name
 * @param customer.last_name
 * @param customer.phone
 */
export function customerName( customer: {
	first_name: string;
	last_name: string;
	phone: string;
} ): string {
	const name = `${ customer.first_name } ${ customer.last_name }`.trim();

	return name === '' ? customer.phone : name;
}

/**
 * The customers list (T3.5): searched and paged by the server, since there
 * may be many more of them than a catalog (CatalogList loads every page).
 */
export function CustomerList() {
	const api = useApi();
	const [ search, setSearch ] = useState( '' );
	const [ status, setStatus ] = useState< CustomerStatus | '' >( '' );
	const [ page, setPage ] = useState( 1 );
	// Typing searches once it pauses, not on every key.
	const [ typed, setTyped ] = useState( '' );
	useEffect( () => {
		if ( typed === search ) {
			return undefined;
		}
		const timer = window.setTimeout( () => {
			setSearch( typed );
			setPage( 1 );
		}, 400 );

		return () => window.clearTimeout( timer );
	}, [ typed, search ] );
	const result = useQuery( {
		queryKey: [ '/customers', search, status, page ],
		queryFn: (): Promise< Page< Customer > > =>
			api.list< Customer >( '/customers', {
				search: search || undefined,
				status: status || undefined,
				page,
				per_page: PER_PAGE,
			} ),
		placeholderData: keepPreviousData,
	} );
	const items = result.data?.items ?? [];
	const totalPages = result.data?.totalPages ?? 1;
	const setStatusFilter = ( value: CustomerStatus | '' ) => {
		setStatus( value );
		setPage( 1 );
	};

	return (
		<>
			<div className="vqy-admin__toolbar">
				<Button variant="primary" href="#/customers/new">
					{ __( 'Add customer', 'vaqtyar' ) }
				</Button>
				<SearchControl
					__nextHasNoMarginBottom
					label={ __( 'Search by name, phone or email', 'vaqtyar' ) }
					value={ typed }
					onChange={ setTyped }
				/>
				<SelectControl
					{ ...SIZE }
					label={ __( 'Status', 'vaqtyar' ) }
					value={ status }
					options={ [
						{ value: '', label: __( 'All', 'vaqtyar' ) },
						{ value: 'active', label: statusLabel( 'active' ) },
						{ value: 'blocked', label: statusLabel( 'blocked' ) },
					] }
					onChange={ ( value ) =>
						setStatusFilter( value as CustomerStatus | '' )
					}
				/>
				{ result.isFetching && <Spinner /> }
			</div>
			{ result.isError && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( result.error ) }
				</Notice>
			) }
			{ result.data && (
				<table className="widefat striped vqy-admin__table">
					<thead>
						<tr>
							<th scope="col">{ __( 'Name', 'vaqtyar' ) }</th>
							<th scope="col">{ __( 'Phone', 'vaqtyar' ) }</th>
							<th scope="col">{ __( 'Email', 'vaqtyar' ) }</th>
							<th scope="col">{ __( 'Status', 'vaqtyar' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ items.length === 0 && (
							<tr>
								<td colSpan={ 4 }>
									{ __( 'No customers.', 'vaqtyar' ) }
								</td>
							</tr>
						) }
						{ items.map( ( item ) => (
							<tr key={ item.id }>
								<td>
									<a href={ `#/customers/${ item.id }` }>
										<strong dir="auto">
											{ customerName( item ) }
										</strong>
									</a>
								</td>
								<td dir="ltr">{ item.phone }</td>
								<td dir="ltr">{ item.email ?? '—' }</td>
								<td>{ statusLabel( item.status ) }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
			{ result.data && totalPages > 1 && (
				<div className="vqy-admin__toolbar">
					<Button
						variant="secondary"
						disabled={ page <= 1 }
						onClick={ () => setPage( page - 1 ) }
					>
						{ __( 'Previous', 'vaqtyar' ) }
					</Button>
					<span>
						{ sprintf(
							/* translators: 1: page number, 2: number of pages, 3: number of customers. */
							__( 'Page %1$d of %2$d (%3$d)', 'vaqtyar' ),
							page,
							totalPages,
							result.data.total
						) }
					</span>
					<Button
						variant="secondary"
						disabled={ page >= totalPages }
						onClick={ () => setPage( page + 1 ) }
					>
						{ __( 'Next', 'vaqtyar' ) }
					</Button>
				</div>
			) }
		</>
	);
}
