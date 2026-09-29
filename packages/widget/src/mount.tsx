import type { ApiClient } from '@vaqtyar/shared';
import { render } from 'preact';

import { Panel } from './Panel';
import { Widget, type WidgetConfig } from './Widget';

/**
 * Renders a widget into an element. Several widgets may share a page; each
 * mount is independent. Returns the function that removes it again.
 *
 * @param element
 * @param config
 * @param api     For tests: the client to use instead of one on config.restUrl.
 */
export function mount(
	element: Element,
	config: WidgetConfig,
	api?: ApiClient
): () => void {
	render( <Widget config={ config } api={ api } />, element );

	return () => render( null, element );
}

/**
 * Renders the customer panel into an element (T4.4), like mount().
 *
 * @param element
 * @param config
 * @param api     For tests.
 */
export function mountPanel(
	element: Element,
	config: WidgetConfig,
	api?: ApiClient
): () => void {
	render( <Panel config={ config } api={ api } />, element );

	return () => render( null, element );
}

/**
 * The config the PHP side put on the element as JSON; an empty config when
 * it is missing or broken, so one bad attribute cannot break the page.
 *
 * @param element
 * @param attribute
 */
export function readConfig(
	element: Element,
	attribute: string
): WidgetConfig {
	try {
		const value: unknown = JSON.parse(
			element.getAttribute( attribute ) ?? '{}'
		);

		return isPlainObject( value ) ? value : {};
	} catch {
		return {};
	}
}

function isPlainObject( value: unknown ): value is WidgetConfig {
	return (
		typeof value === 'object' && value !== null && ! Array.isArray( value )
	);
}
