import { setLocaleData } from '../../packages/admin/node_modules/@wordpress/i18n';

import admin from '../../languages/vaqtyar-fa_IR-vaqtyar-admin.json';

/**
 * ?lang=fa loads the Persian strings, as wp_set_script_translations() does on
 * a real page; anything else stays English.
 */
if ( new URLSearchParams( window.location.search ).get( 'lang' ) === 'fa' ) {
	setLocaleData( admin.locale_data.messages, 'vaqtyar' );
}
