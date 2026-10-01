import {
	ApiError,
	downloadIcs,
	formatAmount,
	formatDate,
	formatDigits,
	type ApiClient,
	type Calendar,
	type Digits,
	type GuestBooking,
	type Money,
	type PlacedHold,
	type PriceLineCode,
	type PublicField,
} from '@vaqtyar/shared';
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useId, useRef, useState } from 'preact/hooks';

import { PhoneCheck } from './PhoneCheck';
import type { SlotChoice } from './Widget';
import { useFetch } from './useFetch';

/** GET /payment-options with a service and its price (docs/api.md). */
interface PaymentOptions {
	online: boolean;
	required?: boolean;
	/** What is charged online now: the whole price, or a deposit of it. */
	due?: Money | null;
	/** Staff approve the booking before it is confirmed. */
	approval?: boolean;
}

interface Placed {
	nonce: string;
	hold: PlacedHold;
}

type Answers = Record< string, string | boolean >;

/**
 * The thank-you address with the tracking code added, so that page can show it.
 *
 * @param url  An address of this site.
 * @param code The booking's tracking code.
 */
export function withCode( url: string, code: string ): string {
	const target = new URL( url, window.location.href );
	target.searchParams.set( 'code', code );

	return target.toString();
}

function lineLabel( code: PriceLineCode ): string {
	const labels: Record< PriceLineCode, string > = {
		base: __( 'Price', 'vaqtyar' ),
		time_rule: __( 'Time-based price', 'vaqtyar' ),
		extra: __( 'Add-on', 'vaqtyar' ),
		party: __( 'Guests', 'vaqtyar' ),
		coupon: __( 'Coupon', 'vaqtyar' ),
		rounding: __( 'Rounding', 'vaqtyar' ),
	};

	return labels[ code ];
}

/**
 * The fields a booking shows: a field with a show_if only when the answer it
 * names equals the given value, in order, as the server checks them.
 *
 * @param fields  In the order they are shown.
 * @param answers What the customer typed so far.
 */
export function visibleFields(
	fields: PublicField[],
	answers: Answers
): PublicField[] {
	const shown: PublicField[] = [];
	for ( const field of fields ) {
		const condition = field.show_if;
		const seen = condition
			? shown.find( ( item ) => item.field_key === condition.field )
			: undefined;
		const value = condition ? answers[ condition.field ] : undefined;
		const normalized = normalize( value );
		if (
			condition === null ||
			( seen !== undefined && normalized === condition.equals )
		) {
			shown.push( field );
		}
	}

	return shown;
}

/**
 * An answer as a show_if compares it: a checkbox is "1" or "0".
 *
 * @param value
 */
function normalize( value: string | boolean | undefined ): string {
	if ( typeof value === 'boolean' ) {
		return value ? '1' : '0';
	}

	return value ?? '';
}

function secondsLeft( expiresAt: string ): number {
	return Math.max(
		0,
		Math.round( ( new Date( expiresAt ).getTime() - Date.now() ) / 1000 )
	);
}

/**
 * From a chosen start to a confirmed appointment (T4.2): the slot is held
 * while the customer fills the form, with a countdown, and the price is
 * shown before they confirm. The hold and the booking each use a fresh
 * REST nonce, since a cached page's is stale.
 *
 * @param props
 * @param props.choice    The start the customer picked.
 * @param props.serviceId
 * @param props.coupon    A code to apply when the hold is placed, or "".
 * @param props.title     What the appointment is called in a calendar file, e.g. the service.
 * @param props.thanksUrl Where to go after a booking that needs no payment page, or "".
 * @param props.calendar
 * @param props.digits
 * @param props.clientFor A client that sends the given nonce.
 * @param props.onBack    Back to choosing a time, after an expired or taken hold.
 */
