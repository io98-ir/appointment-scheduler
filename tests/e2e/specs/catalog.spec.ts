import { expect, test } from '@wordpress/e2e-test-utils-playwright';

import identity from '../../../identity.json';

/**
 * T3.2: the catalog screens against the real REST API, with the build the
 * plugin ships. Each test cleans up what it made, through the same screens.
 */
const app = ( route: string ) =>
	`/wp-admin/admin.php?page=${ identity.slug }#${ route }`;

test.describe( 'catalog', () => {
	test( 'a location is created, gets weekly hours, is renamed and deleted', async ( {
		page,
	} ) => {
		await page.goto( app( '/locations' ) );
		await page.getByRole( 'link', { name: 'Add location' } ).click();
		await page.getByLabel( 'Name', { exact: true } ).fill( 'E2E branch' );
		await page.getByRole( 'button', { name: 'Save', exact: true } ).click();

		// Opened as the stored item, with its weekly hours.
		await expect( page ).toHaveURL( /#\/locations\/\d+$/ );
		await expect(
			page.getByRole( 'heading', { name: 'Weekly hours' } )
		).toBeVisible();
		await page
			.getByRole( 'row', { name: /^Saturday/ } )
			.getByRole( 'button', { name: 'Add hours' } )
			.click();
		await page.getByRole( 'button', { name: 'Copy to every day' } ).first().click();
		await page.getByRole( 'button', { name: 'Save hours' } ).click();
		await expect( page.locator( '.components-snackbar' ).getByText( 'Hours saved.' ).last() ).toBeVisible();

		// A reload reads the hours back from the server.
		await page.reload();
		await expect( page.getByLabel( 'From' ) ).toHaveCount( 7 );

		await page.getByLabel( 'Name', { exact: true } ).fill( 'E2E branch 2' );
		await Promise.all( [
			page.waitForResponse(
				( response ) =>
					response.request().method() === 'PUT' &&
					/\/locations\/\d+/.test( response.url() ) &&
					response.ok()
			),
			page.getByRole( 'button', { name: 'Save', exact: true } ).click(),
		] );

		await page.goto( app( '/locations' ) );
		const row = page.getByRole( 'row', { name: /E2E branch 2/ } );
		await row.getByRole( 'button', { name: 'Delete' } ).click();
		await page
			.getByRole( 'dialog' )
			.getByRole( 'button', { name: 'Delete' } )
			.click();
		await expect( row ).toHaveCount( 0 );
	} );

	test( 'a staff member gets hours and time off, and serves a new service with an add-on', async ( {
		page,
	} ) => {
		await page.goto( app( '/staff/new' ) );
		await page.getByLabel( 'Name', { exact: true } ).fill( 'E2E Karimi' );
		await page.getByRole( 'button', { name: 'Save', exact: true } ).click();
		await expect( page ).toHaveURL( /#\/staff\/\d+$/ );

		await page
			.getByRole( 'row', { name: /^Sunday/ } )
			.getByRole( 'button', { name: 'Add hours' } )
			.click();
		await page.getByRole( 'button', { name: 'Save hours' } ).click();
		await expect( page.locator( '.components-snackbar' ).getByText( 'Hours saved.' ).last() ).toBeVisible();

		const timeOff = page.locator( 'section', {
			has: page.getByRole( 'heading', { name: 'Time off and extra hours' } ),
		} );
		await timeOff.getByLabel( 'Note' ).fill( 'Leave' );
		await timeOff.getByRole( 'button', { name: 'Add', exact: true } ).click();
		await expect( timeOff.getByRole( 'listitem' ) ).toContainText( 'Leave' );

		await page.goto( app( '/services/new' ) );
		await page.getByLabel( 'Name', { exact: true } ).fill( 'E2E checkup' );
		await page.getByLabel( 'Price (IRR)' ).fill( '1500000' );
		await page.getByLabel( 'E2E Karimi' ).check();
		await page.getByRole( 'button', { name: 'Save', exact: true } ).click();
		await expect( page ).toHaveURL( /#\/services\/\d+$/ );

		await page.getByLabel( 'Add-on', { exact: true } ).fill( 'E2E x-ray' );
		await page.getByRole( 'button', { name: 'Add', exact: true } ).click();
		await expect( page.locator( '.components-snackbar' ).getByText( 'Added.' ).last() ).toBeVisible();

		// Its own cancellation policy, over the global one. The default
		// tiers already filled in are enough; no need to edit them here.
		const cancellation = page.getByRole( 'group', { name: 'Cancellation' } );
		await expect( cancellation ).toContainText( 'the global policy applies' );
		await cancellation.getByRole( 'button', { name: 'Save', exact: true } ).click();
		await expect( page.locator( '.components-snackbar' ).getByText( 'Saved.' ).last() ).toBeVisible();
		await expect( cancellation ).not.toContainText( 'the global policy applies' );

		// Read back from the server.
		await page.reload();
		await expect( page.getByLabel( 'E2E Karimi' ) ).toBeChecked();
		await expect( page.getByLabel( 'Add-on', { exact: true } ).first() ).toHaveValue(
			'E2E x-ray'
		);
		await expect(
			page
				.getByRole( 'group', { name: 'Cancellation' } )
				.getByLabel( 'Cancellation deadline (hours before the start)' )
		).toHaveValue( '24' );

		await cancellation.getByRole( 'button', { name: 'Clear' } ).click();
		await expect( cancellation ).toContainText( 'the global policy applies' );

		for ( const [ route, name ] of [
			[ '/services', 'E2E checkup' ],
			[ '/staff', 'E2E Karimi' ],
		] ) {
			await page.goto( app( route ) );
			const row = page.getByRole( 'row', { name: new RegExp( name ) } );
			await row.getByRole( 'button', { name: 'Delete' } ).click();
			await page
				.getByRole( 'dialog' )
				.getByRole( 'button', { name: 'Delete' } )
				.click();
			await expect( row ).toHaveCount( 0 );
		}
	} );

	test( 'a resource is created and listed', async ( { page } ) => {
		await page.goto( app( '/resources/new' ) );
		await page.getByLabel( 'Name', { exact: true } ).fill( 'E2E room' );
		await page.getByLabel( 'Group', { exact: true } ).fill( 'room' );
		await page.getByRole( 'button', { name: 'Save', exact: true } ).click();
		await expect( page ).toHaveURL( /#\/resources\/\d+$/ );

		await page.goto( app( '/resources' ) );
		const row = page.getByRole( 'row', { name: /E2E room/ } );
		await expect( row ).toContainText( 'room' );
		await row.getByRole( 'button', { name: 'Delete' } ).click();
		await page
			.getByRole( 'dialog' )
			.getByRole( 'button', { name: 'Delete' } )
			.click();
		await expect( row ).toHaveCount( 0 );
	} );

	test( 'the global cancellation and reschedule policy is saved and cleared', async ( {
		page,
	} ) => {
		await page.goto( app( '/settings' ) );
		const cancellation = page.getByRole( 'group', { name: 'Cancellation' } );
		await expect( cancellation ).toContainText(
			'cancelling and rescheduling are free until the start, fully refunded'
		);

		await cancellation.getByRole( 'button', { name: 'Save', exact: true } ).click();
		await expect( page.locator( '.components-snackbar' ).getByText( 'Saved.' ).last() ).toBeVisible();
		await expect( cancellation ).not.toContainText(
			'cancelling and rescheduling are free until the start, fully refunded'
		);

		await page.reload();
		await expect(
			cancellation.getByLabel(
				'Cancellation deadline (hours before the start)'
			)
		).toHaveValue( '24' );

		await cancellation.getByRole( 'button', { name: 'Clear' } ).click();
		await expect( cancellation ).toContainText(
			'cancelling and rescheduling are free until the start, fully refunded'
		);
	} );
} );
