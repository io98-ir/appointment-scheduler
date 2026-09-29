/**
 * For the tests of the settings screen: what the brand, SMS and payment
 * sections ask for, answered as an untouched site would (T6.1). A test
 * server calls this first and goes on when it returns null.
 *
 * @param path   The route below the namespace.
 * @param method The HTTP method.
 */
export function settingsAnswer(
	path: string,
	method: string
): Response | null {
	const json = ( body: unknown ) => new Response( JSON.stringify( body ) );
	if ( method !== 'GET' ) {
		return null;
	}
	if ( path === '/brand' ) {
		return json( { name: '', logo_url: '', color: '' } );
	}
	if ( path === '/onboarding' ) {
		return json( { done: true } );
	}
	if ( path === '/sms' ) {
		return json( {
			order: [],
			senders: {},
			otp_patterns: {},
			providers: [
				{
					id: 'kavenegar',
					configured: false,
					secrets: [
						{ name: 'sms_kavenegar_key', set: false, fixed: false },
					],
				},
			],
		} );
	}
	if ( path === '/payments/settings' ) {
		return json( {
			gateways: [],
			woocommerce: { available: false, enabled: false },
		} );
	}

	return null;
}
