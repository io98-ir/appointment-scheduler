import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
	type AppointmentDetail,
	type Extra,
	type PriceLineCode,
	type Service,
	type Staff,
} from '@vaqtyar/shared';
import {
	Button,
	Modal,
	Notice,
	Spinner,
	TextareaControl,
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import type { FormEvent } from 'react';

import { useApi } from '../api';
import { useAll } from '../catalog/crud';
import { errorMessage } from '../query';
import { act, actionsFor, type Action } from './actions';
import { paymentLabel, rial, statusLabel, when } from './AppointmentsPage';

function actionLabel( action: Action ): string {
	const labels: Record< Action, string > = {
		approve: __( 'Approve', 'vaqtyar' ),
		complete: __( 'Mark completed', 'vaqtyar' ),
		'no-show': __( 'Mark no-show', 'vaqtyar' ),
		cancel: __( 'Cancel appointment', 'vaqtyar' ),
	};

	return labels[ action ];
}

function lineLabel( code: PriceLineCode ): string {
	const labels: Record< PriceLineCode, string > = {
		base: __( 'Service', 'vaqtyar' ),
		time_rule: __( 'Time pricing', 'vaqtyar' ),
		extra: __( 'Add-on', 'vaqtyar' ),
		party: __( 'Party', 'vaqtyar' ),
		coupon: __( 'Coupon', 'vaqtyar' ),
		rounding: __( 'Rounding', 'vaqtyar' ),
	};

	return labels[ code ];
}

function historyLabel( action: string ): string {
	const labels: Record< string, string > = {
		created: __( 'Booked', 'vaqtyar' ),
		approve: __( 'Approved', 'vaqtyar' ),
		paid: __( 'Paid', 'vaqtyar' ),
		expire: __( 'Expired', 'vaqtyar' ),
		complete: __( 'Completed', 'vaqtyar' ),
		no_show: __( 'No-show', 'vaqtyar' ),
		cancel: __( 'Cancelled', 'vaqtyar' ),
		reschedule: __( 'Moved', 'vaqtyar' ),
		note: __( 'Internal note changed', 'vaqtyar' ),
	};

	return labels[ action ] ?? action;
}

/**
 * One appointment: what was booked and for how much, its history, the
 * staff-only note, and the status changes it allows. Payments are shown
 * as a status until the payments module (M5) records them.
 *
 * @param props
 * @param props.id
 */
export function AppointmentDetailPage( { id }: { id: number } ) {
	const api = useApi();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const path = `/appointments/${ id }`;
	const item = useQuery( {
		queryKey: [ '/appointments', id ],
		queryFn: () => api.get< AppointmentDetail >( path ),
	} );
	const staff = useAll< Staff >( '/staff' );
	const services = useAll< Service >( '/services' );
	const extras = useAll< Extra >( '/extras' );
	const [ cancelling, setCancelling ] = useState( false );
	const [ refusal, setRefusal ] = useState< string | null >( null );
	const changed = ( message: string ) => {
		void client.invalidateQueries( { queryKey: [ '/appointments' ] } );
		void client.invalidateQueries( { queryKey: [ '/calendar' ] } );
		void createSuccessNotice( message, { type: 'snackbar' } );
	};
	const change = useMutation( {
		mutationFn: ( request: {
			action: Action;
			reason?: string;
			override?: boolean;
		} ) => {
			const { action, ...body } = request;

			return act( api, id, action, body );
		},
		onSuccess: ( result ) => {
			if ( ! result.done ) {
				setRefusal( result.refusal );

				return;
			}
			setCancelling( false );
			setRefusal( null );
			changed( __( 'Appointment updated.', 'vaqtyar' ) );
		},
	} );

	if ( item.isError ) {
		return (
			<>
				<BackLink />
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( item.error ) }
				</Notice>
			</>
		);
	}
	if ( ! item.data ) {
		return <Spinner />;
	}
	const appointment = item.data;
	const service = services.data?.find(
		( candidate ) => candidate.id === appointment.service_id
	);
	const variant = service?.variants.find(
		( candidate ) => candidate.id === appointment.variant_id
	);
	const staffName = ( staffId: number | null ) =>
		staff.data?.find( ( person ) => person.id === staffId )?.name ?? '—';
	const extraName = ( extraId: number | null ) =>
		extras.data?.find( ( extra ) => extra.id === extraId )?.name ?? '—';

	return (
		<div className="vqy-appointment">
			<BackLink />
			<h2 className="vqy-appointment__title">
				<span dir="ltr">{ appointment.code }</span>{ ' ' }
				<span
					className={ `vqy-status vqy-status--${ appointment.status.replace( /_/g, '-' ) }` }
				>
					{ statusLabel( appointment.status ) }
				</span>
			</h2>
			<div className="vqy-admin__toolbar">
				{ actionsFor( appointment.status ).map( ( action ) => (
					<Button
						key={ action }
						variant={
							action === 'cancel' ? 'secondary' : 'primary'
						}
						isDestructive={ action === 'cancel' }
						isBusy={
							change.isPending &&
							change.variables.action === action
						}
						disabled={ change.isPending }
						onClick={ () =>
							action === 'cancel'
								? setCancelling( true )
								: change.mutate( { action } )
						}
					>
						{ actionLabel( action ) }
					</Button>
				) ) }
				{ appointment.status === 'confirmed' && (
					<Button
						variant="tertiary"
						href={ `#/calendar/day/${ appointment.start.slice(
							0,
							10
						) }` }
					>
						{ __( 'Move in calendar', 'vaqtyar' ) }
					</Button>
				) }
			</div>
			<dl className="vqy-appointment__facts">
				<dt>{ __( 'Time', 'vaqtyar' ) }</dt>
				<dd dir="ltr">
					{ when( appointment.start ) }–
					{ appointment.end.slice( 11, 16 ) }
				</dd>
				<dt>{ __( 'Customer', 'vaqtyar' ) }</dt>
				<dd>
					<span dir="auto">
						{ appointment.customer?.name ?? '—' }
					</span>
					{ appointment.customer?.phone && (
						<>
							{ ' ' }
							<a
								dir="ltr"
								href={ `tel:${ appointment.customer.phone }` }
							>
								{ appointment.customer.phone }
							</a>
						</>
					) }
				</dd>
				<dt>{ __( 'Service', 'vaqtyar' ) }</dt>
				<dd dir="auto">
					{ service?.name ?? '—' }
					{ variant && service && service.variants.length > 1
						? ` · ${ variant.label }`
						: '' }
				</dd>
				<dt>{ __( 'Staff', 'vaqtyar' ) }</dt>
				<dd dir="auto">{ staffName( appointment.staff_id ) }</dd>
				<dt>{ __( 'People', 'vaqtyar' ) }</dt>
				<dd>{ appointment.party_size }</dd>
				<dt>{ __( 'Payment', 'vaqtyar' ) }</dt>
				<dd>{ paymentLabel( appointment.payment_status ) }</dd>
				<dt>{ __( 'Booked', 'vaqtyar' ) }</dt>
				<dd dir="ltr">{ when( appointment.created_at ) }</dd>
				{ appointment.cancel_reason && (
					<>
						<dt>{ __( 'Cancel reason', 'vaqtyar' ) }</dt>
						<dd dir="auto">{ appointment.cancel_reason }</dd>
					</>
				) }
			</dl>

			<h3>{ __( 'Price', 'vaqtyar' ) }</h3>
			<table className="widefat vqy-admin__table">
				<tbody>
					{ appointment.price.lines.map( ( line, index ) => (
						<tr key={ index }>
							<td dir="auto">
								{ lineLabel( line.code ) }
								{ line.code === 'extra' &&
									` · ${ extraName( line.ref ) } × ${ line.qty }` }
							</td>
							<td>{ rial( line.amount ) }</td>
						</tr>
					) ) }
					<tr>
						<th scope="row">{ __( 'Total', 'vaqtyar' ) }</th>
						<td>
							<strong>{ rial( appointment.price.total ) }</strong>
						</td>
					</tr>
				</tbody>
			</table>

			{ Object.keys( appointment.answers ).length > 0 && (
				<>
					<h3>{ __( 'Answers', 'vaqtyar' ) }</h3>
					<dl className="vqy-appointment__facts">
						{ Object.entries( appointment.answers ).map(
							( [ key, value ] ) => (
								<div key={ key }>
									<dt dir="ltr">{ key }</dt>
									<dd dir="auto">{ value }</dd>
								</div>
							)
						) }
					</dl>
				</>
			) }

			{ appointment.customer_note !== '' && (
				<>
					<h3>{ __( 'Customer note', 'vaqtyar' ) }</h3>
					<p dir="auto">{ appointment.customer_note }</p>
				</>
			) }

			<InternalNote
				key={ appointment.internal_note }
				id={ id }
				note={ appointment.internal_note }
				onSaved={ () => changed( __( 'Note saved.', 'vaqtyar' ) ) }
			/>

			<h3>{ __( 'History', 'vaqtyar' ) }</h3>
			<ol className="vqy-appointment__history">
				{ appointment.history.map( ( entry, index ) => (
					<li key={ index }>
						<span dir="ltr">{ when( entry.at ) }</span>{ ' ' }
						{ historyLabel( entry.action ) }
						{ entry.actor_type === 'customer' &&
							` (${ __( 'by the customer', 'vaqtyar' ) })` }
						{ entry.reason && (
							<>
								{ ': ' }
								<span dir="auto">{ entry.reason }</span>
							</>
						) }
					</li>
				) ) }
			</ol>

			{ cancelling && (
				<CancelDialog
					refusal={ refusal }
					busy={ change.isPending }
					onConfirm={ ( reason ) =>
						change.mutate( {
							action: 'cancel',
							reason,
							override: refusal !== null,
						} )
					}
					onClose={ () => {
						setCancelling( false );
						setRefusal( null );
					} }
				/>
			) }
		</div>
	);
}

