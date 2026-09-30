import { useQuery } from '@tanstack/react-query';
import type {
	AppointmentListItem,
	ReportSummary,
	Service,
	Staff,
} from '@vaqtyar/shared';
import { Button, Notice, Spinner } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import { useApi } from '../api';
import { statusLabel, useRial } from '../appointments/AppointmentsPage';
import { DateField } from '../DateField';
import { useDate, useDigits } from '../display';
import { useAll } from '../catalog/crud';
import { errorMessage } from '../query';
import { downloadCsv, toCsv } from './csv';
import { daysAgo } from './dates';

/** The export stops here; a longer range is exported in parts. */
const EXPORT_LIMIT = 5000;
const EXPORT_PAGE = 100;

type Ranked = ReportSummary[ 'services' ];

/**
 * The report for a range of dates, and the appointments in it as a CSV
 * (T3.6). The figures count confirmed and completed appointments.
 */
export function ReportsPage() {
	const api = useApi();
	const { createErrorNotice } = useDispatch( noticesStore );
	const [ from, setFrom ] = useState( daysAgo( 29 ) );
	const [ to, setTo ] = useState( daysAgo( 0 ) );
	const [ exporting, setExporting ] = useState( false );
	const staff = useAll< Staff >( '/staff' );
	const services = useAll< Service >( '/services' );
	const summary = useQuery( {
		queryKey: [ '/reports/summary', from, to ],
		queryFn: () =>
			api.get< ReportSummary >( '/reports/summary', { from, to } ),
		enabled: from !== '' && to !== '',
		retry: false,
	} );
	const staffName = ( id: number ) =>
		staff.data?.find( ( item ) => item.id === id )?.name ?? `#${ id }`;
	const serviceName = ( id: number ) =>
		services.data?.find( ( item ) => item.id === id )?.name ?? `#${ id }`;

	const exportCsv = async () => {
		setExporting( true );
		try {
			const items: AppointmentListItem[] = [];
			for ( let page = 1; items.length < EXPORT_LIMIT; page++ ) {
				const result = await api.list< AppointmentListItem >(
					'/appointments',
					{
						from,
						to,
						orderby: 'start',
						order: 'asc',
						page,
						per_page: EXPORT_PAGE,
					}
				);
				items.push( ...result.items );
				if ( page >= result.totalPages ) {
					break;
				}
			}
			downloadCsv(
				`appointments-${ from }-${ to }.csv`,
				toCsv( [
					[
						'code',
						'start',
						'status',
						'payment_status',
						'customer',
						'phone',
						'service',
						'staff',
						'party_size',
						'total_irr',
					],
					...items.map( ( item ) => [
						item.code,
						item.start,
						item.status,
						item.payment_status,
						item.customer?.name ?? '',
						item.customer?.phone ?? '',
						serviceName( item.service_id ),
						staffName( item.staff_id ),
						item.party_size,
						item.total.amount,
					] ),
				] )
			);
		} catch ( error ) {
			void createErrorNotice( errorMessage( error ), {
				type: 'snackbar',
			} );
		} finally {
			setExporting( false );
		}
	};

	return (
		<>
			<form
				className="vqy-admin__inline-form"
				onSubmit={ ( event ) => event.preventDefault() }
			>
				<DateField
					label={ __( 'From date', 'vaqtyar' ) }
					value={ from }
					onChange={ setFrom }
				/>
				<DateField
					label={ __( 'To date', 'vaqtyar' ) }
					value={ to }
					onChange={ setTo }
				/>
				<Button
					variant="secondary"
					isBusy={ exporting }
					disabled={ exporting || from === '' || to === '' }
					onClick={ () => void exportCsv() }
				>
					{ __( 'Export appointments (CSV)', 'vaqtyar' ) }
				</Button>
			</form>
			{ summary.isError && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( summary.error ) }
				</Notice>
			) }
			{ summary.isPending && summary.fetchStatus !== 'idle' && (
				<Spinner />
			) }
			{ summary.data && (
				<Report
					summary={ summary.data }
					serviceName={ serviceName }
					staffName={ staffName }
				/>
			) }
		</>
	);
}

