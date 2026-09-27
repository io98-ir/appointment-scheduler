/**
 * What the admin page (AdminPage.php) hands the app, as JSON in the mount
 * element's data-config: no global variable, so a rename needs no JS edit.
 */
export interface AdminConfig {
	/** rest_url() of the plugin's namespace, with a trailing slash. */
	restUrl: string;
	/** A wp_rest nonce for the current user. */
	nonce: string;
}

/**
 * @param element The mount element.
 * @throws Error when the attribute is missing or not a config.
 */
export function readConfig( element: HTMLElement ): AdminConfig {
	let value: unknown;
	try {
		value = JSON.parse( element.dataset.config ?? '' );
	} catch {
		value = null;
	}
	const { restUrl, nonce } = ( value ?? {} ) as Record< string, unknown >;
	if ( typeof restUrl !== 'string' || typeof nonce !== 'string' ) {
		throw new Error( 'The admin page did not render a valid data-config.' );
	}

	return { restUrl, nonce };
}
