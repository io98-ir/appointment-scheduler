import type { Brand, DisplaySettings } from '@vaqtyar/shared';

/**
 * What the admin page (AdminPage.php) hands the app, as JSON in the mount
 * element's data-config: no global variable, so a rename needs no JS edit.
 */
export interface AdminConfig {
	/** rest_url() of the plugin's namespace, with a trailing slash. */
	restUrl: string;
	/** A wp_rest nonce for the current user. */
	nonce: string;
	/** The owner's look; an empty name means the product name. */
	brand: Brand;
	productName: string;
	/** How dates, digits and the screens are shown (Settings). */
	display: DisplaySettings;
	/** "rtl" or "ltr": the direction of the plugin's language. */
	dir: 'rtl' | 'ltr';
	/** The maker, credited in the footer. */
	author: Author;
}

export interface Author {
	name: string;
	url: string;
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
	const {
		restUrl,
		nonce,
		brand,
		product_name: productName,
		display,
		author,
	} = ( value ?? {} ) as Record< string, unknown >;
	if ( typeof restUrl !== 'string' || typeof nonce !== 'string' ) {
		throw new Error( 'The admin page did not render a valid data-config.' );
	}

	return {
		restUrl,
		nonce,
		brand: readBrand( brand ),
		productName: typeof productName === 'string' ? productName : '',
		...readDisplay( display ),
		author: readAuthor( author ),
	};
}

function readDisplay( value: unknown ): {
	display: DisplaySettings;
	dir: 'rtl' | 'ltr';
} {
	const { calendar, digits, language, dir } = ( value ?? {} ) as Record<
		string,
		unknown
	>;

	return {
		display: {
			calendar: calendar === 'gregorian' ? 'gregorian' : 'jalali',
			digits: digits === 'persian' ? 'persian' : 'latin',
			language:
				language === 'fa' || language === 'en' ? language : 'auto',
		},
		dir: dir === 'rtl' ? 'rtl' : 'ltr',
	};
}

function readAuthor( value: unknown ): Author {
	const { name, url } = ( value ?? {} ) as Record< string, unknown >;

	return {
		name: typeof name === 'string' ? name : '',
		url: typeof url === 'string' ? url : '',
	};
}

function readBrand( value: unknown ): Brand {
	const {
		name,
		logo_url: logo,
		color,
	} = ( value ?? {} ) as Record< string, unknown >;

	return {
		name: typeof name === 'string' ? name : '',
		logo_url: typeof logo === 'string' ? logo : '',
		color: typeof color === 'string' ? color : '',
	};
}
