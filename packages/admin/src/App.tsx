import {
	QueryClientProvider,
	useQuery,
	type QueryClient,
} from '@tanstack/react-query';
import type { ApiClient, Brand, DisplaySettings } from '@vaqtyar/shared';
import { SnackbarList } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { Component, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import { ApiContext } from './api';
import type { Author } from './config';
import { DEFAULT_DISPLAY, DisplayProvider } from './display';
import { paletteVars } from './palette';
import { AppointmentsPage } from './appointments/AppointmentsPage';
import { CalendarPage } from './calendar/CalendarPage';
import {
	CategoriesPage,
	LocationsPage,
	ResourcesPage,
	StaffPage,
} from './catalog/screens';
import { ServicesPage } from './catalog/ServicesPage';
import { CustomersPage } from './customers/CustomersPage';
import { HolidaysPage } from './holidays/HolidaysPage';
import { NotificationsPage } from './notifications/NotificationsPage';
import { NotFound } from './NotFound';
import { SetupWizard } from './setup/SetupWizard';
import { SettingsPage } from './policies/SettingsPage';
import { StatusPage } from './status/StatusPage';
import { WaitlistPage } from './waitlist/WaitlistPage';
import { DashboardPage } from './reports/DashboardPage';
import { ReportsPage } from './reports/ReportsPage';
import { errorMessage } from './query';
import { useRoute } from './router';
import { useTheme, type ThemeChoice } from './theme';
import type { CSSProperties, ErrorInfo, ReactNode } from 'react';

interface Section {
	path: string;
	title: () => string;
	Page: () => ReactNode;
	/** False for a screen reached from another one, e.g. categories. */
	menu?: false;
}

/**
 * The admin screens, in menu order. A section owns its path and every path
 * below it ("/services/7").
 */
const SECTIONS: Section[] = [
	{
		path: '/',
		title: () => __( 'Dashboard', 'vaqtyar' ),
		Page: DashboardPage,
	},
	{
		path: '/calendar',
		title: () => __( 'Calendar', 'vaqtyar' ),
		Page: CalendarPage,
	},
	{
		path: '/appointments',
		title: () => __( 'Appointments', 'vaqtyar' ),
		Page: AppointmentsPage,
	},
	{
		path: '/waitlist',
		title: () => __( 'Waiting list', 'vaqtyar' ),
		Page: WaitlistPage,
	},
	{
		path: '/customers',
		title: () => __( 'Customers', 'vaqtyar' ),
		Page: CustomersPage,
	},
	{
		path: '/services',
		title: () => __( 'Services', 'vaqtyar' ),
		Page: ServicesPage,
	},
	{
		path: '/service-categories',
		title: () => __( 'Service categories', 'vaqtyar' ),
		Page: CategoriesPage,
		menu: false,
	},
	{
		path: '/staff',
		title: () => __( 'Staff', 'vaqtyar' ),
		Page: StaffPage,
	},
	{
		path: '/resources',
		title: () => __( 'Resources', 'vaqtyar' ),
		Page: ResourcesPage,
	},
	{
		path: '/locations',
		title: () => __( 'Locations', 'vaqtyar' ),
		Page: LocationsPage,
	},
	{
		path: '/reports',
		title: () => __( 'Reports', 'vaqtyar' ),
		Page: ReportsPage,
	},
	{
		path: '/holidays',
		title: () => __( 'Holidays', 'vaqtyar' ),
		Page: HolidaysPage,
	},
	{
		path: '/setup',
		title: () => __( 'Setup', 'vaqtyar' ),
		Page: SetupWizard,
		menu: false,
	},
	{
		path: '/notifications',
		title: () => __( 'Notifications', 'vaqtyar' ),
		Page: NotificationsPage,
	},
	{
		path: '/settings',
		title: () => __( 'Settings', 'vaqtyar' ),
		Page: SettingsPage,
	},
	{
		path: '/status',
		title: () => __( 'System status', 'vaqtyar' ),
		Page: StatusPage,
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
			: route === path ||
				route.startsWith( path + '/' ) ||
				route.startsWith( path + '?' )
	);
}

const NO_BRAND: Brand = { name: '', logo_url: '', color: '' };

/**
 * The owner's name and logo at the start of the header (white-label, T6.1).
 *
 * @param props
 * @param props.brand       The owner's brand.
 * @param props.productName What an empty brand name means.
 */
function BrandMark( {
	brand,
	productName,
}: {
	brand: Brand;
	productName: string;
} ) {
	return (
		<span className="vqy-admin__brand">
			{ brand.logo_url && (
				<img
					className="vqy-admin__logo"
					src={ brand.logo_url }
					alt=""
				/>
			) }
			{ brand.name || productName }
		</span>
	);
}

/**
 * The owner's accent on the page's body as well: WordPress's modals and
 * popovers render outside the app's own element, and read the same colour.
 *
 * @param vars From paletteVars().
 */
function usePageAccent( vars: Record< string, string > ) {
	const key = JSON.stringify( vars );
	useEffect( () => {
		const names = Object.keys( vars );
		for ( const name of names ) {
			document.body.style.setProperty( name, vars[ name ] ?? '' );
		}

		return () => {
			for ( const name of names ) {
				document.body.style.removeProperty( name );
			}
		};
		// The serialised vars stand for the object, which is new on each render.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ key ] );
}

function ThemeSwitch( {
	theme,
	onChange,
}: {
	theme: ThemeChoice;
	onChange: ( choice: ThemeChoice ) => void;
} ) {
	const options: Array< [ ThemeChoice, string ] > = [
		[ 'auto', __( 'System', 'vaqtyar' ) ],
		[ 'light', __( 'Light', 'vaqtyar' ) ],
		[ 'dark', __( 'Dark', 'vaqtyar' ) ],
	];

	return (
		<div
			className="vqy-admin__theme"
			role="group"
			aria-label={ __( 'Theme', 'vaqtyar' ) }
		>
			{ options.map( ( [ value, label ] ) => (
				<button
					key={ value }
					type="button"
					aria-pressed={ theme === value }
					onClick={ () => onChange( value ) }
				>
					{ label }
				</button>
			) ) }
		</div>
	);
}

export function App( {
	api,
	queryClient,
	brand = NO_BRAND,
	productName = '',
	display = DEFAULT_DISPLAY,
	dir = 'ltr',
	author = { name: '', url: '' },
}: {
	api: ApiClient;
	queryClient: QueryClient;
	brand?: Brand;
	productName?: string;
	display?: DisplaySettings;
	dir?: 'rtl' | 'ltr';
	author?: Author;
} ) {
	return (
		<ApiContext.Provider value={ api }>
			<QueryClientProvider client={ queryClient }>
				<DisplayProvider api={ api } initial={ display }>
					<Shell
						api={ api }
						initialBrand={ brand }
						productName={ productName }
						dir={ dir }
						author={ author }
					/>
				</DisplayProvider>
			</QueryClientProvider>
		</ApiContext.Provider>
	);
}

/**
 * The page: header, the routed screen and the footer. The owner's brand is
 * read here, so a save on the settings screen recolours it without a reload.
 *
 * @param props
 * @param props.api          The REST client.
 * @param props.initialBrand The brand the page was rendered with.
 * @param props.productName  What an empty brand name means.
 * @param props.dir          The direction of the plugin's language.
 * @param props.author       The maker, credited in the footer.
 */
function Shell( {
	api,
	initialBrand,
	productName,
	dir,
	author,
}: {
	api: ApiClient;
	initialBrand: Brand;
	productName: string;
	dir: 'rtl' | 'ltr';
	author: Author;
} ) {
	const route = useRoute();
	const section = sectionOf( route );
	const [ theme, setTheme ] = useTheme();
	const { data: brand } = useQuery( {
		queryKey: [ '/brand' ],
		queryFn: () => api.get< Brand >( '/brand' ),
		initialData: initialBrand,
		staleTime: Infinity,
	} );
	const vars = paletteVars( brand.color );
	usePageAccent( vars );

	return (
		<div
			className="vqy-admin"
			dir={ dir }
			data-theme={ theme }
			style={ vars as CSSProperties }
		>
			<header className="vqy-admin__header">
				{ productName !== '' && (
					<BrandMark brand={ brand } productName={ productName } />
				) }
				<nav
					className="vqy-admin__nav"
					aria-label={ __( 'Sections', 'vaqtyar' ) }
				>
					{ SECTIONS.filter( ( item ) => item.menu !== false ).map(
						( item ) => (
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
						)
					) }
				</nav>
				<ThemeSwitch theme={ theme } onChange={ setTheme } />
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
			<footer className="vqy-admin__footer">
				{ author.url !== '' && (
					<a
						href={ author.url }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ sprintf(
							/* translators: %s: the maker's name, io98 */
							__( 'Made by %s', 'vaqtyar' ),
							author.name
						) }
					</a>
				) }
			</footer>
			<Snackbars />
		</div>
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
