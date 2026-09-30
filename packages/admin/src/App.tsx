import {
	QueryClientProvider,
	useQuery,
	type QueryClient,
} from '@tanstack/react-query';
import type { ApiClient, Brand } from '@vaqtyar/shared';
import { SelectControl, SnackbarList } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import { ApiContext } from './api';
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
 * It follows a save on the settings screen without a reload.
 *
 * @param props
 * @param props.api         The REST client.
 * @param props.initial     The brand the page was rendered with.
 * @param props.productName What an empty brand name means.
 */
function BrandMark( {
	api,
	initial,
	productName,
}: {
	api: ApiClient;
	initial: Brand;
	productName: string;
} ) {
	const { data } = useQuery( {
		queryKey: [ '/brand' ],
		queryFn: () => api.get< Brand >( '/brand' ),
		initialData: initial,
		staleTime: Infinity,
	} );
	const name = data.name || productName;

	return (
		<span className="vqy-admin__brand">
			{ data.logo_url && (
				<img className="vqy-admin__logo" src={ data.logo_url } alt="" />
			) }
			{ name }
		</span>
	);
}

export function App( {
	api,
	queryClient,
	brand = NO_BRAND,
	productName = '',
}: {
	api: ApiClient;
	queryClient: QueryClient;
	brand?: Brand;
	productName?: string;
} ) {
	const route = useRoute();
	const section = sectionOf( route );
	const [ theme, setTheme ] = useTheme();

	return (
		<ApiContext.Provider value={ api }>
			<QueryClientProvider client={ queryClient }>
				<div
					className="vqy-admin"
					data-theme={ theme }
					style={
						brand.color
							? ( {
									'--vqy-accent': brand.color,
								} as CSSProperties )
							: undefined
					}
				>
					<header className="vqy-admin__header">
						{ productName !== '' && (
							<BrandMark
								api={ api }
								initial={ brand }
								productName={ productName }
							/>
						) }
						<nav
							className="vqy-admin__nav"
							aria-label={ __( 'Sections', 'vaqtyar' ) }
						>
							{ SECTIONS.filter(
								( item ) => item.menu !== false
							).map( ( item ) => (
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
