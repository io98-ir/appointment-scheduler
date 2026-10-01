import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { Money, PaymentLedger, PaymentRecord } from '@vaqtyar/shared';
import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import type { FormEvent } from 'react';

import { useApi } from '../api';
import { IntField, SIZE } from '../catalog/fields';
import { errorMessage } from '../query';
import { useRial } from './AppointmentsPage';

function gatewayLabel( id: string ): string {
	const labels: Record< string, string > = {
		offline: __( 'Recorded by staff', 'vaqtyar' ),
		zarinpal: 'Zarinpal',
		zibal: 'Zibal',
		woocommerce: 'WooCommerce',
	};

	return labels[ id ] ?? id;
}

function statusLabel( status: PaymentRecord[ 'status' ] ): string {
	const labels: Record< PaymentRecord[ 'status' ], string > = {
		succeeded: __( 'Paid', 'vaqtyar' ),
		failed: __( 'Failed', 'vaqtyar' ),
		awaiting_callback: __( 'Waiting for the gateway', 'vaqtyar' ),
		pending: __( 'Waiting for the gateway', 'vaqtyar' ),
	};

	return labels[ status ];
}

/**
 * The money of one appointment: what was paid, at which gateway, what was
 * given back, and the two things staff do about it: record money received
 * outside any gateway (cash, a card machine, a transfer), and record a refund
 * they made by hand. Nothing here moves money; it keeps the ledger true, and
 * the appointment's payment status follows it.
 *
 * @param props
 * @param props.appointmentId
 * @param props.total         The appointment's price.
 * @param props.onChanged     Called after a payment or a refund is recorded.
 */
