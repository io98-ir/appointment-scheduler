import { expect, test } from '@wordpress/e2e-test-utils-playwright';

import identity from '../../../identity.json';

/**
 * T3.3: the calendar books a clicked time and moves a dragged appointment,
 * against the real REST API (holds, bookings, reschedule). The catalog it
 * needs is made through the API and removed afterwards.
 */
const app = ( route: string ) =>
	`/wp-admin/admin.php?page=${ identity.slug }#${ route }`;
const path = ( route: string ) => `/${ identity.rest_namespace }${ route }`;

/** Three days ahead in Tehran: inside any lead time and booking window. */
function dayAhead(): string {
	const now = new Date( Date.now() + 3 * 24 * 3600 * 1000 );

	return new Intl.DateTimeFormat( 'en-CA', {
		timeZone: 'Asia/Tehran',
	} ).format( now );
}

type Made = { id: number } & Record< string, unknown >;

test.describe( 'calendar', () => {
	const made: Record< string, number > = {};

	test.beforeAll( async ( { requestUtils } ) => {
		const post = ( route: string, data: object ) =>
			requestUtils.rest< Made >( {
				method: 'POST',
				path: path( route ),
				data,
			} );
		made.location = (
			await post( '/locations', {
				name: 'E2E calendar branch',
				timezone: 'Asia/Tehran',
			} )
		).id;
		made.staff = (
			await post( '/staff', {
				name: 'E2E Calendar staff',
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
		made.service = (
			await post( '/services', {
				name: 'E2E calendar visit',
				variants: [
					{
						id: null,
						label: '',
						duration_min: 60,
						price: { amount: 1000000, currency: 'IRR' },
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
			} )
		).id;
		made.customer = (
			await post( '/customers', {
				first_name: 'E2E',
				last_name: 'Rahimi',
				phone: '09120000333',
			} )
		).id;
	} );

	test.afterAll( async ( { requestUtils } ) => {
		const calendar = await requestUtils.rest< { id: number }[] >( {
			path: path( '/calendar' ),
			params: {
				from: `${ dayAhead() }T00:00:00+03:30`,
				to: `${ dayAhead() }T23:59:00+03:30`,
				location: made.location,
			},
		} );
		for ( const { id } of calendar ) {
			await requestUtils.rest( {
				method: 'POST',
				path: path( `/appointments/${ id }/cancel` ),
				data: { reason: 'E2E cleanup' },
			} );
		}
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

	test( 'a clicked time is booked, then dragged to another time', async ( {
		page,
	} ) => {
		await page.goto( app( `/calendar/day/${ dayAhead() }` ) );
		await page
			.getByLabel( 'Location', { exact: true } )
			.selectOption( { label: 'E2E calendar branch' } );
		const column = page.locator( '[data-column="E2E Calendar staff"]' );

		// The grid starts at 07:00, one pixel a minute: 10:00 is 180px down.
		await column.click( { position: { x: 20, y: 185 } } );
		const dialog = page.getByRole( 'dialog' );
		await expect( dialog.getByLabel( 'Start' ) ).toHaveValue( /T10:00:00/ );
		await dialog.getByLabel( 'Find customer' ).fill( 'Rahimi' );
		await expect( dialog.getByLabel( 'Customer', { exact: true } ) ).toBeVisible();
		await dialog.getByRole( 'button', { name: 'Book', exact: true } ).click();
		await expect(
			page.locator( '.components-snackbar' ).getByText( 'Appointment booked.' ).last()
		).toBeVisible();

		const booked = column.getByRole( 'button', { name: /10:00–11:00/ } );
		await expect( booked ).toContainText( 'E2E Rahimi' );

		// A pointer drag, grabbed 5px below its top and let go 120px lower: 12:00.
		const box = await booked.boundingBox();
		if ( box === null ) {
			throw new Error( 'The appointment is not on screen.' );
		}
		await page.mouse.move( box.x + 10, box.y + 5 );
		await page.mouse.down();
		await page.mouse.move( box.x + 10, box.y + 125, { steps: 12 } );
		await page.mouse.up();
		await expect(
			page.locator( '.components-snackbar' ).getByText( 'Appointment moved.' ).last()
		).toBeVisible();
		await expect(
			column.getByRole( 'button', { name: /12:00–13:00/ } )
		).toContainText( 'E2E Rahimi' );

		// A reload reads it back from the server.
		await page.reload();
		await page
			.getByLabel( 'Location', { exact: true } )
			.selectOption( { label: 'E2E calendar branch' } );
		await expect(
			column.getByRole( 'button', { name: /12:00–13:00/ } )
		).toBeVisible();
	} );
} );