export function BookingFlow( {
	choice,
	serviceId,
	coupon,
	title,
	thanksUrl,
	calendar,
	digits,
	clientFor,
	onBack,
}: {
	choice: SlotChoice;
	serviceId: number;
	coupon: string;
	title: string;
	thanksUrl: string;
	calendar: Calendar;
	digits: Digits;
	clientFor: ( nonce?: string ) => ApiClient;
	onBack: () => void;
} ) {
	const uid = useId();
	const placed = useFetch< Placed >(
		`hold:${ choice.slot.start }:${ choice.staff ?? '' }:${ coupon }`,
		async () => {
			const { nonce } = await clientFor().get< { nonce: string } >(
				'/nonce'
			);
			const hold = await clientFor( nonce ).post< PlacedHold >(
				'/holds',
				{
					variant: choice.variant,
					location: choice.location,
					start: choice.slot.start,
					staff: choice.staff ?? undefined,
					coupon: coupon === '' ? undefined : coupon,
				}
			);

			return { nonce, hold };
		}
	);
	const fields = useFetch< PublicField[] >( `fields:${ serviceId }`, () =>
		clientFor().get< PublicField[] >( '/service-fields', {
			service: serviceId,
		} )
	);
	const otp = useFetch< { required: boolean } >( 'otp-config', () =>
		clientFor().get< { required: boolean } >( '/otp/config' )
	);
	// What the service asks of the booking depends on the price the hold quoted, so this waits for it.
	const quoted = placed.data?.hold.price.total.amount;
	const paymentOptions = useFetch< PaymentOptions >(
		quoted === undefined
			? null
			: `payment-options:${ serviceId }:${ quoted }`,
		() =>
			clientFor().get< PaymentOptions >( '/payment-options', {
				service: serviceId,
				total: quoted,
			} )
	);
	// Which submit button was pressed: pay now, or pay at the place.
	const payOnline = useRef( false );
	const [ redirecting, setRedirecting ] = useState( false );
	const [ session, setSession ] = useState< string | null >( null );
	const [ firstName, setFirstName ] = useState( '' );
	const [ lastName, setLastName ] = useState( '' );
	const [ phone, setPhone ] = useState( '' );
	const [ email, setEmail ] = useState( '' );
	const [ note, setNote ] = useState( '' );
	const [ answers, setAnswers ] = useState< Answers >( {} );
	const [ booking, setBooking ] = useState< GuestBooking | null >( null );
	const [ error, setError ] = useState< ApiError | Error | null >( null );
	const [ busy, setBusy ] = useState( false );
	const [ left, setLeft ] = useState( 0 );

	// The thank-you page of the site, when the shortcode names one (a booking that
	// goes to a payment page has its own way back).
	useEffect( () => {
		if ( booking && thanksUrl !== '' ) {
			window.location.assign( withCode( thanksUrl, booking.code ) );
		}
	}, [ booking, thanksUrl ] );

	const expiresAt = placed.data?.hold.expires_at;
	useEffect( () => {
		if ( expiresAt === undefined || booking ) {
			return undefined;
		}
		setLeft( secondsLeft( expiresAt ) );
		const timer = window.setInterval(
			() => setLeft( secondsLeft( expiresAt ) ),
			1000
		);

		return () => window.clearInterval( timer );
	}, [ expiresAt, booking ] );

	const holdError = placed.error;
	if ( redirecting ) {
		return (
			<p role="status">
				{ __( 'Taking you to the payment page…', 'vaqtyar' ) }
			</p>
		);
	}
	if ( booking ) {
		return (
			<div className="vqy-widget__done" role="status">
				<p>
					<strong>
						{ booking.status === 'pending_approval'
							? __(
									'Your request is received. It is confirmed once the staff approve it.',
									'vaqtyar'
								)
							: __( 'Your appointment is booked.', 'vaqtyar' ) }
					</strong>
				</p>
				<p>
					{ sprintf(
						/* translators: 1: a date, 2: a time. */
						__( '%1$s at %2$s', 'vaqtyar' ),
						formatDate(
							booking.start.slice( 0, 10 ),
							calendar,
							digits
						),
						formatDigits( booking.start.slice( 11, 16 ), digits )
					) }
				</p>
				<p>
					{ sprintf(
						/* translators: %s: an 8-character tracking code. */
						__( 'Tracking code: %s', 'vaqtyar' ),
						booking.code
					) }
				</p>
				{ booking.status === 'confirmed' && (
					<button
						type="button"
						onClick={ () =>
							downloadIcs(
								{
									uid: booking.code,
									start: booking.start,
									end: booking.end,
									summary: title,
								},
								booking.code
							)
						}
					>
						{ __( 'Add to calendar', 'vaqtyar' ) }
					</button>
				) }
			</div>
		);
	}
	if ( holdError ) {
		return (
			<div>
				<p role="alert" className="vqy-widget__error">
					{ holdError }
				</p>
				<button type="button" onClick={ onBack }>
					{ __( 'Choose another time', 'vaqtyar' ) }
				</button>
			</div>
		);
	}
	if ( ! placed.data || otp.loading || paymentOptions.loading ) {
		return <p>{ __( 'Reserving your time…', 'vaqtyar' ) }</p>;
	}

	const { hold, nonce } = placed.data;
	const expired = left <= 0;
	const shown = visibleFields( fields.data ?? [], answers );
	const wrong =
		error instanceof ApiError && typeof error.details.field_key === 'string'
			? error.details.field_key
			: null;
	// Free bookings have nothing to pay, whatever the site offers.
	const payable =
		paymentOptions.data?.online === true && hold.price.total.amount > 0;
	// The service wants the booking paid online, and a deposit may be only part of the price.
	const mustPay = paymentOptions.data?.required === true;
	const due = paymentOptions.data?.due ?? null;
	const isDeposit = due !== null && due.amount < hold.price.total.amount;
	const blocked =
		busy || expired || ( otp.data?.required === true && session === null );
	const minutes = String( Math.floor( left / 60 ) );
	const seconds = String( left % 60 ).padStart( 2, '0' );

	const submit = async ( event: Event ) => {
		event.preventDefault();
		setBusy( true );
		setError( null );
		try {
			const booked = await clientFor( nonce ).post< GuestBooking >(
				'/book',
				{
					hold_token: hold.token,
					first_name: firstName,
					last_name: lastName,
					phone,
					email: email.trim() === '' ? null : email.trim(),
					customer_note: note,
					session_token: session,
					pay_online: payOnline.current,
					return_url: window.location.href,
					answers: Object.fromEntries(
						shown
							.filter( ( field ) => field.field_key in answers )
							.map( ( field ) => [
								field.field_key,
								answers[ field.field_key ],
							] )
					),
				}
			);
			if ( booked.payment_url ) {
				setRedirecting( true );
				window.location.assign( booked.payment_url );

				return;
			}
			setBooking( booked );
		} catch ( failure ) {
			setError(
				failure instanceof Error
					? failure
					: new Error( String( failure ) )
			);
		} finally {
			setBusy( false );
		}
	};

	return (
		<form
			className="vqy-widget__form"
			onSubmit={ ( e ) => void submit( e ) }
		>
			<p className="vqy-widget__timer" role="timer">
				{ expired
					? __( 'Your reserved time has expired.', 'vaqtyar' )
					: sprintf(
							/* translators: %s: minutes and seconds left, e.g. 9:41. */
							__(
								'We are holding this time for you: %s',
								'vaqtyar'
							),
							formatDigits( `${ minutes }:${ seconds }`, digits )
						) }
			</p>
			<ul className="vqy-widget__price">
				{ hold.price.lines.map( ( line, index ) => (
					<li key={ `${ line.code }-${ index }` }>
						{ lineLabel( line.code ) }
						{ ': ' }
						{ formatAmount( line.amount, digits ) }
					</li>
				) ) }
				<li>
					<strong>
						{ sprintf(
							/* translators: %s: the total in rials. */
							__( 'Total: %s IRR', 'vaqtyar' ),
							formatAmount( hold.price.total, digits )
						) }
					</strong>
				</li>
			</ul>
			<label htmlFor={ `${ uid }-first` }>
				{ __( 'First name', 'vaqtyar' ) }
				<input
					id={ `${ uid }-first` }
					value={ firstName }
					required
					maxLength={ 100 }
					onInput={ ( e ) => setFirstName( e.currentTarget.value ) }
				/>
			</label>
			<label htmlFor={ `${ uid }-last` }>
				{ __( 'Last name', 'vaqtyar' ) }
				<input
					id={ `${ uid }-last` }
					value={ lastName }
					maxLength={ 100 }
					onInput={ ( e ) => setLastName( e.currentTarget.value ) }
				/>
			</label>
			<label htmlFor={ `${ uid }-phone` }>
				{ __( 'Mobile number', 'vaqtyar' ) }
				<input
					id={ `${ uid }-phone` }
					type="tel"
					dir="ltr"
					value={ phone }
					required
					maxLength={ 32 }
					onInput={ ( e ) => {
						setPhone( e.currentTarget.value );
						setSession( null );
					} }
				/>
			</label>
			{ otp.data?.required && (
				<PhoneCheck
					key={ phone }
					phone={ phone }
					nonce={ nonce }
					clientFor={ clientFor }
					digits={ digits }
					verified={ session !== null }
					onVerified={ setSession }
				/>
			) }
			<label htmlFor={ `${ uid }-email` }>
				{ __( 'Email (optional)', 'vaqtyar' ) }
				<input
					id={ `${ uid }-email` }
					type="email"
					dir="ltr"
					value={ email }
					maxLength={ 191 }
					onInput={ ( e ) => setEmail( e.currentTarget.value ) }
				/>
			</label>
			{ shown.map( ( field ) => (
				<AnswerInput
					key={ field.field_key }
					id={ `${ uid }-a-${ field.field_key }` }
					field={ field }
					value={ answers[ field.field_key ] }
					invalid={ wrong === field.field_key }
					onChange={ ( value ) =>
						setAnswers( { ...answers, [ field.field_key ]: value } )
					}
				/>
			) ) }
			<label htmlFor={ `${ uid }-note` }>
				{ __( 'Note (optional)', 'vaqtyar' ) }
				<textarea
					id={ `${ uid }-note` }
					value={ note }
					maxLength={ 2000 }
					onInput={ ( e ) => setNote( e.currentTarget.value ) }
				/>
			</label>
			{ mustPay && ! payable && (
				<p role="alert" className="vqy-widget__error">
					{ __(
						'This service has to be paid online, and online payment is not available now.',
						'vaqtyar'
					) }
				</p>
			) }
			{ paymentOptions.data?.approval === true && (
				<p className="vqy-widget__hint">
					{ __(
						'This booking is confirmed once the staff approve it.',
						'vaqtyar'
					) }
				</p>
			) }
			{ error && (
				<p role="alert" className="vqy-widget__error">
					{ error.message }
				</p>
			) }
			<div className="vqy-widget__actions">
				<button type="button" onClick={ onBack }>
					{ __( 'Back', 'vaqtyar' ) }
				</button>
				{ payable && (
					<button
						type="submit"
						disabled={ blocked }
						onClick={ () => {
							payOnline.current = true;
						} }
					>
						{ isDeposit && due
							? sprintf(
									/* translators: %s: the deposit, an amount in rials. */
									__(
										'Pay the deposit (%s IRR) and book',
										'vaqtyar'
									),
									formatAmount( due, digits )
								)
							: __( 'Pay online and book', 'vaqtyar' ) }
					</button>
				) }
				{ ! mustPay && (
					<button
						type="submit"
						disabled={ blocked }
						onClick={ () => {
							payOnline.current = false;
						} }
					>
						{ payable
							? __( 'Book and pay at the place', 'vaqtyar' )
							: __( 'Confirm booking', 'vaqtyar' ) }
					</button>
				) }
			</div>
		</form>
	);
}

