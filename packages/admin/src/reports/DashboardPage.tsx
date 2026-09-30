import { useQuery } from '@tanstack/react-query';
import type { AppointmentListItem, ReportSummary } from '@vaqtyar/shared';
import { Notice, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { useApi } from '../api';
import { useRial } from '../appointments/AppointmentsPage';
import { useDate, useDigits, useWhen } from '../display';
import { errorMessage } from '../query';
import { SetupPrompt } from '../setup/SetupPrompt';
import { daysAgo } from './dates';

type Days = ReportSummary[ 'days' ];

function sum( days: Days ) {
	return days.reduce(
		( total, day ) => ( {
			appointments: total.appointments + day.appointments,
			revenue: total.revenue + day.revenue,
		} ),
		{ appointments: 0, revenue: 0 }
	);
}

function Stat( {
	title,
	value,
	detail,
}: {
	title: string;
	value: string;
	detail?: string;
} ) {
	return (
		<div className="vqy-admin__stat">
			<span className="vqy-admin__muted">{ title }</span>
			<strong>{ value }</strong>
			{ detail && <span className="vqy-admin__muted">{ detail }</span> }
		</div>
	);
}

/**
 * Today, the last week and the last month at a glance, and what is left of
 * today (T3.6). "Today" and "week" are the admin's own dates.
 */
export function DashboardPage() {
	const api = useApi();
	const when = useWhen();
	const money = useRial();
	const showDate = useDate();
	const digits = useDigits();
	const from = daysAgo( 29 );
	const to = daysAgo( 0 );
	const summary = useQuery( {
		queryKey: [ '/reports/summary', from, to ],
		queryFn: () =>
			api.get< ReportSummary >( '/reports/summary', { from, to } ),
	} );
	const today = useQuery( {
		queryKey: [ '/appointments', 'today', to ],
		queryFn: () =>
			api.list< AppointmentListItem >( '/appointments', {
				from: to,
				to,
				status: 'confirmed',
				orderby: 'start',
				order: 'asc',
				per_page: 20,
			} ),
	} );

	if ( summary.isError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ errorMessage( summary.error ) }
			</Notice>
		);
	}
	if ( ! summary.data ) {
		return <Spinner />;
	}
	const days = summary.data.days;
	const now = days.slice( -1 );
	const week = days.slice( -7 );
	const { totals } = summary.data;
	const count = ( n: number ) =>
		sprintf(
			/* translators: %d: a number of appointments. */
			__( '%d appointments', 'vaqtyar' ),
			n
		);
	const busiest = Math.max( 1, ...week.map( ( day ) => day.appointments ) );

	return (
		<>
			<SetupPrompt />
			<div className="vqy-admin__stats">
				<Stat
					title={ __( 'Today', 'vaqtyar' ) }
					value={ digits( count( sum( now ).appointments ) ) }
					detail={ money( {
						amount: sum( now ).revenue,
						currency: 'IRR',
					} ) }
				/>
				<Stat
					title={ __( 'Last 7 days', 'vaqtyar' ) }
					value={ digits( count( sum( week ).appointments ) ) }
					detail={ money( {
						amount: sum( week ).revenue,
						currency: 'IRR',
					} ) }
				/>
				<Stat
					title={ __( 'Last 30 days', 'vaqtyar' ) }
					value={ digits( count( totals.appointments ) ) }
					detail={ money( {
						amount: totals.revenue,
						currency: 'IRR',
					} ) }
				/>
				<Stat
					title={ __( 'Cancellation rate (30 days)', 'vaqtyar' ) }
					value={ digits( `${ totals.cancel_rate }%` ) }
					detail={ digits(
						sprintf(
							/* translators: %d: a number of cancelled appointments. */
							__( '%d cancelled', 'vaqtyar' ),
							totals.cancelled
						)
					) }
				/>
			</div>
			<section className="vqy-admin__panel">
				<h2>{ __( 'Last 7 days', 'vaqtyar' ) }</h2>
				<ul className="vqy-admin__bars">
					{ week.map( ( day ) => (
						<li key={ day.date }>
							<span>{ showDate( day.date ) }</span>
							<span
								className="vqy-admin__bar"
								style={ {
									inlineSize: `${ ( day.appointments / busiest ) * 100 }%`,
								} }
							/>
							<span>
								{ digits( String( day.appointments ) ) }
							</span>
						</li>
					) ) }
				</ul>
			</section>
			<section className="vqy-admin__panel">
				<h2>{ __( 'Still to come today', 'vaqtyar' ) }</h2>
				{ today.isPending && <Spinner /> }
				{ today.data?.items.length === 0 && (
					<p className="vqy-admin__muted">
						{ __( 'No confirmed appointments today.', 'vaqtyar' ) }
					</p>
				) }
				<ul className="vqy-admin__exceptions">
					{ today.data?.items.map( ( item ) => (
						<li key={ item.id }>
							<a href={ `#/appointments/${ item.id }` }>
								{ when( item.start ) }
							</a>
							{ ' — ' }
							{ item.customer?.name ?? '—' }
						</li>
					) ) }
				</ul>
			</section>
		</>
	);
}
