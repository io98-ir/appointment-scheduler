import { __ } from '@wordpress/i18n';

export function NotFound() {
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