function AnswerInput( {
	id,
	field,
	value,
	invalid,
	onChange,
}: {
	id: string;
	field: PublicField;
	value: string | boolean | undefined;
	invalid: boolean;
	onChange: ( value: string | boolean ) => void;
} ) {
	const common = {
		id,
		required: field.required,
		'aria-invalid': invalid || undefined,
	};

	return (
		<label htmlFor={ id }>
			{ field.label }
			{ field.type === 'textarea' && (
				<textarea
					{ ...common }
					value={ typeof value === 'string' ? value : '' }
					onInput={ ( e ) => onChange( e.currentTarget.value ) }
				/>
			) }
			{ field.type === 'select' && (
				<select
					{ ...common }
					value={ typeof value === 'string' ? value : '' }
					onChange={ ( e ) => onChange( e.currentTarget.value ) }
				>
					<option value="" />
					{ field.options.map( ( option ) => (
						<option key={ option } value={ option }>
							{ option }
						</option>
					) ) }
				</select>
			) }
			{ field.type === 'checkbox' && (
				<input
					{ ...common }
					type="checkbox"
					checked={ value === true }
					onChange={ ( e ) => onChange( e.currentTarget.checked ) }
				/>
			) }
			{ ( field.type === 'text' || field.type === 'number' ) && (
				<input
					{ ...common }
					type={ field.type === 'number' ? 'number' : 'text' }
					value={ typeof value === 'string' ? value : '' }
					onInput={ ( e ) => onChange( e.currentTarget.value ) }
				/>
			) }
		</label>
	);
}
