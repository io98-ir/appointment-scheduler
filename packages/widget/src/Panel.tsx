import {
	ApiClient,
	ApiError,
	downloadIcs,
	formatAmount,
	formatDate,
	formatDigits,
	type AppointmentStatus,
	type AvailabilityDay,
	type AvailabilitySlot,
	type Calendar,
	type Digits,
	type PanelAppointment,
	type PolicyDecision,
} from '@vaqtyar/shared';
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useId, useState } from 'preact/hooks';

import { DateInput } from './DateInput';
import { PhoneCheck } from './PhoneCheck';
import type { WidgetConfig } from './Widget';
import { useFetch } from './useFetch';

const STORAGE_KEY = 'vqy-panel-session';

interface Session {
	token: string;
	/** ms since the epoch. */
	expiresAt: number;
}

function readSession(): string | null {
	try {
		const stored: unknown = JSON.parse(
			window.sessionStorage.getItem( STORAGE_KEY ) ?? 'null'
		);
		const session = stored as Session | null;

		return session && session.expiresAt > Date.now() ? session.token : null;
	} catch {
		return null;
	}
}

function writeSession( token: string | null ): void {
	try {
		if ( token === null ) {
			window.sessionStorage.removeItem( STORAGE_KEY );
		} else {
			// The server's own limit is 30 minutes; expire a little earlier.
			const session: Session = {
				token,
				expiresAt: Date.now() + 29 * 60 * 1000,
			};
			window.sessionStorage.setItem(
				STORAGE_KEY,
				JSON.stringify( session )
			);
		}
	} catch {
		// Storage can be blocked; the session then lasts until reload.
	}
}

function statusLabel( status: AppointmentStatus ): string {
	const labels: Record< AppointmentStatus, string > = {
		pending_approval: __( 'Awaiting approval', 'vaqtyar' ),
		pending_payment: __( 'Awaiting payment', 'vaqtyar' ),
		confirmed: __( 'Confirmed', 'vaqtyar' ),
		completed: __( 'Completed', 'vaqtyar' ),
		no_show: __( 'Missed', 'vaqtyar' ),
		cancelled: __( 'Cancelled', 'vaqtyar' ),
		expired: __( 'Expired', 'vaqtyar' ),
	};

	return labels[ status ];
}

function whyNot( decision: PolicyDecision ): string {
	const reasons: Record< string, string > = {
		'policy.cancel_window_passed': __(
			'It is too late to cancel this appointment online.',
			'vaqtyar'
		),
		'policy.reschedule_window_passed': __(
			'It is too late to move this appointment online.',
			'vaqtyar'
		),
		'policy.reschedule_limit_reached': __(
			'This appointment has already been moved as many times as allowed.',
			'vaqtyar'
		),
		'policy.already_started': __(
			'This appointment has started.',
			'vaqtyar'
		),
	};

	return (
		reasons[ decision.reason_code ?? '' ] ??
		__( 'This change is not allowed now.', 'vaqtyar' )
	);
}

/**
 * The customer panel (T4.4): sign in with the phone number and a one-time
 * code, then see, cancel and move your own appointments, each with what the
 * policy says first. The session lives in sessionStorage for the tab.
 *
 * @param props
 * @param props.config
 * @param props.api    For tests.
 */
export function Panel( {
	config,
	api,
}: {
	config: WidgetConfig;
	api?: ApiClient | undefined;
} ) {
	const calendar: Calendar = config.calendar ?? 'jalali';
	const digits: Digits = config.digits ?? 'latin';
	const [ session, setSessionState ] = useState< string | null >(
		readSession
	);
	const setSession = ( token: string | null ) => {
		writeSession( token );
		setSessionState( token );
	};
	const clientFor = ( nonce?: string, token?: string ): ApiClient =>
		api ??
		new ApiClient( {
			baseUrl: config.restUrl ?? '',
			...( nonce === undefined ? {} : { nonce } ),
			...( token === undefined ? {} : { session: token } ),
		} );
	const [ phone, setPhone ] = useState( '' );
	const uid = useId();
	const nonce = useFetch< { nonce: string } >(
		config.restUrl !== undefined || api !== undefined ? 'nonce' : null,
		() => clientFor().get< { nonce: string } >( '/nonce' )
	);

	return (
		<section
			className="vqy-widget vqy-panel"
			aria-label={ __( 'My appointments', 'vaqtyar' ) }
		>
			<p className="vqy-widget__title">
				{ __( 'My appointments', 'vaqtyar' ) }
			</p>
			{ config.restUrl === undefined && api === undefined && (
				<p role="alert" className="vqy-widget__error">
					{ __( 'The booking form is not configured.', 'vaqtyar' ) }
				</p>
			) }
			{ nonce.error && (
				<p role="alert" className="vqy-widget__error">
					{ nonce.error }
				</p>
			) }
			{ session === null && nonce.data && (
				<div className="vqy-widget__form">
					<label htmlFor={ `${ uid }-phone` }>
						{ __( 'Mobile number', 'vaqtyar' ) }
						<input
							id={ `${ uid }-phone` }
							type="tel"
							dir="ltr"
							value={ phone }
							maxLength={ 32 }
							onInput={ ( e ) =>
								setPhone( e.currentTarget.value )
							}
						/>
					</label>
					<PhoneCheck
						key={ phone }
						phone={ phone }
						nonce={ nonce.data.nonce }
						clientFor={ clientFor }
						digits={ digits }
						verified={ false }
						onVerified={ setSession }
					/>
				</div>
			) }
			{ session !== null && nonce.data && (
				<Appointments
					nonce={ nonce.data.nonce }
					token={ session }
					clientFor={ clientFor }
					calendar={ calendar }
					digits={ digits }
					onSignOut={ () => setSession( null ) }
				/>
			) }
		</section>
	);
}

