import { expect, test } from '@wordpress/e2e-test-utils-playwright';

import identity from '../../../identity.json';

/**
 * T3.4: an appointment booked through the REST API is found in the list,
 * opened, given an internal note and cancelled with a reason, all read back
 * from the server. The catalog it needs is made through the API and removed
 * afterwards.
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

test.describe( 'appointments', () => {
	const made: Record< string, number > = {};
	let code = '';

	test.beforeAll( async ( { requestUtils } ) => {
		const post = < T = Made >( route: string, data: object ) =>
			requestUtils.rest< T >( {
				method: 'POST',
				path: path( route ),
				data,
			} );
		made.location = (
			await post( '/locations', {
				name: 'E2E list branch',
				timezone: 'Asia/Tehran',
			} )
		).id;
		made.staff = (
			await post( '/staff', {
				name: 'E2E List staff',
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
				name: 'E2E list visit',
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
		made.customer = (
			await post( '/customers', {
				first_name: 'E2E',
				last_name: 'Karimi',
				phone: '09120000444',
			} )
		).id;
		const hold = await post< { token: string } >( '/holds', {
			variant: service.variants[ 0 ]?.id,
			location: made.location,
			staff: made.staff,
			start: `${ dayAhead() }T10:00:00+03:30`,
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

	test( 'is found, noted and cancelled', async ( { page } ) => {
		await page.goto( app( '/appointments' ) );
		await page
			.getByLabel( 'Search by code, name or phone' )
			.fill( code );
		// The search is kept in the route.
		await expect( page ).toHaveURL( new RegExp( `search=${ code }` ) );
		const row = page.getByRole( 'row', { name: new RegExp( code ) } );
		await expect( row ).toContainText( 'E2E Karimi' );
		await expect( row ).toContainText( 'Confirmed' );
		await row.getByRole( 'link', { name: code } ).click();

		await expect( page.getByRole( 'heading', { name: code } ) ).toBeVisible();
		await expect( page.getByText( 'E2E list visit' ) ).toBeVisible();
		await page.getByLabel( 'Internal note' ).fill( 'Prefers the window seat' );
		await page.getByRole( 'button', { name: 'Save note' } ).click();
		await expect(
			page.locator( '.components-snackbar' ).getByText( 'Note saved.' ).last()
		).toBeVisible();

		await page.getByRole( 'button', { name: 'Cancel appointment' } ).click();
		const dialog = page.getByRole( 'dialog' );
		await dialog.getByLabel( 'Reason' ).fill( 'Asked by phone' );
		await dialog
			.getByRole( 'button', { name: 'Cancel appointment' } )
			.click();
		await expect(
			page
				.locator( '.components-snackbar' )
				.getByText( 'Appointment updated.' )
				.last()
		).toBeVisible();

		// A reload reads it all back from the server.
		await page.reload();
		await expect( page.locator( '.vqy-appointment__title' ) ).toContainText(
			'Cancelled'
		);
		await expect( page.getByLabel( 'Internal note' ) ).toHaveValue(
			'Prefers the window seat'
		);
		await expect( page.getByText( 'Asked by phone' ).first() ).toBeVisible();
		await expect(
			page.getByRole( 'button', { name: 'Cancel appointment' } )
		).toHaveCount( 0 );
	} );
} );
