import { useQuery } from '@tanstack/react-query';
import {
	formatDate,
	formatDigits,
	type ApiClient,
	type DisplaySettings,
} from '@vaqtyar/shared';
import { createContext, useContext } from '@wordpress/element';
import type { ReactNode } from 'react';

/**
 * Without an <App> (a test, a story) dates read Jalali with Latin digits,
 * which is what the labels of the tests were written against.
 */
export const DEFAULT_DISPLAY: DisplaySettings = {
	calendar: 'jalali',
	digits: 'latin',
	language: 'auto',
};

export const DisplayContext =
	createContext< DisplaySettings >( DEFAULT_DISPLAY );

/**
 * How the owner chose to see dates and numbers (Settings, "Language and
 * calendar"). A save on that screen updates it without a reload.
 *
 * @param props
 * @param props.api      The REST client.
 * @param props.initial  What the page was rendered with.
 * @param props.children The app.
 */
export function DisplayProvider( {
	api,
	initial,
	children,
}: {
	api: ApiClient;
	initial: DisplaySettings;
	children: ReactNode;
} ) {
	const { data } = useQuery( {
		queryKey: [ '/general' ],
		queryFn: () => api.get< DisplaySettings >( '/general' ),
		initialData: initial,
		staleTime: Infinity,
	} );

	return (
		<DisplayContext.Provider value={ data }>
			{ children }
		</DisplayContext.Provider>
	);
}

export function useDisplay(): DisplaySettings {
	return useContext( DisplayContext );
}

/**
 * A Gregorian local date ("2026-09-25") in the chosen calendar and digits.
 */
export function useDate(): ( localDate: string ) => string {
	const { calendar, digits } = useDisplay();

	return ( localDate ) => formatDate( localDate, calendar, digits );
}

/**
 * A clock time or any number in the chosen digits.
 */
export function useDigits(): ( text: string ) => string {
	const { digits } = useDisplay();

	return ( text ) => formatDigits( text, digits );
}

/**
 * "1405/07/12 10:30": a date and the time the API gave, in the location's
 * own time, written in the chosen calendar and digits.
 *
 * @param iso     ISO 8601 with the location's offset.
 * @param display How to show it.
 */
export function whenIn( iso: string, display: DisplaySettings ): string {
	return `${ formatDate(
		iso.slice( 0, 10 ),
		display.calendar,
		display.digits
	) } ${ formatDigits( iso.slice( 11, 16 ), display.digits ) }`;
}

/**
 * Like whenIn() for a component.
 */
export function useWhen(): ( iso: string ) => string {
	const display = useDisplay();

	return ( iso ) => whenIn( iso, display );
}
