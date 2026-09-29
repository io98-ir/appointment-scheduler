import { useQuery } from '@tanstack/react-query';
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { useApi } from '../api';

/**
 * The dashboard's nudge to the setup wizard until the owner finishes or
 * skips it. A failed check shows nothing: the wizard is never in the way.
 */
export function SetupPrompt() {
	const api = useApi();
	const onboarding = useQuery( {
		queryKey: [ '/onboarding' ],
		queryFn: () => api.get< { done: boolean } >( '/onboarding' ),
	} );

	if ( ! onboarding.data || onboarding.data.done ) {
		return null;
	}

	return (
		<Notice status="info" isDismissible={ false }>
			{ __( 'Your booking site is not set up yet.', 'vaqtyar' ) }{ ' ' }
			<a href="#/setup">{ __( 'Open the setup wizard', 'vaqtyar' ) }</a>
		</Notice>
	);
}