function BackLink() {
	return (
		<p>
			<a href="#/appointments">
				{ __( '← All appointments', 'vaqtyar' ) }
			</a>
		</p>
	);
}

function InternalNote( {
	id,
	note,
	onSaved,
}: {
	id: number;
	note: string;
	onSaved: () => void;
} ) {
	const api = useApi();
	const [ draft, setDraft ] = useState( note );
	const save = useMutation( {
		mutationFn: () =>
			api.put( `/appointments/${ id }/note`, { note: draft } ),
		onSuccess: onSaved,
	} );

	return (
		<form
			className="vqy-admin__form"
			onSubmit={ ( event: FormEvent ) => {
				event.preventDefault();
				save.mutate();
			} }
		>
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Internal note', 'vaqtyar' ) }
				help={ __( 'Only staff see this.', 'vaqtyar' ) }
				value={ draft }
				maxLength={ 5000 }
				onChange={ setDraft }
			/>
			<div className="vqy-admin__buttons">
				<Button
					variant="secondary"
					type="submit"
					isBusy={ save.isPending }
					disabled={ save.isPending || draft === note }
				>
					{ __( 'Save note', 'vaqtyar' ) }
				</Button>
			</div>
		</form>
	);
}

/**
 * Asks for a reason; after the policy refuses, offers to cancel anyway,
 * which needs the reason for the history.
 *
 * @param props
 * @param props.refusal
 * @param props.busy
 * @param props.onConfirm
 * @param props.onClose
 */
