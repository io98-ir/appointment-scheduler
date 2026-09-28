import { expect, test } from '@wordpress/e2e-test-utils-playwright';

import identity from '../../../identity.json';

/**
 * T3.5: a customer created through the admin screen is found in the list,
 * opened, edited and shows the appointment booked for them through the REST
 * API, all read back from the server.
 */
const app = ( route: string ) =>
	`/wp-admin/admin.php?page=${ identity.slug }#${ route }`;
const path = ( route: string ) => `/${ identity.rest_namespace }${ route }`;

/** Four days ahead in Tehran: inside any lead time and booking window. */
function dayAhead(): string {
	const now = new Date( Date.now() + 4 * 24 * 3600 * 1000 );

	return new Intl.DateTimeFormat( 'en-CA', {
		timeZone: 'Asia/Tehran',
	} ).format( now );
}

type Made = { id: number } & Record< string, unknown >;

test.describe( 'customers', () => {
	const made: Record< string, number > = {};
	let code = '';

	test.beforeAll( async ( { requestUtils } ) => {
		const post = < T = Made >( route: string, data: object ) =>
			requestUtils.rest< T >( { method: 'POST', path: path( route ), data } );
		made.location = (
			await post( '/locations', {
				name: 'E2E customer branch',
				timezone: 'Asia/Tehran',
			} )
		).id;
		made.staff = (
			await post( '/staff', {
				name: 'E2E Customer staff',
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
				name: 'E2E customer visit',
				variants: [
					{
						id: null,
						label: '',
						duration_min: 60,
						price: { amount: 1500000, currency: 'IRR' },
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
		made.customer = (
			await post( '/customers', {
				first_name: 'E2E',
				last_name: 'Profile',
				phone: '09120000555',
			} )
		).id;
		const hold = await post< { token: string } >( '/holds', {
			variant: service.variants[ 0 ]?.id,
			location: made.location,
			staff: made.staff,
			start: `${ dayAhead() }T11:00:00+03:30`,
		} );
		const booked = await post< Made & { code: string } >( '/bookings', {
			hold_token: hold.token,
			customer_id: made.customer,
		} );
		made.appointment = booked.id;
		code = booked.code;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils
			.rest( {
				method: 'POST',
				path: path( `/appointments/${ made.appointment }/cancel` ),
				data: { reason: 'E2E cleanup' },
			} )
			.catch( () => undefined );
		for ( const route of [
			`/customers/${ made.customer }`,
			`/services/${ made.service }`,
			`/staff/${ made.staff }`,
			`/locations/${ made.location }`,
		] ) {
			// 204 has no body, which requestUtils.rest() fails to parse.
			await requestUtils
				.rest( { method: 'DELETE', path: path( route ) } )
				.catch( ( error: unknown ) => {
					if ( ! ( error instanceof SyntaxError ) ) {
						throw error;
					}
				} );
		}
	} );

	test( 'is found, edited and shows its appointment history', async ( {
		page,
	} ) => {
		await page.goto( app( '/customers' ) );
		await page
			.getByLabel( 'Search by name, phone or email' )
			.fill( 'Profile' );
		const row = page.getByRole( 'row', { name: /E2E Profile/ } );
		await expect( row ).toBeVisible();
		await row.getByRole( 'link' ).click();

		await expect( page.getByLabel( 'First name' ) ).toHaveValue( 'E2E' );
		await expect(
			page.getByRole( 'row', { name: new RegExp( code ) } )
		).toContainText( 'Confirmed' );

		await page.getByLabel( 'Last name' ).fill( 'Renamed' );
		await page.getByRole( 'button', { name: 'Save' } ).click();
		await expect(
			page.locator( '.components-snackbar' ).getByText( 'Saved.' ).last()
		).toBeVisible();
		await page.reload();
		await expect( page.getByLabel( 'Last name' ) ).toHaveValue( 'Renamed' );
	} );

	test( 'is created through the admin screen and opens as the stored customer', async ( {
		page,
		requestUtils,
	} ) => {
		await page.goto( app( '/customers/new' ) );
		await page.getByLabel( 'First name' ).fill( 'E2E' );
		await page.getByLabel( 'Last name' ).fill( 'New' );
		await page.getByLabel( 'Phone' ).fill( '09120000666' );
		await page.getByRole( 'button', { name: 'Save' } ).click();

		await expect( page ).toHaveURL( /#\/customers\/\d+$/ );
		await expect(
			page.locator( '.components-snackbar' ).getByText( 'Saved.' ).last()
		).toBeVisible();
		await expect( page.getByText( 'No appointments yet.' ) ).toBeVisible();

		const id = page.url().split( '/' ).pop();
		await requestUtils
			.rest( { method: 'DELETE', path: path( `/customers/${ id }` ) } )
			// 204 has no body, which requestUtils.rest() fails to parse.
			.catch( ( error: unknown ) => {
				if ( ! ( error instanceof SyntaxError ) ) {
					throw error;
				}
			} );
	} );
} );
