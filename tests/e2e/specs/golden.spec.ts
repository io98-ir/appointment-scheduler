import { request } from '@playwright/test';
import { expect, test } from '@wordpress/e2e-test-utils-playwright';

import identity from '../../../identity.json';

/**
 * T6.5, the golden path of a booking site: a visitor with no login finds the
 * service in the widget on a page, books a time through the public API with
 * the REST nonce the widget itself uses (no session, no cookie), and the
 * front desk finds that booking in the admin app and cancels it. The
 * widget's own clicks are covered by its component tests; this checks the
 * whole stack around it, with the build the plugin ships.
 */
const app = ( route: string ) =>
	`/wp-admin/admin.php?page=${ identity.slug }#${ route }`;
const path = ( route: string ) => `/${ identity.rest_namespace }${ route }`;
const PHONE = '09120000555';

/** Four days ahead in Tehran: inside any lead time and booking window. */
function dayAhead(): string {
	const now = new Date( Date.now() + 4 * 24 * 3600 * 1000 );

	return new Intl.DateTimeFormat( 'en-CA', {
		timeZone: 'Asia/Tehran',
	} ).format( now );
}

type Made = { id: number } & Record< string, unknown >;

test.describe( 'golden path', () => {
	const made: Record< string, number > = {};
	let variant = 0;
	let pageId = 0;
	let pageLink = '';
	let code = '';
	let start = '';

	test.beforeAll( async ( { requestUtils } ) => {
		const post = < T = Made >( route: string, data: object ) =>
			requestUtils.rest< T >( {
				method: 'POST',
				path: path( route ),
				data,
			} );
		made.location = (
			await post( '/locations', {
				name: 'E2E golden branch',
				timezone: 'Asia/Tehran',
			} )
		).id;
		made.staff = (
			await post( '/staff', {
				name: 'E2E Golden staff',
				color: '#2271b1',
				location_id: made.location,
			} )
		).id;
		await requestUtils.rest( {
			method: 'PUT',
			path: path( `/schedules/staff/${ made.staff }` ),
			data: {
				rules: Array.from( { length: 7 }, ( _, weekday ) => ( {
					weekday,
					start: '09:00',
					end: '17:00',
					kind: 'work',
				} ) ),
			},
		} );
		const service = await post< Made & { variants: { id: number }[] } >(
			'/services',
			{
				name: 'E2E golden visit',
				variants: [
					{
						id: null,
						label: '',
						duration_min: 60,
						price: { amount: 2000000, currency: 'IRR' },
						is_default: true,
						buffer_before_min: 0,
						buffer_after_min: 0,
						slot_step_min: 60,
						sort: 0,
					},
				],
				staff: [
					{
						staff_id: made.staff,
						variant_id: null,
						price: null,
						duration_min: null,
					},
				],
			}
		);
		made.service = service.id;
		variant = service.variants[ 0 ]?.id ?? 0;
		const created = await requestUtils.createPage( {
			title: 'E2E golden page',
			status: 'publish',
			content: `[${ identity.slug }_booking]`,
		} );
		pageId = created.id;
		pageLink = `/?page_id=${ created.id }`;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		const quietly = ( route: string ) =>
			// 204 has no body, which requestUtils.rest() fails to parse.
			requestUtils
				.rest( { method: 'DELETE', path: path( route ) } )
				.catch( ( error: unknown ) => {
					if ( ! ( error instanceof SyntaxError ) ) {
						throw error;
					}
				} );
		if ( pageId ) {
			await requestUtils
				.rest( {
					method: 'DELETE',
					path: `/wp/v2/pages/${ pageId }`,
					params: { force: true },
				} )
				.catch( () => undefined );
		}
		const found = await requestUtils
			.rest< unknown >( {
				path: path( '/customers' ),
				params: { search: PHONE },
			} )
			.catch( () => [] );
		const customers = Array.isArray( found )
			? found
			: ( ( found as { items?: Made[] } ).items ?? [] );
		for ( const customer of customers as Made[] ) {
			await quietly( `/customers/${ customer.id }` ).catch(
				() => undefined
			);
		}
		for ( const route of [
			`/services/${ made.service }`,
			`/staff/${ made.staff }`,
			`/locations/${ made.location }`,
		] ) {
			await quietly( route ).catch( () => undefined );
		}
	} );

	test( 'the widget on a page offers the service to a visitor', async ( {
		page,
	} ) => {
		const errors: string[] = [];
		page.on( 'pageerror', ( error ) => errors.push( error.message ) );

		await page.goto( pageLink );

		await expect( page.getByText( 'E2E golden visit' ) ).toBeVisible();
		expect( errors ).toEqual( [] );
	} );

	test( 'a visitor books with no login and the front desk cancels it', async ( {
		page,
		requestUtils,
	} ) => {
		const baseURL = process.env.WP_BASE_URL ?? 'http://localhost:8888';
		// A context of its own: the built-in one carries the admin's cookies.
		const guest = await request.newContext( { baseURL } );
		// Pretty permalinks are off on the test site, so routes go by rest_route.
		const route = ( rest: string, query = '' ) =>
			`/?rest_route=${ path( rest ) }${ query }`;
		try {
			const { nonce } = ( await (
				await guest.get( route( '/nonce' ) )
			).json() ) as { nonce: string };
			const first = ( await (
				await guest.get(
					route(
						'/availability',
						`&variant=${ variant }&location=${ made.location }&view=first&date=${ dayAhead() }`
					)
				)
			).json() ) as { slots: { start: string }[] };
			start = first.slots[ 0 ]?.start ?? '';
			expect( start ).toBeTruthy();
			const headers = { 'X-WP-Nonce': nonce };

			const hold = await guest.post( route( '/holds' ), {
				headers,
				data: {
					variant,
					location: made.location,
					staff: made.staff,
					start,
				},
			} );
			expect( hold.status() ).toBe( 201 );
			const { token } = ( await hold.json() ) as { token: string };

			const book = await guest.post( route( '/book' ), {
				headers,
				data: {
					hold_token: token,
					first_name: 'E2E',
					last_name: 'Visitor',
					phone: PHONE,
				},
			} );
			expect( book.status() ).toBe( 201 );
			const booked = ( await book.json() ) as {
				code: string;
				status: string;
			};
			code = booked.code;
			expect( code ).toMatch( /\S+/ );
			expect( booked.status ).toBe( 'confirmed' );

			// The same hold cannot be booked twice.
			const again = await guest.post( route( '/book' ), {
				headers,
				data: {
					hold_token: token,
					first_name: 'E2E',
					last_name: 'Visitor',
					phone: PHONE,
				},
			} );
			expect( again.ok() ).toBe( false );
		} finally {
			await guest.dispose();
		}

		await page.goto( app( '/appointments' ) );
		await page.getByLabel( 'Search by code, name or phone' ).fill( code );
		const row = page.getByRole( 'row', { name: new RegExp( code ) } );
		await expect( row ).toContainText( 'E2E Visitor' );
		await expect( row ).toContainText( 'Confirmed' );
		await row.getByRole( 'link', { name: code } ).click();

		await page
			.getByRole( 'button', { name: 'Cancel appointment' } )
			.click();
		const dialog = page.getByRole( 'dialog' );
		await dialog.getByLabel( 'Reason' ).fill( 'E2E golden path' );
		await dialog
			.getByRole( 'button', { name: 'Cancel appointment' } )
			.click();
		await expect(
			page
				.locator( '.components-snackbar' )
				.getByText( 'Appointment updated.' )
				.last()
		).toBeVisible();

		await page.reload();
		await expect( page.locator( '.vqy-appointment__title' ) ).toContainText(
			'Cancelled'
		);

		// The time is free again for the next visitor.
		const free = await requestUtils.rest< { slots: { start: string }[] } >(
			{
				path: path( '/availability' ),
				params: {
					variant,
					location: made.location,
					view: 'first',
					date: dayAhead(),
				},
			}
		);
		expect( free.slots.map( ( slot ) => slot.start ) ).toContain( start );
	} );
} );
