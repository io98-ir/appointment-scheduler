import { SLUG } from '@vaqtyar/shared';

import { mount, mountPanel, readConfig } from './mount';
import './widget.css';

/**
 * Mounts a widget on every element the shortcode or block renders:
 * <div data-{slug}-widget='{"service":7}'></div>, and the customer panel on
 * <div data-{slug}-panel></div>. No global is defined (principles §5).
 */
const attribute = `data-${ SLUG }-widget`;
const panelAttribute = `data-${ SLUG }-panel`;

function mountAll(): void {
	for ( const element of document.querySelectorAll( `[${ attribute }]` ) ) {
		mount( element, readConfig( element, attribute ) );
	}
	for ( const element of document.querySelectorAll(
		`[${ panelAttribute }]`
	) ) {
		mountPanel( element, readConfig( element, panelAttribute ) );
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mountAll );
} else {
	mountAll();
}
