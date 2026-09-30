import { setLocaleData } from '../../packages/widget/node_modules/@wordpress/i18n';

import widget from '../../languages/vaqtyar-fa_IR-vaqtyar-widget.json';

/** The widget bundles its own copy of @wordpress/i18n, so it is given its own strings. */
if ( new URLSearchParams( window.location.search ).get( 'lang' ) === 'fa' ) {
	setLocaleData( widget.locale_data.messages, 'vaqtyar' );
}
