import { expect, test } from '@wordpress/e2e-test-utils-playwright';

import identity from '../../../identity.json';

/**
 * T3.5 and T3.6: the settings sections (custom fields, coupons, time-based
 * prices), the holidays screen, the dashboard and the reports, against the
 * real REST API. Each test cleans up what it made, through the same screens.
 */
const app = ( route: string ) =>
	`/wp-admin/admin.php?page=${ identity.slug }#${ route }`;

const snackbar = ( page: import( '@playwright/test' ).Page, text: string ) =>
	page.locator( '.components-snackbar' ).getByText( text ).last();

test.describe( 'settings', () => {
	test( 'a global custom field is added, kept after a reload and deleted', async ( {
		page,
	} ) => {
		await page.goto( app( '/settings' ) );
		await page.getByLabel( 'Key', { exact: true } ).fill( 'e2e_allergies' );
		await page.getByLabel( 'Label', { exact: true } ).fill( 'E2E allergies' );
		await page.getByRole( 'button', { name: 'Add field' } ).click();
		await expect( snackbar( page, 'Added.' ) ).toBeVisible();

		await page.reload();
		const item = page.getByRole( 'listitem' ).filter( { hasText: 'e2e_allergies' } );
		await expect( item ).toContainText( 'E2E allergies' );

		await item.getByRole( 'button', { name: 'Delete' } ).click();
		await expect( item ).toHaveCount( 0 );
	} );

	test( 'a coupon is added, kept after a reload and deleted', async ( { page } ) => {
		await page.goto( app( '/settings' ) );
		await page.getByLabel( 'Coupon code' ).fill( 'E2E15' );
		await page.getByLabel( 'Discount value' ).fill( '15' );
		await page.getByRole( 'button', { name: 'Add coupon' } ).click();
		await expect( snackbar( page, 'Added.' ) ).toBeVisible();

		await page.reload();
		const item = page.getByRole( 'listitem' ).filter( { hasText: 'E2E15' } );
		await expect( item ).toContainText( '15%' );

		await item.getByRole( 'button', { name: 'Delete' } ).click();
		await expect( item ).toHaveCount( 0 );
	} );

	test( 'a time-based price is added, kept after a reload and deleted', async ( {
		page,
	} ) => {
		await page.goto( app( '/settings' ) );
		await page.getByLabel( 'Friday' ).check();
		await page
			.getByLabel( 'Price change (%, negative for a discount)' )
			.fill( '25' );
		await page.getByRole( 'button', { name: 'Add time-based price' } ).click();
		await expect( snackbar( page, 'Added.' ) ).toBeVisible();

		await page.reload();
		const item = page.getByRole( 'listitem' ).filter( { hasText: '+25%' } );
		await expect( item ).toContainText( 'Friday' );

		await item.getByRole( 'button', { name: 'Delete' } ).click();
		await expect( item ).toHaveCount( 0 );
	} );

	test( 'a holiday is added and deleted', async ( { page } ) => {
		await page.goto( app( '/holidays' ) );
		await page.getByLabel( 'Title' ).fill( 'E2E holiday' );
		await page.getByRole( 'button', { name: 'Add', exact: true } ).click();
		await expect( snackbar( page, 'Saved.' ) ).toBeVisible();

		const item = page.getByRole( 'listitem' ).filter( { hasText: 'E2E holiday' } );
		await expect( item ).toHaveCount( 1 );
		await item.getByRole( 'button', { name: 'Delete' } ).click();
		await expect( item ).toHaveCount( 0 );
	} );
} );

test.describe( 'dashboard and reports', () => {
	test( 'the dashboard shows the figures', async ( { page } ) => {
		await page.goto( app( '/' ) );

		await expect( page.getByRole( 'heading', { name: 'Dashboard', level: 1 } ) ).toBeVisible();
		await expect( page.getByText( 'Cancellation rate (30 days)' ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: 'Still to come today' } ) ).toBeVisible();
	} );

	test( 'the report reads and exports a CSV', async ( { page } ) => {
		await page.goto( app( '/reports' ) );

		await expect( page.getByRole( 'heading', { name: 'Summary' } ) ).toBeVisible();

		const [ download ] = await Promise.all( [
			page.waitForEvent( 'download' ),
			page.getByRole( 'button', { name: 'Export appointments (CSV)' } ).click(),
		] );
		expect( download.suggestedFilename() ).toMatch( /^appointments-\d{4}-\d{2}-\d{2}-\d{4}-\d{2}-\d{2}\.csv$/ );
	} );
} );
