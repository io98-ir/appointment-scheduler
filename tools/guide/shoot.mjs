/**
 * Takes the screenshots of docs/guide: serves the built admin app and widget
 * (tools/guide/out, from `pnpm guide:build`) against the made-up business in
 * mock-api.cjs, and photographs each screen in a real browser.
 *
 *     pnpm guide:build
 *     pnpm guide:shoot            # all scenes
 *     pnpm guide:shoot dashboard  # the scenes whose name contains this
 *
 * Set CHROME_PATH to a Chromium or Chrome executable when Playwright's own
 * browser is not installed.
 */
import { createServer } from 'node:http';
import { existsSync, mkdirSync, readFileSync, readdirSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire( import.meta.url );
const root = resolve( dirname( fileURLToPath( import.meta.url ) ), '../..' );
const out = join( root, 'tools/guide/out' );
const images = join( root, 'docs/guide/img' );
const { answer, day } = require( './mock-api.cjs' );

function playwright() {
	const pnpm = join( root, 'node_modules/.pnpm' );
	const folder = readdirSync( pnpm ).find( ( name ) =>
		name.startsWith( 'playwright-core@' )
	);
	if ( ! folder ) {
		throw new Error( 'playwright-core is not installed (pnpm install).' );
	}

	return require( join( pnpm, folder, 'node_modules/playwright-core' ) );
}

function chromePath() {
	if ( process.env.CHROME_PATH ) {
		return process.env.CHROME_PATH;
	}
	const base = join( process.env.LOCALAPPDATA ?? '', 'ms-playwright' );
	if ( existsSync( base ) ) {
		for ( const name of readdirSync( base ).sort().reverse() ) {
			const exe = join( base, name, 'chrome-win64/chrome.exe' );
			if ( name.startsWith( 'chromium-' ) && existsSync( exe ) ) {
				return exe;
			}
		}
	}

	return undefined;
}

const faAdmin = JSON.parse(
	readFileSync(
		join( root, 'languages/vaqtyar-fa_IR-vaqtyar-admin.json' ),
		'utf8'
	)
).locale_data.messages;

/** The Persian for an English string of the admin app, as the screen shows it. */
const fa = ( english ) => faAdmin[ english ]?.[ 0 ] ?? english;

const state = {
	brand: { name: '', logo_url: '', color: '' },
	general: { calendar: 'jalali', digits: 'persian', language: 'fa' },
	onboarded: true,
};

const TYPES = {
	'.js': 'text/javascript',
	'.css': 'text/css',
	'.json': 'application/json',
};

function adminPage( origin, { lang, theme } ) {
	const persian = lang === 'fa';
	const config = {
		restUrl: `${ origin }/wp-json/vaqtyar/v1/`,
		nonce: 'x',
		brand: state.brand,
		product_name: persian ? 'وقت یار' : 'Vaqtyar',
		display: {
			...state.general,
			language: persian ? 'fa' : 'en',
			locale: persian ? 'fa_IR' : 'en_US',
			dir: persian ? 'rtl' : 'ltr',
		},
		author: { name: 'io98', url: 'https://io98.ir' },
	};
	const dir = persian ? 'rtl' : 'ltr';
	const menu = [
		persian ? 'پیشخوان' : 'Dashboard',
		persian ? 'نوشته‌ها' : 'Posts',
		persian ? 'برگه‌ها' : 'Pages',
		persian ? 'دیدگاه‌ها' : 'Comments',
	];

	return `<!doctype html>
<html lang="${ lang }" dir="${ dir }">
<head>
<meta charset="utf-8">
<title>${ config.product_name }</title>
<link rel="stylesheet" href="/style-components${ persian ? '-rtl' : '' }.css">
<link rel="stylesheet" href="/admin.css">
<style>
	body { margin: 0; background: #f0f0f1; color: #3c434a; font: 13px/1.4 -apple-system, "Segoe UI", Tahoma, sans-serif; }
	#wpadminbar { block-size: 32px; background: #1d2327; }
	#layout { display: flex; min-block-size: calc(100vh - 32px); }
	#adminmenuwrap { inline-size: 160px; flex: none; background: #1d2327; color: #c3c4c7; padding-block: 12px; }
	#adminmenuwrap li { list-style: none; padding: 8px 12px; display: flex; gap: 8px; align-items: center; }
	#adminmenuwrap ul { margin: 0; padding: 0; }
	#adminmenuwrap .current { background: #2271b1; color: #fff; }
	#adminmenuwrap img { inline-size: 20px; block-size: 20px; opacity: 0.6; }
	#adminmenuwrap .current img { opacity: 1; filter: brightness(10); }
	#wpcontent { flex: 1; padding-inline-start: 20px; min-inline-size: 0; }
	.wrap { margin: 10px 20px 0 0; }
	[dir="rtl"] .wrap { margin: 10px 0 0 20px; }
	.vqy-admin { margin-inline: 0 !important; }
</style>
</head>
<body class="wp-admin">
<div id="wpadminbar"></div>
<div id="layout">
<div id="adminmenuwrap"><ul>
${ menu.map( ( item ) => `<li>${ item }</li>` ).join( '' ) }
<li class="current"><img src="/menu-icon.svg" alt=""> ${ config.product_name }</li>
<li>${ persian ? 'افزونه‌ها' : 'Plugins' }</li>
<li>${ persian ? 'تنظیمات' : 'Settings' }</li>
</ul></div>
<div id="wpcontent"><div class="wrap">
<div id="vaqtyar-admin" data-config='${ JSON.stringify( config ).replace( /'/g, '&#39;' ) }'></div>
</div></div>
</div>
<script>try { localStorage.setItem( 'vqy-admin-theme', '${ theme }' ); } catch ( e ) {}</script>
<script src="/admin.js?lang=${ lang }"></script>
</body>
</html>`;
}

function widgetPage( origin, kind ) {
	const config = {
		restUrl: `${ origin }/wp-json/vaqtyar/v1/`,
		calendar: 'jalali',
		digits: 'persian',
		paymentParam: 'vqy_payment',
		...( kind === 'booking' ? { service: 1 } : {} ),
	};
	const attribute = kind === 'booking' ? 'widget' : 'panel';

	return `<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<title>سایت نمونه</title>
<link rel="stylesheet" href="/widget-rtl.css">
<style>
	body { margin: 0; background: #fff; color: #1f2937; font: 16px/1.7 Vazirmatn, "Segoe UI", Tahoma, sans-serif; }
	header { padding: 18px 32px; border-block-end: 1px solid #e5e7eb; font-weight: 700; }
	main { max-inline-size: 640px; margin: 32px auto; padding-inline: 16px; }
	h1 { font-size: 1.6em; margin-block: 0 16px; }
</style>
</head>
<body>
<header>کلینیک نمونه</header>
<main>
<h1>${ kind === 'booking' ? 'نوبت‌گیری آنلاین' : 'نوبت‌های من' }</h1>
<div data-vaqtyar-${ attribute }='${ JSON.stringify( config ) }' dir="rtl" style="--vqy-accent:#0e7184"></div>
</main>
<script src="/widget.js?lang=fa"></script>
</body>
</html>`;
}

function serve( menuIcon ) {
	const server = createServer( ( request, response ) => {
		const url = new URL( request.url ?? '/', 'http://localhost' );
		const origin = `http://${ request.headers.host }`;
		if ( url.pathname.startsWith( '/wp-json/vaqtyar/v1' ) ) {
			const chunks = [];
			request.on( 'data', ( chunk ) => chunks.push( chunk ) );
			request.on( 'end', () => {
				let body = null;
				try {
					body = JSON.parse( Buffer.concat( chunks ).toString() );
				} catch {
					// No body.
				}
				const result = answer(
					url.pathname.replace( '/wp-json/vaqtyar/v1', '' ),
					request.method ?? 'GET',
					url.searchParams,
					body,
					state
				);
				response.writeHead( result.status, {
					'Content-Type': 'application/json',
					...result.headers,
				} );
				response.end( JSON.stringify( result.body ) );
			} );

			return;
		}
		if ( url.pathname === '/admin' ) {
			response.writeHead( 200, { 'Content-Type': 'text/html; charset=utf-8' } );
			response.end(
				adminPage( origin, {
					lang: url.searchParams.get( 'lang' ) ?? 'fa',
					theme: url.searchParams.get( 'theme' ) ?? 'light',
				} )
			);

			return;
		}
		if ( url.pathname === '/widget-page' ) {
			response.writeHead( 200, { 'Content-Type': 'text/html; charset=utf-8' } );
			response.end( widgetPage( origin, url.searchParams.get( 'kind' ) ?? 'booking' ) );

			return;
		}
		if ( url.pathname === '/menu-icon.svg' ) {
			response.writeHead( 200, { 'Content-Type': 'image/svg+xml' } );
			response.end( menuIcon );

			return;
		}
		const file = join( out, url.pathname );
		if ( file.startsWith( out ) && existsSync( file ) ) {
			const extension = file.slice( file.lastIndexOf( '.' ) );
			response.writeHead( 200, { 'Content-Type': TYPES[ extension ] ?? 'text/plain' } );
			response.end( readFileSync( file ) );

			return;
		}
		response.writeHead( 404 );
		response.end();
	} );

	return new Promise( ( done ) =>
		server.listen( 0, '127.0.0.1', () => done( server ) )
	);
}

async function settle( page ) {
	await page.waitForLoadState( 'networkidle' );
	await page.waitForTimeout( 350 );
}

async function adminScene( page, origin, { hash = '', lang = 'fa', theme = 'light' } ) {
	await page.goto( `${ origin }/admin?lang=${ lang }&theme=${ theme }#${ hash }` );
	await page.waitForSelector( '.vqy-admin' );
	await page.addStyleTag( { content: '.vqy-admin__snackbars { display: none; }' } );
	await settle( page );
}

const scenes = [
	{
		name: '01-menu-icon',
		async run( page, origin ) {
			await adminScene( page, origin, { hash: '/' } );
			await page.locator( '#adminmenuwrap' ).screenshot( {
				path: join( images, '01-menu-icon.png' ),
			} );
		},
		scale: 3,
		viewport: { width: 1200, height: 420 },
	},
	{
		name: '02-wizard-language',
		async run( page, origin ) {
			state.onboarded = false;
			await adminScene( page, origin, { hash: '/setup' } );
			await page.locator( '#vaqtyar-admin' ).screenshot( {
				path: join( images, '02-wizard-language.png' ),
			} );
		},
	},
	{
		name: '03-wizard-brand',
		async run( page, origin ) {
			state.onboarded = false;
			await adminScene( page, origin, { hash: '/setup' } );
			await page.getByRole( 'button', { name: fa( 'Skip this step' ) } ).click();
			await settle( page );
			await page.locator( '#vaqtyar-admin' ).screenshot( {
				path: join( images, '03-wizard-brand.png' ),
			} );
			state.onboarded = true;
		},
	},
	{
		name: '04-settings-language',
		async run( page, origin ) {
			await adminScene( page, origin, { hash: '/settings' } );
			await page
				.locator( 'section.vqy-admin__panel', {
					hasText: fa( 'Language and calendar' ),
				} )
				.first()
				.screenshot( { path: join( images, '04-settings-language.png' ) } );
		},
	},
	{
		name: '05-settings-palette',
		async run( page, origin ) {
			await adminScene( page, origin, { hash: '/settings' } );
			await page
				.locator( 'section.vqy-admin__panel', { hasText: fa( 'Palette' ) } )
				.first()
				.screenshot( { path: join( images, '05-settings-palette.png' ) } );
		},
	},
	{
		name: '06-palette-rose',
		async run( page, origin ) {
			await adminScene( page, origin, { hash: '/settings' } );
			await page.getByRole( 'button', { name: fa( 'Rose' ) } ).click();
			await page.getByRole( 'button', { name: fa( 'Save look' ) } ).click();
			await settle( page );
			await page.evaluate( () => {
				window.location.hash = '#/';
			} );
			await settle( page );
			await page.locator( '#vaqtyar-admin' ).screenshot( {
				path: join( images, '06-palette-rose.png' ),
			} );
			state.brand.color = '';
		},
	},
	{
		name: '07-settings-booking-rules',
		async run( page, origin ) {
			await adminScene( page, origin, { hash: '/settings' } );
			await page
				.locator( 'section.vqy-admin__panel', { hasText: fa( 'Booking rules' ) } )
				.first()
				.screenshot( { path: join( images, '07-settings-booking-rules.png' ) } );
		},
	},
	{
		name: '08-dashboard',
		async run( page, origin ) {
			await adminScene( page, origin, { hash: '/' } );
			await page.locator( '#vaqtyar-admin' ).screenshot( {
				path: join( images, '08-dashboard.png' ),
			} );
		},
	},
	{
		name: '09-services',
		async run( page, origin ) {
			await adminScene( page, origin, { hash: '/services' } );
			await page.locator( '#vaqtyar-admin' ).screenshot( {
				path: join( images, '09-services.png' ),
			} );
		},
	},
	{
		name: '10-service-edit',
		async run( page, origin ) {
			await adminScene( page, origin, { hash: '/services/1' } );
			await page.locator( '#vaqtyar-admin' ).screenshot( {
				path: join( images, '10-service-edit.png' ),
			} );
		},
		viewport: { width: 1280, height: 1400 },
	},
	{
		name: '11-staff-hours',
		async run( page, origin ) {
			await adminScene( page, origin, { hash: '/staff/1' } );
			await page.locator( '#vaqtyar-admin' ).screenshot( {
				path: join( images, '11-staff-hours.png' ),
			} );
		},
		viewport: { width: 1280, height: 1500 },
	},
	{
		name: '12-calendar',
		async run( page, origin ) {
			await adminScene( page, origin, { hash: '/calendar' } );
			await page.locator( '#vaqtyar-admin' ).screenshot( {
				path: join( images, '12-calendar.png' ),
			} );
		},
		viewport: { width: 1280, height: 1100 },
	},
	{
		name: '13-quick-book',
		async run( page, origin ) {
			await adminScene( page, origin, { hash: '/calendar' } );
			const column = page.locator( '.vqy-calendar__column' ).nth( 2 );
			await column.click( { position: { x: 120, y: 420 } } );
			await page.waitForSelector( '.components-modal__frame' );
			await settle( page );
			await page.locator( '.components-modal__frame' ).screenshot( {
				path: join( images, '13-quick-book.png' ),
			} );
		},
		viewport: { width: 1280, height: 1100 },
	},
	{
		name: '14-appointments',
		async run( page, origin ) {
			await adminScene( page, origin, { hash: '/appointments' } );
			await page.locator( '#vaqtyar-admin' ).screenshot( {
				path: join( images, '14-appointments.png' ),
			} );
		},
		viewport: { width: 1280, height: 1000 },
	},
	{
		name: '15-appointment-detail',
		async run( page, origin ) {
			await adminScene( page, origin, { hash: '/appointments/3' } );
			await page.locator( '#vaqtyar-admin' ).screenshot( {
				path: join( images, '15-appointment-detail.png' ),
			} );
		},
		viewport: { width: 1280, height: 1100 },
	},
	{
		name: '16-customers',
		async run( page, origin ) {
			await adminScene( page, origin, { hash: '/customers' } );
			await page.locator( '#vaqtyar-admin' ).screenshot( {
				path: join( images, '16-customers.png' ),
			} );
		},
		viewport: { width: 1280, height: 900 },
	},
	{
		name: '17-holidays-date-picker',
		async run( page, origin ) {
			await adminScene( page, origin, { hash: '/holidays' } );
			await page.getByRole( 'button', { name: fa( 'Calendar' ) } ).first().click();
			await page.waitForSelector( '.vqy-date__popup' );
			// The popup hangs below the panel, so photograph both.
			const app = await page.locator( '#vaqtyar-admin' ).boundingBox();
			const popup = await page.locator( '.vqy-date__popup' ).boundingBox();
			const bottom = Math.max( app.y + app.height, popup.y + popup.height + 12 );
			await page.screenshot( {
				path: join( images, '17-holidays-date-picker.png' ),
				fullPage: true,
				clip: { x: app.x, y: app.y, width: app.width, height: bottom - app.y },
			} );
		},
		viewport: { width: 1280, height: 1250 },
	},
	{
		name: '18-notifications',
		async run( page, origin ) {
			await adminScene( page, origin, { hash: '/notifications' } );
			await page.locator( '#vaqtyar-admin' ).screenshot( {
				path: join( images, '18-notifications.png' ),
			} );
		},
		viewport: { width: 1280, height: 900 },
	},
	{
		name: '19-reports',
		async run( page, origin ) {
			await adminScene( page, origin, { hash: '/reports' } );
			await page.locator( '#vaqtyar-admin' ).screenshot( {
				path: join( images, '19-reports.png' ),
			} );
		},
		viewport: { width: 1280, height: 1400 },
	},
	{
		name: '20-status',
		async run( page, origin ) {
			await adminScene( page, origin, { hash: '/status' } );
			await page.locator( '#vaqtyar-admin' ).screenshot( {
				path: join( images, '20-status.png' ),
			} );
		},
		viewport: { width: 1280, height: 1000 },
	},
	{
		name: '21-dark',
		async run( page, origin ) {
			await adminScene( page, origin, { hash: '/', theme: 'dark' } );
			await page.locator( '#vaqtyar-admin' ).screenshot( {
				path: join( images, '21-dark.png' ),
			} );
		},
	},
	{
		name: '22-english-gregorian',
		async run( page, origin ) {
			state.general.calendar = 'gregorian';
			state.general.digits = 'latin';
			await adminScene( page, origin, { hash: '/appointments', lang: 'en' } );
			await page.locator( '#vaqtyar-admin' ).screenshot( {
				path: join( images, '22-english-gregorian.png' ),
			} );
			state.general.calendar = 'jalali';
			state.general.digits = 'persian';
		},
		viewport: { width: 1280, height: 1000 },
	},
	{
		name: '23-widget-booking',
		async run( page, origin ) {
			await page.goto( `${ origin }/widget-page?kind=booking&lang=fa` );
			await page.waitForSelector( '.vqy-widget' );
			await settle( page );
			await page.screenshot( { path: join( images, '23-widget-booking.png' ) } );
		},
		viewport: { width: 900, height: 900 },
	},
	{
		name: '24-widget-slots',
		async run( page, origin ) {
			await page.goto( `${ origin }/widget-page?kind=booking&lang=fa` );
			await page.waitForSelector( '.vqy-widget__day:not(:disabled)' );
			await settle( page );
			await page.locator( '.vqy-widget__day:not(:disabled)' ).first().click();
			await settle( page );
			await page.screenshot( { path: join( images, '24-widget-slots.png' ), fullPage: true } );
		},
		viewport: { width: 900, height: 900 },
	},
	{
		name: '25-customer-panel',
		async run( page, origin ) {
			await page.addInitScript( () => {
				window.sessionStorage.setItem(
					'vqy-panel-session',
					JSON.stringify( { token: 'T', expiresAt: Date.now() + 3600000 } )
				);
			} );
			await page.goto( `${ origin }/widget-page?kind=panel&lang=fa` );
			await page.waitForSelector( '.vqy-panel, .vqy-widget' );
			await settle( page );
			await page.screenshot( { path: join( images, '25-customer-panel.png' ), fullPage: true } );
		},
		viewport: { width: 900, height: 700 },
	},
];

const only = process.argv[ 2 ];
mkdirSync( images, { recursive: true } );
const { chromium } = playwright();
const { execFileSync } = await import( 'node:child_process' );
const icon = execFileSync(
	'php',
	[
		'-r',
		'require "vendor/autoload.php"; echo \\Vaqtyar\\Modules\\Admin\\Presentation\\MenuIcon::svg();',
	],
	{ cwd: root, encoding: 'utf8' }
);
const server = await serve( icon );
const origin = `http://127.0.0.1:${ server.address().port }`;
const browser = await chromium.launch( { executablePath: chromePath() } );
let failed = 0;
for ( const scene of scenes ) {
	if ( only && ! scene.name.includes( only ) ) {
		continue;
	}
	const context = await browser.newContext( {
		viewport: scene.viewport ?? { width: 1280, height: 900 },
		deviceScaleFactor: scene.scale ?? 1,
		locale: 'fa-IR',
	} );
	const page = await context.newPage();
	page.on( 'pageerror', ( error ) =>
		console.log(
			`  [page error] ${ ( error.stack ?? error.message )
				.split( '\n' )
				.slice( 0, 4 )
				.join( ' | ' ) }`
		)
	);
	try {
		await scene.run( page, origin );
		console.log( `ok   ${ scene.name }` );
	} catch ( error ) {
		failed++;
		console.log( `FAIL ${ scene.name }: ${ error.message.split( '\n' )[ 0 ] }` );
	}
	await context.close();
}
await browser.close();
server.close();
process.exit( failed > 0 ? 1 : 0 );
