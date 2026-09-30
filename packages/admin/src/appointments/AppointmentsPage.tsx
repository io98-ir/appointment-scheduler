import { keepPreviousData, useQuery } from '@tanstack/react-query';
import {
	formatAmount,
	type Digits,
	type AppointmentListItem,
	type AppointmentStatus,
	type PaymentStatus,
	type Service,
	type Staff,
} from '@vaqtyar/shared';
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
import { DateField } from '../DateField';
import { useDisplay, useWhen } from '../display';
import { useAll } from '../catalog/crud';
import { SIZE } from '../catalog/fields';
import { errorMessage } from '../query';
import { useRoute } from '../router';
import { AppointmentDetailPage } from './AppointmentDetail';
import { detailOf, filtersOf, queryOf, routeOf, type Filters } from './filters';

export function statusLabel( status: AppointmentStatus ): string {
	const labels: Record< AppointmentStatus, string > = {
		pending_approval: __( 'Awaiting approval', 'vaqtyar' ),
		pending_payment: __( 'Awaiting payment', 'vaqtyar' ),
		confirmed: __( 'Confirmed', 'vaqtyar' ),
		completed: __( 'Completed', 'vaqtyar' ),
		no_show: __( 'No-show', 'vaqtyar' ),
		cancelled: __( 'Cancelled', 'vaqtyar' ),
		expired: __( 'Expired', 'vaqtyar' ),
	};

	return labels[ status ];
}

export function paymentLabel( status: PaymentStatus ): string {
	const labels: Record< PaymentStatus, string > = {
		unpaid: __( 'Unpaid', 'vaqtyar' ),
		deposit_paid: __( 'Deposit paid', 'vaqtyar' ),
		paid: __( 'Paid', 'vaqtyar' ),
		refunded: __( 'Refunded', 'vaqtyar' ),
		partially_refunded: __( 'Partly refunded', 'vaqtyar' ),
	};

	return labels[ status ];
}

export function rial(
	amount: { amount: number; currency: 'IRR' },
	digits: Digits = 'latin'
): string {
	return sprintf(
		/* translators: %s: an amount in Iranian rials. */
		__( '%s IRR', 'vaqtyar' ),
		formatAmount( amount, digits )
	);
}

/**
 * An amount in rials, in the digits the owner chose.
 */
export function useRial(): ( amount: {
	amount: number;
	currency: 'IRR';
} ) => string {
	const { digits } = useDisplay();

	return ( amount ) => rial( amount, digits );
}

/**
 * The appointments screen (T3.4): the list, or one appointment at
 * "/appointments/{id}".
 */
export function AppointmentsPage() {
	const route = useRoute();
	const id = detailOf( route );

	return id === null ? (
		<AppointmentList filters={ filtersOf( route ) } />
	) : (
		<AppointmentDetailPage id={ id } />
	);
}

function go( filters: Filters ) {
	window.location.hash = '#' + routeOf( filters );
}

/**
 * A page of appointments, filtered and paginated by the server; no
 * DataViews (ADR-019).
 *
 * @param props
 * @param props.filters
 */
