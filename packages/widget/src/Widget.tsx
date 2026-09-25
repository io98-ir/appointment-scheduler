import { __ } from '@wordpress/i18n';

/**
 * What the shortcode or block passes in (T4.5), e.g. a preselected service.
 * Nothing is read from it yet.
 */
export type WidgetConfig = Record< string, unknown >;

/**
 * The booking widget. Until the booking steps exist (T4.1) it only shows
 * its frame.
 *
 * @param props
 * @param props.config
 */
// eslint-disable-next-line @typescript-eslint/no-unused-vars -- read from T4.1 on.
export function Widget( { config }: { config: WidgetConfig } ) {
	return (
		<section
			className="vqy-widget"
			aria-label={ __( 'Book an appointment', 'vaqtyar' ) }
		>
			<p className="vqy-widget__title">
				{ __( 'Book an appointment', 'vaqtyar' ) }
			</p>
		</section>
	);
}
