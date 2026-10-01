import {
	keepPreviousData,
	useMutation,
	useQuery,
	useQueryClient,
} from '@tanstack/react-query';
import type {
	Location,
	Service,
	Staff,
	WaitlistItem,
	WaitlistStatus,
} from '@vaqtyar/shared';
import { Button, Notice, Spinner } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import { useApi } from '../api';
import { useAll } from '../catalog/crud';
import { useDate, useWhen } from '../display';
import { errorMessage } from '../query';

const PER_PAGE = 20;
const KEY = [ '/waitlist' ];

function statusLabel( status: WaitlistStatus ): string {
	switch ( status ) {
		case 'waiting':
			return __( 'Waiting', 'vaqtyar' );
		case 'notified':
			return __( 'Told', 'vaqtyar' );
		default:
			return __( 'Expired', 'vaqtyar' );
	}
}

/**
 * The waiting list: customers who found a day full and asked to be told when a time opens. The plugin
 * checks every few minutes and sends one SMS per request; nothing is reserved for them. Staff can
 * remove a request, e.g. one made by mistake.
 */
export function WaitlistPage() {
	const api = useApi();
	const client = useQueryClient();
	const date = useDate();
	const when = useWhen();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const [ page, setPage ] = useState( 1 );
	const services = useAll< Service >( '/services' );
	const staff = useAll< Staff >( '/staff' );
	const locations = useAll< Location >( '/locations' );
	const list = useQuery( {
		queryKey: [ ...KEY, page ],
		queryFn: () =>
			api.list< WaitlistItem >( '/waitlist', {
				page,
				per_page: PER_PAGE,
			} ),
		placeholderData: keepPreviousData,
	} );
	const remove = useMutation( {
		mutationFn: ( id: number ) => api.delete< null >( `/waitlist/${ id }` ),
		onSuccess: () => {
			void client.invalidateQueries( { queryKey: KEY } );
			void createSuccessNotice( __( 'Removed.', 'vaqtyar' ), {
				type: 'snackbar',
			} );
		},
	} );

	const serviceName = ( variantId: number ) => {
		const service = services.data?.find( ( item ) =>
			item.variants.some( ( variant ) => variant.id === variantId )
		);
		const variant = service?.variants.find(
			( item ) => item.id === variantId
		);
		if ( ! service ) {
			return '—';
		}

		return service.variants.length > 1 && variant
			? `${ service.name } · ${ variant.label }`
			: service.name;
	};
	const items = list.data?.items ?? [];
	const totalPages = Math.max(
		1,
		Math.ceil( ( list.data?.total ?? 0 ) / PER_PAGE )
	);

	return (
		<section className="vqy-admin__panel">
			<p className="vqy-admin__muted">
				{ __(
					'Customers who found a day full and asked to be told when a time opens. The plugin checks every few minutes and sends one SMS to each (it needs an SMS provider in Notifications). Nothing is reserved for them.',
					'vaqtyar'
				) }
			</p>
			{ list.isError && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( list.error ) }
				</Notice>
			) }
			{ list.isPending && <Spinner /> }
			{ list.data && (
				<table className="widefat striped vqy-admin__table">
					<thead>
						<tr>
							<th scope="col">{ __( 'Customer', 'vaqtyar' ) }</th>
							<th scope="col">{ __( 'Service', 'vaqtyar' ) }</th>
							<th scope="col">{ __( 'Day', 'vaqtyar' ) }</th>
							<th scope="col">{ __( 'Status', 'vaqtyar' ) }</th>
							<th scope="col">{ __( 'Asked', 'vaqtyar' ) }</th>
							<th scope="col">
								<span className="screen-reader-text">
									{ __( 'Actions', 'vaqtyar' ) }
								</span>
							</th>
						</tr>
					</thead>
					<tbody>
						{ items.length === 0 && (
							<tr>
								<td colSpan={ 6 }>
									{ __( 'Nobody is waiting.', 'vaqtyar' ) }
								</td>
							</tr>
						) }
						{ items.map( ( item ) => (
							<tr key={ item.id }>
								<td>
									<a
										href={ `#/customers/${ item.customer_id }` }
									>
										<strong dir="auto">
											{ item.customer_name ?? '—' }
										</strong>
									</a>
									{ item.customer_phone && (
										<>
											<br />
											<span dir="ltr">
												{ item.customer_phone }
											</span>
										</>
									) }
								</td>
								<td>
									{ serviceName( item.variant_id ) }
									{ item.staff_id !== null && (
										<>
											{ ' · ' }
											{ staff.data?.find(
												( member ) =>
													member.id === item.staff_id
											)?.name ?? '' }
										</>
									) }
									{ locations.data &&
										locations.data.length > 1 && (
											<>
												{ ' · ' }
												{ locations.data.find(
													( place ) =>
														place.id ===
														item.location_id
												)?.name ?? '' }
											</>
										) }
								</td>
								<td dir="ltr">{ date( item.date ) }</td>
								<td>{ statusLabel( item.status ) }</td>
								<td dir="ltr">{ when( item.created_at ) }</td>
								<td>
									<Button
										variant="link"
										isDestructive
										disabled={ remove.isPending }
										onClick={ () =>
											remove.mutate( item.id )
										}
									>
										{ __( 'Remove', 'vaqtyar' ) }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
			{ list.data && totalPages > 1 && (
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
							/* translators: 1: page number, 2: number of pages, 3: number of requests. */
							__( 'Page %1$d of %2$d (%3$d)', 'vaqtyar' ),
							page,
							totalPages,
							list.data.total
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
		</section>
	);
}