function AppointmentList( { filters }: { filters: Filters } ) {
	const api = useApi();
	const when = useWhen();
	const money = useRial();
	const staff = useAll< Staff >( '/staff' );
	const services = useAll< Service >( '/services' );
	const query = queryOf( filters );
	const page = useQuery( {
		queryKey: [ '/appointments', query ],
		queryFn: () =>
			api.list< AppointmentListItem >( '/appointments', query ),
		placeholderData: keepPreviousData,
	} );
	// Typing searches once it pauses, not on every key.
	const [ search, setSearch ] = useState( filters.search );
	useEffect( () => {
		if ( search === filters.search ) {
			return undefined;
		}
		const timer = window.setTimeout(
			() => go( { ...filters, search, page: 1 } ),
			400
		);

		return () => window.clearTimeout( timer );
	}, [ search, filters ] );
	const set = ( change: Partial< Filters > ) =>
		go( { ...filters, ...change, page: 1 } );
	const staffName = ( staffId: number ) =>
		staff.data?.find( ( item ) => item.id === staffId )?.name ?? '—';
	const serviceName = ( item: AppointmentListItem ) => {
		const service = services.data?.find(
			( candidate ) => candidate.id === item.service_id
		);
		const variant = service?.variants.find(
			( candidate ) => candidate.id === item.variant_id
		);
		if ( ! service ) {
			return '—';
		}

		return service.variants.length > 1 && variant
			? `${ service.name } · ${ variant.label }`
			: service.name;
	};
	const statuses: AppointmentStatus[] = [
		'pending_approval',
		'pending_payment',
		'confirmed',
		'completed',
		'no_show',
		'cancelled',
		'expired',
	];
	const items = page.data?.items ?? [];
	const totalPages = page.data?.totalPages ?? 1;

	return (
		<>
			<div className="vqy-admin__toolbar">
				<SearchControl
					__nextHasNoMarginBottom
					label={ __( 'Search by code, name or phone', 'vaqtyar' ) }
					value={ search }
					onChange={ setSearch }
				/>
				<SelectControl
					{ ...SIZE }
					label={ __( 'Status', 'vaqtyar' ) }
					value={ filters.status }
					options={ [
						{ value: '', label: __( 'All', 'vaqtyar' ) },
						...statuses.map( ( status ) => ( {
							value: status,
							label: statusLabel( status ),
						} ) ),
					] }
					onChange={ ( value ) =>
						set( { status: value as Filters[ 'status' ] } )
					}
				/>
				<SelectControl
					{ ...SIZE }
					label={ __( 'Staff', 'vaqtyar' ) }
					value={ String( filters.staff ?? '' ) }
					options={ [
						{ value: '', label: __( 'All', 'vaqtyar' ) },
						...( staff.data ?? [] ).map( ( item ) => ( {
							value: String( item.id ),
							label: item.name,
						} ) ),
					] }
					onChange={ ( value ) =>
						set( { staff: value === '' ? null : Number( value ) } )
					}
				/>
				<DateField
					label={ __( 'From', 'vaqtyar' ) }
					value={ filters.from }
					onChange={ ( value ) => set( { from: value } ) }
				/>
				<DateField
					label={ __( 'To', 'vaqtyar' ) }
					value={ filters.to }
					onChange={ ( value ) => set( { to: value } ) }
				/>
				{ page.isFetching && <Spinner /> }
			</div>
			{ page.isError && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( page.error ) }
				</Notice>
			) }
			{ page.data && (
				<table className="widefat striped vqy-admin__table">
					<thead>
						<tr>
							<th scope="col">{ __( 'Code', 'vaqtyar' ) }</th>
							<th scope="col">{ __( 'Time', 'vaqtyar' ) }</th>
							<th scope="col">{ __( 'Customer', 'vaqtyar' ) }</th>
							<th scope="col">{ __( 'Service', 'vaqtyar' ) }</th>
							<th scope="col">{ __( 'Staff', 'vaqtyar' ) }</th>
							<th scope="col">{ __( 'Status', 'vaqtyar' ) }</th>
							<th scope="col">{ __( 'Total', 'vaqtyar' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ items.length === 0 && (
							<tr>
								<td colSpan={ 7 }>
									{ __( 'No appointments.', 'vaqtyar' ) }
								</td>
							</tr>
						) }
						{ items.map( ( item ) => (
							<tr key={ item.id }>
								<td>
									<a href={ `#/appointments/${ item.id }` }>
										<strong dir="ltr">{ item.code }</strong>
									</a>
								</td>
								<td dir="ltr">{ when( item.start ) }</td>
								<td dir="auto">
									{ item.customer?.name ?? '—' }
								</td>
								<td dir="auto">{ serviceName( item ) }</td>
								<td dir="auto">
									{ staffName( item.staff_id ) }
								</td>
								<td>
									<span
										className={ `vqy-status vqy-status--${ item.status.replace( /_/g, '-' ) }` }
									>
										{ statusLabel( item.status ) }
									</span>
								</td>
								<td>{ money( item.total ) }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
			{ page.data && totalPages > 1 && (
				<div className="vqy-admin__toolbar">
					<Button
						variant="secondary"
						disabled={ filters.page <= 1 }
						onClick={ () =>
							go( { ...filters, page: filters.page - 1 } )
						}
					>
						{ __( 'Previous', 'vaqtyar' ) }
					</Button>
					<span>
						{ sprintf(
							/* translators: 1: page number, 2: number of pages, 3: number of appointments. */
							__( 'Page %1$d of %2$d (%3$d)', 'vaqtyar' ),
							filters.page,
							totalPages,
							page.data.total
						) }
					</span>
					<Button
						variant="secondary"
						disabled={ filters.page >= totalPages }
						onClick={ () =>
							go( { ...filters, page: filters.page + 1 } )
						}
					>
						{ __( 'Next', 'vaqtyar' ) }
					</Button>
				</div>
			) }
		</>
	);
}