function Appointments( {
	nonce,
	token,
	clientFor,
	calendar,
	digits,
	onSignOut,
}: {
	nonce: string;
	token: string;
	clientFor: ( nonce?: string, token?: string ) => ApiClient;
	calendar: Calendar;
	digits: Digits;
	onSignOut: () => void;
} ) {
	const [ version, setVersion ] = useState( 0 );
	const list = useFetch< PanelAppointment[] >( `list:${ version }`, () =>
		clientFor( nonce, token ).get< PanelAppointment[] >(
			'/my/appointments'
		)
	);
	const [ cancelling, setCancelling ] = useState< number | null >( null );
	const [ moving, setMoving ] = useState< number | null >( null );
	const [ error, setError ] = useState< string | null >( null );
	const [ busy, setBusy ] = useState( false );

	// A session the server no longer accepts (expired, or a new site salt).
	useEffect( () => {
		if ( list.status === 401 || list.status === 403 ) {
			onSignOut();
		}
	}, [ list.status ] );

	const act = async ( path: string, body: Record< string, unknown > ) => {
		setBusy( true );
		setError( null );
		try {
			await clientFor( nonce, token ).post( path, body );
			setCancelling( null );
			setMoving( null );
			setVersion( version + 1 );
		} catch ( failure ) {
			if ( failure instanceof ApiError && failure.status === 401 ) {
				onSignOut();

				return;
			}
			setError(
				failure instanceof Error ? failure.message : String( failure )
			);
		} finally {
			setBusy( false );
		}
	};
	// What is left after a deposit is paid at the gateway, like a booking's own payment.
	const payRest = async ( id: number ) => {
		setBusy( true );
		setError( null );
		try {
			const { payment_url: url } = await clientFor( nonce, token ).post< {
				payment_url: string;
			} >( `/my/appointments/${ id }/pay`, {
				return_url: window.location.href,
			} );
			window.location.assign( url );
		} catch ( failure ) {
			setError(
				failure instanceof Error ? failure.message : String( failure )
			);
			setBusy( false );
		}
	};
	const when = ( start: string ) =>
		`${ formatDate( start.slice( 0, 10 ), calendar, digits ) } ${ formatDigits(
			start.slice( 11, 16 ),
			digits
		) }`;

	return (
		<div className="vqy-panel__list">
			<button type="button" onClick={ onSignOut }>
				{ __( 'Sign out', 'vaqtyar' ) }
			</button>
			{ list.loading && <p>{ __( 'Loading…', 'vaqtyar' ) }</p> }
			{ list.error && (
				<p role="alert" className="vqy-widget__error">
					{ list.error }
				</p>
			) }
			{ error && (
				<p role="alert" className="vqy-widget__error">
					{ error }
				</p>
			) }
			{ list.data?.length === 0 && (
				<p>{ __( 'You have no appointments yet.', 'vaqtyar' ) }</p>
			) }
			<ul className="vqy-panel__items">
				{ list.data?.map( ( item ) => (
					<li key={ item.id } className="vqy-panel__item">
						<strong>{ when( item.start ) }</strong>
						{ ' — ' }
						{ statusLabel( item.status ) }
						{ ' — ' }
						{ sprintf(
							/* translators: %s: an amount in rials. */
							__( '%s IRR', 'vaqtyar' ),
							formatAmount( item.total, digits )
						) }
						<br />
						<span dir="ltr">
							{ sprintf(
								/* translators: %s: an 8-character tracking code. */
								__( 'Tracking code: %s', 'vaqtyar' ),
								item.code
							) }
						</span>
						{ item.due && (
							<p className="vqy-panel__due">
								{ sprintf(
									/* translators: %s: an amount in rials. */
									__( 'Left to pay: %s IRR', 'vaqtyar' ),
									formatAmount( item.due, digits )
								) }{ ' ' }
								<button
									type="button"
									disabled={ busy }
									onClick={ () => void payRest( item.id ) }
								>
									{ __( 'Pay the rest', 'vaqtyar' ) }
								</button>
							</p>
						) }
						{ item.cancel && item.reschedule && (
							<div className="vqy-panel__actions">
								{ item.status === 'confirmed' && (
									<button
										type="button"
										onClick={ () =>
											downloadIcs(
												{
													uid: item.code,
													start: item.start,
													end: item.end,
													summary: sprintf(
														/* translators: %s: an 8-character tracking code. */
														__(
															'Appointment %s',
															'vaqtyar'
														),
														item.code
													),
												},
												item.code
											)
										}
									>
										{ __( 'Add to calendar', 'vaqtyar' ) }
									</button>
								) }
								{ item.reschedule.allowed ? (
									<button
										type="button"
										disabled={ busy }
										onClick={ () => {
											setMoving( item.id );
											setCancelling( null );
										} }
									>
										{ __( 'Move', 'vaqtyar' ) }
									</button>
								) : (
									<span className="vqy-widget__muted">
										{ whyNot( item.reschedule ) }
									</span>
								) }
								{ item.cancel.allowed ? (
									<button
										type="button"
										disabled={ busy }
										onClick={ () => {
											setCancelling( item.id );
											setMoving( null );
										} }
									>
										{ __(
											'Cancel appointment',
											'vaqtyar'
										) }
									</button>
								) : (
									<span className="vqy-widget__muted">
										{ whyNot( item.cancel ) }
									</span>
								) }
							</div>
						) }
						{ cancelling === item.id && item.cancel && (
							<div
								role="alertdialog"
								className="vqy-panel__confirm"
							>
								<p>
									{ item.cancel.refund_percent > 0
										? sprintf(
												/* translators: %d: a percentage. */
												__(
													'Cancel this appointment? %d%% of what you paid is refunded.',
													'vaqtyar'
												),
												item.cancel.refund_percent
											)
										: __(
												'Cancel this appointment?',
												'vaqtyar'
											) }
								</p>
								<button
									type="button"
									disabled={ busy }
									onClick={ () =>
										void act(
											`/my/appointments/${ item.id }/cancel`,
											{}
										)
									}
								>
									{ __( 'Yes, cancel it', 'vaqtyar' ) }
								</button>
								<button
									type="button"
									onClick={ () => setCancelling( null ) }
								>
									{ __( 'Keep it', 'vaqtyar' ) }
								</button>
							</div>
						) }
						{ moving === item.id && (
							<Mover
								item={ item }
								clientFor={ clientFor }
								calendar={ calendar }
								digits={ digits }
								busy={ busy }
								onPick={ ( slot ) =>
									void act(
										`/my/appointments/${ item.id }/reschedule`,
										{
											start: slot.start,
										}
									)
								}
								onClose={ () => setMoving( null ) }
							/>
						) }
					</li>
				) ) }
			</ul>
		</div>
	);
}