export function Payments( {
	appointmentId,
	total,
	onChanged,
}: {
	appointmentId: number;
	total: Money;
	onChanged: () => void;
} ) {
	const api = useApi();
	const money = useRial();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const key = [ '/payments', appointmentId ];
	const ledger = useQuery( {
		queryKey: key,
		queryFn: () =>
			api.get< PaymentLedger >( '/payments', {
				appointment_id: appointmentId,
			} ),
	} );
	const left = Math.max(
		0,
		total.amount -
			( ledger.data?.paid ?? 0 ) +
			( ledger.data?.refunded ?? 0 )
	);
	const [ amount, setAmount ] = useState< number | null >( null );
	const [ refunding, setRefunding ] = useState< number | null >( null );
	const done = ( message: string ) => {
		void client.invalidateQueries( { queryKey: key } );
		void createSuccessNotice( message, { type: 'snackbar' } );
		onChanged();
	};
	const record = useMutation( {
		mutationFn: ( value: number ) =>
			api.post( '/payments/offline', {
				appointment_id: appointmentId,
				amount: value,
			} ),
		onSuccess: () => {
			setAmount( null );
			done( __( 'Payment recorded.', 'vaqtyar' ) );
		},
	} );
	const refund = useMutation( {
		mutationFn: ( entry: {
			payment_id: number;
			amount: number;
			reason: string;
		} ) => api.post( '/payments/refunds', entry ),
		onSuccess: () => {
			setRefunding( null );
			done( __( 'Refund recorded.', 'vaqtyar' ) );
		},
	} );
	const submit = ( event: FormEvent ) => {
		event.preventDefault();
		record.mutate( amount ?? left );
	};

	return (
		<section className="vqy-appointment__payments">
			<h3>{ __( 'Payments', 'vaqtyar' ) }</h3>
			{ ledger.isPending && <Spinner /> }
			{ ledger.isError && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( ledger.error ) }
				</Notice>
			) }
			{ ledger.data && (
				<>
					{ ledger.data.items.length === 0 ? (
						<p className="vqy-admin__muted">
							{ __( 'Nothing has been paid.', 'vaqtyar' ) }
						</p>
					) : (
						<table className="widefat vqy-admin__table">
							<thead>
								<tr>
									<th scope="col">
										{ __( 'Gateway', 'vaqtyar' ) }
									</th>
									<th scope="col">
										{ __( 'Amount', 'vaqtyar' ) }
									</th>
									<th scope="col">
										{ __( 'Status', 'vaqtyar' ) }
									</th>
									<th scope="col">
										{ __( 'Refunded', 'vaqtyar' ) }
									</th>
									<th scope="col" />
								</tr>
							</thead>
							<tbody>
								{ ledger.data.items.map( ( item ) => (
									<tr key={ item.id }>
										<td>
											{ gatewayLabel( item.gateway ) }
										</td>
										<td>{ money( item.amount ) }</td>
										<td>{ statusLabel( item.status ) }</td>
										<td>
											{ money( {
												amount: item.refunded,
												currency: 'IRR',
											} ) }
										</td>
										<td className="vqy-admin__actions">
											{ item.status === 'succeeded' &&
												item.amount.amount >
													item.refunded && (
													<Button
														variant="tertiary"
														onClick={ () =>
															setRefunding(
																refunding ===
																	item.id
																	? null
																	: item.id
															)
														}
													>
														{ __(
															'Record a refund',
															'vaqtyar'
														) }
													</Button>
												) }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) }
					{ refunding !== null && (
						<RefundForm
							key={ refunding }
							max={
								( ledger.data.items.find(
									( item ) => item.id === refunding
								)?.amount.amount ?? 0 ) -
								( ledger.data.items.find(
									( item ) => item.id === refunding
								)?.refunded ?? 0 )
							}
							busy={ refund.isPending }
							onSubmit={ ( value, reason ) =>
								refund.mutate( {
									payment_id: refunding,
									amount: value,
									reason,
								} )
							}
							onCancel={ () => setRefunding( null ) }
						/>
					) }
					<p>
						{ __( 'Left to pay', 'vaqtyar' ) }:{ ' ' }
						<strong>
							{ money( { amount: left, currency: 'IRR' } ) }
						</strong>
					</p>
					<form
						className="vqy-admin__inline-form"
						onSubmit={ submit }
					>
						<IntField
							label={ __( 'Money received (IRR)', 'vaqtyar' ) }
							min={ 1 }
							value={ amount ?? left }
							onChange={ setAmount }
						/>
						<Button
							variant="secondary"
							type="submit"
							isBusy={ record.isPending }
							disabled={
								record.isPending || ( amount ?? left ) < 1
							}
						>
							{ __( 'Record payment', 'vaqtyar' ) }
						</Button>
					</form>
					<p className="vqy-admin__muted">
						{ __(
							'This records money you received yourself (cash, a card machine, a transfer). A refund is recorded after you have paid it back, in the gateway’s panel or by transfer: nothing is sent from here.',
							'vaqtyar'
						) }
					</p>
				</>
			) }
		</section>
	);
}

function RefundForm( {
	max,
	busy,
	onSubmit,
	onCancel,
}: {
	max: number;
	busy: boolean;
	onSubmit: ( amount: number, reason: string ) => void;
	onCancel: () => void;
} ) {
	const [ amount, setAmount ] = useState( max );
	const [ reason, setReason ] = useState( '' );

	return (
		<form
			className="vqy-admin__inline-form"
			onSubmit={ ( event ) => {
				event.preventDefault();
				onSubmit( amount, reason );
			} }
		>
			<IntField
				label={ __( 'Refund amount (IRR)', 'vaqtyar' ) }
				min={ 1 }
				value={ amount }
				onChange={ ( value ) => setAmount( Math.min( max, value ) ) }
			/>
			<TextControl
				{ ...SIZE }
				label={ __( 'Reason', 'vaqtyar' ) }
				value={ reason }
				onChange={ setReason }
			/>
			<Button
				variant="primary"
				type="submit"
				isBusy={ busy }
				disabled={ busy || amount < 1 }
			>
				{ __( 'Save refund', 'vaqtyar' ) }
			</Button>
			<Button variant="tertiary" onClick={ onCancel }>
				{ __( 'Cancel', 'vaqtyar' ) }
			</Button>
		</form>
	);
}
