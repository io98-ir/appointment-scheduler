import { useQuery } from '@tanstack/react-query';
import type { AppointmentListItem } from '@vaqtyar/shared';
import { Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { useApi } from '../api';
import { rial, statusLabel, when } from '../appointments/AppointmentsPage';
import { errorMessage } from '../query';

/** Appointments shown on a customer's profile: the most recent ones. */
const RECENT = 20;

/**
 * A customer's booking history (T3.5): their most recent appointments,
 * newest start first, each linking to its full detail.
 *
 * @param props
 * @param props.customerId
 */
export function CustomerHistory( { customerId }: { customerId: number } ) {
	const api = useApi();
	const history = useQuery( {
		queryKey: [ '/appointments', { customer: customerId } ],
		queryFn: () =>
			api.list< AppointmentListItem >( '/appointments', {
				customer: customerId,
				per_page: RECENT,
			} ),
	} );

	return (
		<>
			<h3>{ __( 'Appointments', 'vaqtyar' ) }</h3>
			{ history.isError && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( history.error ) }
				</Notice>
			) }
			{ ! history.data && ! history.isError && <Spinner /> }
			{ history.data && history.data.items.length === 0 && (
				<p>{ __( 'No appointments yet.', 'vaqtyar' ) }</p>
			) }
			{ history.data && history.data.items.length > 0 && (
				<table className="widefat striped vqy-admin__table">
					<thead>
						<tr>
							<th scope="col">{ __( 'Code', 'vaqtyar' ) }</th>
							<th scope="col">{ __( 'Time', 'vaqtyar' ) }</th>
							<th scope="col">{ __( 'Status', 'vaqtyar' ) }</th>
							<th scope="col">{ __( 'Total', 'vaqtyar' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ history.data.items.map( ( item ) => (
							<tr key={ item.id }>
								<td>
									<a href={ `#/appointments/${ item.id }` }>
										<strong dir="ltr">{ item.code }</strong>
									</a>
								</td>
								<td dir="ltr">{ when( item.start ) }</td>
								<td>
									<span
										className={ `vqy-status vqy-status--${ item.status.replace( /_/g, '-' ) }` }
									>
										{ statusLabel( item.status ) }
									</span>
								</td>
								<td>{ rial( item.total ) }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</>
	);
}
