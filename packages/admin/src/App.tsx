import { __ } from '@wordpress/i18n';

import { useRoute } from './router';

/**
 * The admin screens by route. Each module's screens are added here as they
 * are built (M3); until then the shell has one empty dashboard.
 */
const PAGES: Record< string, typeof Dashboard > = {
	'/': Dashboard,
};

export function App() {
	const route = useRoute();
	const Page = PAGES[ route ] ?? NotFound;

	return (
		<div className="vqy-admin">
			<Page />
		</div>
	);
}

function Dashboard() {
	return (
		<h1 className="vqy-admin__title">{ __( 'Dashboard', 'vaqtyar' ) }</h1>
	);
}

function NotFound() {
	return (
		<>
			<h1 className="vqy-admin__title">
				{ __( 'Page not found', 'vaqtyar' ) }
			</h1>
			<p>
				<a href="#/">{ __( 'Back to the dashboard', 'vaqtyar' ) }</a>
			</p>
		</>
	);
}
