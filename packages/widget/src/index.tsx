import { SLUG } from '@vaqtyar/shared';

import { mount, readConfig } from './mount';
import './widget.css';

/**
 * Mounts a widget on every element the shortcode or block renders:
 * <div data-{slug}-widget='{"service":7}'></div>. No global is defined
 * (principles §5).
 */
const attribute = `data-${ SLUG }-widget`;

function mountAll(): void {
	for ( const element of document.querySelectorAll( `[${ attribute }]` ) ) {
		mount( element, readConfig( element, attribute ) );
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mountAll );
} else {
	mountAll();
}
