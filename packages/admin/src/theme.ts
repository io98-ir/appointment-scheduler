import { useState } from '@wordpress/element';

/**
 * Dark mode (principles §5): the system's preference by default, or a choice
 * the user makes, kept in this browser only. The CSS reads data-theme.
 */
export type ThemeChoice = 'auto' | 'light' | 'dark';

const KEY = 'vqy-admin-theme';

/**
 * @param store localStorage, which may be missing or throw (private mode).
 */
export function readTheme( store: Storage | undefined ): ThemeChoice {
	try {
		const value = store?.getItem( KEY );

		return value === 'light' || value === 'dark' ? value : 'auto';
	} catch {
		return 'auto';
	}
}

export function useTheme(): [ ThemeChoice, ( choice: ThemeChoice ) => void ] {
	const [ theme, setTheme ] = useState( () => readTheme( storage() ) );

	return [
		theme,
		( choice ) => {
			setTheme( choice );
			try {
				storage()?.setItem( KEY, choice );
			} catch {
				// Not remembered; the choice still applies to this page.
			}
		},
	];
}

function storage(): Storage | undefined {
	try {
		return window.localStorage;
	} catch {
		return undefined;
	}
}