function Report( {
	summary,
	serviceName,
	staffName,
}: {
	summary: ReportSummary;
	serviceName: ( id: number ) => string;
	staffName: ( id: number ) => string;
} ) {
	const { totals } = summary;
	const showDate = useDate();
	const digits = useDigits();
	const money = useRial();

	return (
		<>
			<section className="vqy-admin__panel">
				<h2>{ __( 'Summary', 'vaqtyar' ) }</h2>
				<p>
					{ digits(
						sprintf(
							/* translators: 1: number of appointments, 2: revenue. */
							__( '%1$d booked appointments, %2$s.', 'vaqtyar' ),
							totals.appointments,
							money( { amount: totals.revenue, currency: 'IRR' } )
						)
					) }
				</p>
				<p>
					{ digits(
						sprintf(
							/* translators: 1: cancelled, 2: no-shows, 3: percent. */
							__(
								'%1$d cancelled, %2$d no-shows, cancellation rate %3$s%%.',
								'vaqtyar'
							),
							totals.cancelled,
							totals.no_show,
							String( totals.cancel_rate )
						)
					) }
				</p>
				<ul className="vqy-admin__exceptions">
					{ Object.entries( summary.statuses ).map(
						( [ status, count ] ) => (
							<li key={ status }>
								{ statusLabel(
									status as Parameters<
										typeof statusLabel
									>[ 0 ]
								) }
								{ `: ${ count }` }
							</li>
						)
					) }
				</ul>
			</section>
			<Table
				title={ __( 'By service', 'vaqtyar' ) }
				label={ __( 'Service', 'vaqtyar' ) }
				rows={ summary.services }
				name={ serviceName }
			/>
			<Table
				title={ __( 'By staff', 'vaqtyar' ) }
				label={ __( 'Staff', 'vaqtyar' ) }
				rows={ summary.staff }
				name={ staffName }
			/>
			<section className="vqy-admin__panel">
				<h2>{ __( 'By day', 'vaqtyar' ) }</h2>
				<table className="widefat striped vqy-admin__table">
					<thead>
						<tr>
							<th>{ __( 'Date', 'vaqtyar' ) }</th>
							<th>{ __( 'Appointments', 'vaqtyar' ) }</th>
							<th>{ __( 'Revenue', 'vaqtyar' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ summary.days.map( ( day ) => (
							<tr key={ day.date }>
								<td>{ showDate( day.date ) }</td>
								<td>
									{ digits( String( day.appointments ) ) }
								</td>
								<td>
									{ money( {
										amount: day.revenue,
										currency: 'IRR',
									} ) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</section>
		</>
	);
}

function Table( {
	title,
	label,
	rows,
	name,
}: {
	title: string;
	label: string;
	rows: Ranked;
	name: ( id: number ) => string;
} ) {
	const digits = useDigits();
	const money = useRial();

	return (
		<section className="vqy-admin__panel">
			<h2>{ title }</h2>
			{ rows.length === 0 ? (
				<p className="vqy-admin__muted">
					{ __( 'Nothing booked in this range.', 'vaqtyar' ) }
				</p>
			) : (
				<table className="widefat striped vqy-admin__table">
					<thead>
						<tr>
							<th>{ label }</th>
							<th>{ __( 'Appointments', 'vaqtyar' ) }</th>
							<th>{ __( 'Revenue', 'vaqtyar' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ rows.map( ( row ) => (
							<tr key={ row.id }>
								<td>{ name( row.id ) }</td>
								<td>
									{ digits( String( row.appointments ) ) }
								</td>
								<td>
									{ money( {
										amount: row.revenue,
										currency: 'IRR',
									} ) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</section>
	);
}