function CancelDialog( {
	refusal,
	busy,
	onConfirm,
	onClose,
}: {
	refusal: string | null;
	busy: boolean;
	onConfirm: ( reason: string ) => void;
	onClose: () => void;
} ) {
	const [ reason, setReason ] = useState( '' );

	return (
		<Modal
			title={ __( 'Cancel this appointment?', 'vaqtyar' ) }
			onRequestClose={ onClose }
		>
			<form
				className="vqy-admin__form"
				onSubmit={ ( event: FormEvent ) => {
					event.preventDefault();
					onConfirm( reason );
				} }
			>
				{ refusal !== null && (
					<Notice status="warning" isDismissible={ false }>
						{ refusal }
					</Notice>
				) }
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'Reason', 'vaqtyar' ) }
					value={ reason }
					maxLength={ 1000 }
					required={ refusal !== null }
					onChange={ setReason }
				/>
				<div className="vqy-admin__buttons">
					<Button
						variant="primary"
						isDestructive
						type="submit"
						isBusy={ busy }
						disabled={
							busy || ( refusal !== null && reason.trim() === '' )
						}
					>
						{ refusal === null
							? __( 'Cancel appointment', 'vaqtyar' )
							: __( 'Cancel anyway', 'vaqtyar' ) }
					</Button>
					<Button variant="tertiary" onClick={ onClose }>
						{ __( 'Keep it', 'vaqtyar' ) }
					</Button>
				</div>
			</form>
		</Modal>
	);
}
