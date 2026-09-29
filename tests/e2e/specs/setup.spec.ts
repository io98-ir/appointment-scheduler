import { expect, test } from '@wordpress/e2e-test-utils-playwright';

import identity from '../../../identity.json';

/**
 * T6.1: a fresh install walks through the setup wizard, with the build the
 * plugin ships and the real REST API. The test starts from "not set up" and
 * removes what it made afterwards.
 */
const app = ( route: string ) =>
	`/wp-admin/admin.php?page=${ identity.slug }#${ route }`;

const path = ( route: string ) => `/${ identity.rest_namespace }${ route }`;

test.describe( 'setup wizard', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.rest( {
			method: 'PUT',
			path: path( '/onboarding' ),
			data: { done: false },
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.rest( {
			method: 'PUT',
			path: path( '/brand' ),
			data: { name: '', logo_url: '', color: '' },
		} );
		await requestUtils.rest( {
			method: 'PUT',
			path: path( '/onboarding' ),
			data: { done: true },
		} );
		for ( const base of [ '/services', '/staff', '/locations' ] ) {
			const items = await requestUtils.rest<
				Array< { id: number; name: string } >
			>( {
				path: path( base ),
				params: { per_page: 100 },
			} );
			for ( const item of items.filter( ( { name } ) =>
				name.startsWith( 'E2E wizard' )
			) ) {
				// 204 has no body, which requestUtils.rest() fails to parse.
				await requestUtils
					.rest( {
						method: 'DELETE',
						path: path( `${ base }/${ item.id }` ),
					} )
					.catch( ( error: unknown ) => {
						if ( ! ( error instanceof SyntaxError ) ) {
							throw error;
						}
					} );
			}
		}
	} );

	test( 'a new site is led from the dashboard through every step and the prompt goes away', async ( {
		page,
	} ) => {
		await page.goto( app( '/' ) );
		await page
			.getByRole( 'link', { name: 'Open the setup wizard' } )
			.click();
		await expect( page ).toHaveURL( /#\/setup$/ );

		// Brand.
		await page.getByLabel( 'Brand name' ).fill( 'E2E wizard salon' );
		await page.getByLabel( 'Accent colour' ).fill( '#aa3300' );
		await page.getByRole( 'button', { name: 'Save look' } ).click();

		// Location and hours.
		await expect(
			page.getByRole( 'heading', { name: 'Location and hours' } )
		).toBeVisible();
		await page.getByLabel( 'Location name' ).fill( 'E2E wizard branch' );
		await page.getByRole( 'button', { name: 'Create location' } ).click();
		await page.getByRole( 'button', { name: 'Next' } ).click();

		// First service, with one staff member.
		await expect(
			page.getByRole( 'heading', { name: 'First service' } )
		).toBeVisible();
		await page.getByLabel( 'Staff member name' ).fill( 'E2E wizard staff' );
		await page.getByLabel( 'Service name' ).fill( 'E2E wizard service' );
		await page.getByRole( 'button', { name: 'Create service' } ).click();
		await page.getByRole( 'button', { name: 'Next' } ).click();

		// SMS and payments are optional.
		await expect(
			page.getByRole( 'heading', { name: 'SMS' } )
		).toBeVisible();
		await page.getByRole( 'button', { name: 'Skip this step' } ).click();
		await expect(
			page.getByRole( 'heading', { name: 'Online payment' } )
		).toBeVisible();
		await page.getByRole( 'button', { name: 'Skip this step' } ).click();

		// Finish.
		await expect(
			page.getByText( 'The service can be booked now.' )
		).toBeVisible();
		await expect(
			page.getByText( `[${ identity.slug }_booking]` )
		).toBeVisible();
		await page
			.getByRole( 'button', { name: 'Go to the dashboard' } )
			.click();
		await expect( page ).toHaveURL( /#\/$/ );
		await expect(
			page.getByText( 'Your booking site is not set up yet.' )
		).toHaveCount( 0 );

		// What the wizard made is really there: the brand name leads the header
		// and the service exists.
		await page.reload();
		await expect( page.locator( '.vqy-admin__brand' ) ).toHaveText(
			'E2E wizard salon'
		);
		await page.goto( app( '/services' ) );
		await expect( page.getByText( 'E2E wizard service' ) ).toBeVisible();
	} );
} );
