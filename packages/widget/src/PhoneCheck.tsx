import { formatDigits, type ApiClient, type Digits } from '@vaqtyar/shared';
import { __, sprintf } from '@wordpress/i18n';
import { useId, useState } from 'preact/hooks';

interface Captcha {
	a: number;
	b: number;
	token: string;
}

type Stage = 'idle' | 'captcha' | 'code';

/**
 * Proves the phone number with a one-time code (T4.3): a sum to solve, then
 * the code the site sent, then a session token the booking carries. The
 * parent remounts it (a `key` of the phone) when the number changes, since
 * a code is for one number only.
 *
 * @param props
 * @param props.phone      What the customer typed.
 * @param props.nonce      The fresh REST nonce of this booking.
 * @param props.clientFor  A client that sends the given nonce.
 * @param props.digits
 * @param props.verified   Whether a session token is held.
 * @param props.onVerified Called with the token once the code is right.
 */
export function PhoneCheck( {
	phone,
	nonce,
	clientFor,
	digits,
	verified,
	onVerified,
}: {
	phone: string;
	nonce: string;
	clientFor: ( nonce?: string ) => ApiClient;
	digits: Digits;
	verified: boolean;
	onVerified: ( token: string ) => void;
} ) {
	const uid = useId();
	const [ stage, setStage ] = useState< Stage >( 'idle' );
	const [ captcha, setCaptcha ] = useState< Captcha | null >( null );
	const [ answer, setAnswer ] = useState( '' );
	const [ code, setCode ] = useState( '' );
	const [ error, setError ] = useState< string | null >( null );
	const [ busy, setBusy ] = useState( false );

	const run = async ( work: () => Promise< void > ) => {
		setBusy( true );
		setError( null );
		try {
			await work();
		} catch ( failure ) {
			setError(
				failure instanceof Error ? failure.message : String( failure )
			);
		} finally {
			setBusy( false );
		}
	};
	const askCaptcha = () =>
		run( async () => {
			setCaptcha( await clientFor().get< Captcha >( '/captcha' ) );
			setAnswer( '' );
			setStage( 'captcha' );
		} );
	const sendCode = () =>
		run( async () => {
			if ( captcha === null ) {
				return;
			}
			await clientFor( nonce ).post( '/otp/request', {
				phone,
				captcha_token: captcha.token,
				captcha_answer: answer,
			} );
			setCode( '' );
			setStage( 'code' );
		} );
	const verify = () =>
		run( async () => {
			const session = await clientFor( nonce ).post< { token: string } >(
				'/otp/verify',
				{ phone, code }
			);
			onVerified( session.token );
		} );

	if ( verified ) {
		return (
			<p className="vqy-widget__verified" role="status">
				{ __( 'Your phone number is verified.', 'vaqtyar' ) }
			</p>
		);
	}

	return (
		<div className="vqy-widget__otp">
			{ stage === 'idle' && (
				<button
					type="button"
					disabled={ busy || phone.trim() === '' }
					onClick={ () => void askCaptcha() }
				>
					{ __( 'Send a verification code', 'vaqtyar' ) }
				</button>
			) }
			{ stage === 'captcha' && captcha && (
				<>
					<label htmlFor={ `${ uid }-captcha` }>
						{ sprintf(
							/* translators: 1: a digit, 2: a digit. */
							__( 'What is %1$s + %2$s?', 'vaqtyar' ),
							formatDigits( String( captcha.a ), digits ),
							formatDigits( String( captcha.b ), digits )
						) }
						<input
							id={ `${ uid }-captcha` }
							inputMode="numeric"
							value={ answer }
							maxLength={ 3 }
							onInput={ ( e ) =>
								setAnswer( e.currentTarget.value )
							}
						/>
					</label>
					<button
						type="button"
						disabled={ busy || answer.trim() === '' }
						onClick={ () => void sendCode() }
					>
						{ __( 'Send code', 'vaqtyar' ) }
					</button>
				</>
			) }
			{ stage === 'code' && (
				<>
					<label htmlFor={ `${ uid }-code` }>
						{ __( 'Code we sent you', 'vaqtyar' ) }
						<input
							id={ `${ uid }-code` }
							inputMode="numeric"
							autoComplete="one-time-code"
							dir="ltr"
							value={ code }
							maxLength={ 12 }
							onInput={ ( e ) =>
								setCode( e.currentTarget.value )
							}
						/>
					</label>
					<button
						type="button"
						disabled={ busy || code.trim() === '' }
						onClick={ () => void verify() }
					>
						{ __( 'Verify', 'vaqtyar' ) }
					</button>
					<button
						type="button"
						disabled={ busy }
						onClick={ () => void askCaptcha() }
					>
						{ __( 'Send another code', 'vaqtyar' ) }
					</button>
				</>
			) }
			{ error && (
				<p role="alert" className="vqy-widget__error">
					{ error }
				</p>
			) }
		</div>
	);
}
