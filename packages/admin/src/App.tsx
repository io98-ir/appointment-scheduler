import { QueryClientProvider, type QueryClient } from '@tanstack/react-query';
import type { ApiClient } from '@vaqtyar/shared';
import { SelectControl, SnackbarList } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { Component, createContext, useContext } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import { errorMessage } from './query';
import { useRoute } from './router';
import { useTheme, type ThemeChoice } from './theme';
import type { ErrorInfo, ReactNode } from 'react';

interface Section {
	path: string;
	title: () => string;
	Page: () => ReactNode;
}

/**
 * The admin screens, in menu order. A section owns its path and every path
 * below it ("/services/7"). The screens of T3.2–T3.6 replace the
 * placeholders as they are built.
 */
const SECTIONS: Section[] = [
	{ path: '/', title: () => __( 'Dashboard', 'vaqtyar' ), Page: Placeholder },
	{
		path: '/calendar',
		title: () => __( 'Calendar', 'vaqtyar' ),
		Page: Placeholder,
	},
	{
		path: '/appointments',
		title: () => __( 'Appointments', 'vaqtyar' ),
		Page: Placeholder,
	},
	{
		path: '/customers',
		title: () => __( 'Customers', 'vaqtyar' ),
		Page: Placeholder,
	},
	{
		path: '/services',
		title: () => __( 'Services', 'vaqtyar' ),
		Page: Placeholder,
	},
	{
		path: '/staff',
		title: () => __( 'Staff', 'vaqtyar' ),
		Page: Placeholder,
	},
	{
		path: '/settings',
		title: () => __( 'Settings', 'vaqtyar' ),
		Page: Placeholder,
	},
];

/**
 * The section a route belongs to, or undefined for an unknown route.
 *
 * @param route From useRoute().
 */
export function sectionOf( route: string ): Section | undefined {
	return SECTIONS.find( ( { path } ) =>
		path === '/'
			? route === '/'
			: route === path || route.startsWith( path + '/' )
	);
}

const ApiContext = createContext< ApiClient | null >( null );

/**
 * The REST client, for the screens' queries and mutations.
 */
export function useApi(): ApiClient {
	const api = useContext( ApiContext );
	if ( api === null ) {
		throw new Error( 'useApi() outside of <App>.' );
	}

	return api;
}

export function App( {
	api,
	queryClient,
}: {
	api: ApiClient;
	queryClient: QueryClient;
} ) {
	const route = useRoute();
	const section = sectionOf( route );
	const [ theme, setTheme ] = useTheme();

	return (
		<ApiContext.Provider value={ api }>
			<QueryClientProvider client={ queryClient }>
				<div className="vqy-admin" data-theme={ theme }>
					<header className="vqy-admin__header">
						<nav
							className="vqy-admin__nav"
							aria-label={ __( 'Sections', 'vaqtyar' ) }
						>
							{ SECTIONS.map( ( item ) => (
								<a
									key={ item.path }
									href={ '#' + item.path }
									className="vqy-admin__link"
									aria-current={
										item === section ? 'page' : undefined
									}
								>
									{ item.title() }
								</a>
							) ) }
						</nav>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Theme', 'vaqtyar' ) }
							value={ theme }
							options={ [
								{
									value: 'auto',
									label: __( 'System', 'vaqtyar' ),
								},
								{
									value: 'light',
									label: __( 'Light', 'vaqtyar' ),
								},
								{
									value: 'dark',
									label: __( 'Dark', 'vaqtyar' ),
								},
							] }
							onChange={ ( value ) =>
								setTheme( value as ThemeChoice )
							}
						/>
					</header>
					<main className="vqy-admin__main">
						{ /* A new route clears a crashed screen. */ }
						<ErrorBoundary key={ route }>
							{ section ? (
								<>
									<h1 className="vqy-admin__title">
										{ section.title() }
									</h1>
									<section.Page />
								</>
							) : (
								<NotFound />
							) }
						</ErrorBoundary>
					</main>
					<Snackbars />
				</div>
			</QueryClientProvider>
		</ApiContext.Provider>
	);
}

function Placeholder() {
	return <p>{ __( 'This screen is not built yet.', 'vaqtyar' ) }</p>;
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

/**
 * Messages from anywhere in the app (the notices store), e.g. a failed save.
 */
function Snackbars() {
	const notices = useSelect(
		( select ) =>
			select( noticesStore )
				.getNotices()
				.filter( ( notice ) => notice.type === 'snackbar' ),
		[]
	);
	const { removeNotice } = useDispatch( noticesStore );

	return (
		<SnackbarList
			className="vqy-admin__snackbars"
			notices={ notices }
			onRemove={ removeNotice }
		/>
	);
}

/**
 * A screen that throws while rendering shows a message instead of taking
 * the whole app (and its menu) down with it.
 */
class ErrorBoundary extends Component<
	{ children: ReactNode },
	{ error: unknown }
> {
	override state = { error: null as unknown };

	static getDerivedStateFromError( error: unknown ) {
		return { error };
	}

	override componentDidCatch( error: unknown, info: ErrorInfo ) {
		// eslint-disable-next-line no-console -- the stack for a bug report.
		console.error( error, info.componentStack );
	}

	override render() {
		if ( this.state.error === null ) {
			return this.props.children;
		}

		return (
			<div className="notice notice-error inline" role="alert">
				<p>{ errorMessage( this.state.error ) }</p>
			</div>
		);
	}
}
