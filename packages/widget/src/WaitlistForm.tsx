import { type ApiClient, type Digits } from '@vaqtyar/shared';
import { __ } from '@wordpress/i18n';
import { useId, useState } from 'preact/hooks';

import { PhoneCheck } from './PhoneCheck';
import { useFetch } from './useFetch';

/** What the form asks the waiting list for: the same inputs as an availability view. */
export interface WaitlistQuery {
	variant: number;
	location: number;
	staff: number | null;
	date: string;
}

/**
 * Shown under a full day: the customer leaves a phone number to be told when a time opens on it. It is
 * a notice only, nothing is held for them, which the text says. The phone is verified with a code when
 * the site requires it, the same as a booking.
 *
 * @param props
 * @param props.query     The service, location, staff and day waited for.
 * @param props.clientFor A client that sends the given nonce.
 * @param props.digits
 */
export function WaitlistForm( {
	query,
	clientFor,
	digits,
}: {
	query: WaitlistQuery;
	clientFor: ( nonce?: string ) => ApiClient;
	digits: Digits;
} ) {
	const uid = useId();
	const otp = useFetch< { required: boolean } >( 'otp-config', () =>
		clientFor().get< { required: boolean } >( '/otp/config' )
	);
	const nonce = useFetch< { nonce: string } >( 'waitlist-nonce', () =>
		clientFor().get< { nonce: string } >( '/nonce' )
	);
	const [ name, setName ] = useState( '' );
	const [ phone, setPhone ] = useState( '' );
	const [ session, setSession ] = useState< string | null >( null );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );
	const [ joined, setJoined ] = useState( false );

	if ( joined ) {
		return (
			<p role="status" className="vqy-widget__done">
				{ __(
					'Done. We will message you if a time opens on this day.',
					'vaqtyar'
				) }
			</p>
		);
	}
	if ( otp.loading || nonce.loading ) {
		return <p>{ __( 'Loading…', 'vaqtyar' ) }</p>;
	}
	const token = nonce.data?.nonce;
	const verifyNeeded = otp.data?.required === true && session === null;

	const submit = async ( event: Event ) => {
		event.preventDefault();
		if ( token === undefined ) {
			return;
		}
		setBusy( true );
		setError( null );
		try {
			await clientFor( token ).post( '/waitlist', {
				variant: query.variant,
				location: query.location,
				staff: query.staff,
				date: query.date,
				phone,
				first_name: name,
				session_token: session,
				page_url: window.location.href,
			} );
			setJoined( true );
		} catch ( failure ) {
			setError(
				failure instanceof Error ? failure.message : String( failure )
			);
		} finally {
			setBusy( false );
		}
	};

	return (
		<form className="vqy-widget__waitlist" onSubmit={ submit }>
			<p>
				{ __(
					'This day is full. Leave your number and we will message you if a time opens. Nothing is reserved for you: whoever books it first has it.',
					'vaqtyar'
				) }
			</p>
			<label htmlFor={ `${ uid }-name` }>
				{ __( 'Name', 'vaqtyar' ) }
				<input
					id={ `${ uid }-name` }
					value={ name }
					maxLength={ 100 }
					onInput={ ( e ) => setName( e.currentTarget.value ) }
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
			{ otp.data?.required === true && token !== undefined && (
				<PhoneCheck
					key={ phone }
					phone={ phone }
					nonce={ token }
					clientFor={ clientFor }
					digits={ digits }
					verified={ session !== null }
					onVerified={ setSession }
				/>
			) }
			{ ( error ?? nonce.error ) && (
				<p role="alert" className="vqy-widget__error">
					{ error ?? nonce.error }
				</p>
			) }
			<button
				type="submit"
				disabled={
					busy ||
					token === undefined ||
					verifyNeeded ||
					phone.trim() === ''
				}
			>
				{ __( 'Tell me when a time opens', 'vaqtyar' ) }
			</button>
		</form>
	);
}