/**
 * Picks a new start for an appointment: a date, then that day's free starts
 * for the same service, place and staff member.
 *
 * @param props
 * @param props.item
 * @param props.clientFor
 * @param props.calendar
 * @param props.digits
 * @param props.busy
 * @param props.onPick
 * @param props.onClose
 */
function Mover( {
	item,
	clientFor,
	calendar,
	digits,
	busy,
	onPick,
	onClose,
}: {
	item: PanelAppointment;
	clientFor: ( nonce?: string, token?: string ) => ApiClient;
	calendar: Calendar;
	digits: Digits;
	busy: boolean;
	onPick: ( slot: AvailabilitySlot ) => void;
	onClose: () => void;
} ) {
	const uid = useId();
	const [ date, setDate ] = useState( '' );
	const day = useFetch< AvailabilityDay >(
		date === '' ? null : `move:${ item.id }:${ date }`,
		() =>
			clientFor().get< AvailabilityDay >( '/availability', {
				variant: item.variant_id,
				location: item.location_id,
				staff: item.staff_id,
				view: 'day',
				date,
			} )
	);

	return (
		<div className="vqy-panel__move">
			<label htmlFor={ `${ uid }-date` }>
				{ __( 'New date', 'vaqtyar' ) }
				<DateInput
					id={ `${ uid }-date` }
					value={ date }
					calendar={ calendar }
					digits={ digits }
					onChange={ setDate }
				/>
			</label>
			{ day.loading && <p>{ __( 'Loading…', 'vaqtyar' ) }</p> }
			{ day.error && (
				<p role="alert" className="vqy-widget__error">
					{ day.error }
				</p>
			) }
			{ day.data?.slots.length === 0 && (
				<p>{ __( 'No free time on this day.', 'vaqtyar' ) }</p>
			) }
			<ul className="vqy-widget__slot-list">
				{ day.data?.slots.map( ( slot ) => (
					<li key={ slot.start }>
						<button
							type="button"
							disabled={ busy }
							onClick={ () => onPick( slot ) }
						>
							{ formatDigits(
								slot.start.slice( 11, 16 ),
								digits
							) }
						</button>
					</li>
				) ) }
			</ul>
			<button type="button" onClick={ onClose }>
				{ __( 'Close', 'vaqtyar' ) }
			</button>
		</div>
	);
}
