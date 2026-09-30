import { ApiClient, SLUG } from '@vaqtyar/shared';
import { dispatch } from '@wordpress/data';
import { createRoot } from '@wordpress/element';
import { store as noticesStore } from '@wordpress/notices';

import { App } from './App';
import { readConfig } from './config';
import { createQueryClient } from './query';
import './tokens.css';
import './admin.css';

/**
 * Mounts the app into the element the admin page renders (AdminPage.php).
 */
const element = document.getElementById( `${ SLUG }-admin` );
if ( element ) {
	const { restUrl, nonce, brand, productName, display, dir, author } =
		readConfig( element );
	const queryClient = createQueryClient( ( message ) =>
		dispatch( noticesStore ).createErrorNotice( message, {
			type: 'snackbar',
		} )
	);

	createRoot( element ).render(
		<App
			api={ new ApiClient( { baseUrl: restUrl, nonce } ) }
			queryClient={ queryClient }
			brand={ brand }
			productName={ productName }
			display={ display }
			dir={ dir }
			author={ author }
		/>
	);
}
