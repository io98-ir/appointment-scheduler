import { createRoot } from '@wordpress/element';
import { SLUG } from '@vaqtyar/shared';

import { App } from './App';
import './admin.css';

/**
 * Mounts the app into the element the admin page renders (AdminPage.php).
 */
const root = document.getElementById( `${ SLUG }-admin` );
if ( root ) {
	createRoot( root ).render( <App /> );
}
