import { ApiClient, ApiError } from '@vaqtyar/shared';
import { describe, expect, it, vi } from 'vitest';

import { sectionOf } from '../App';
import { act, actionsFor } from './actions';
import {
	detailOf,
	filtersOf,
	NO_FILTERS,
	PER_PAGE,
	queryOf,
	routeOf,
} from './filters';

describe( 'appointment list filters', () => {
	it( 'round-trips through the route and drops defaults', () => {
		const filters = {
			...NO_FILTERS,
			status: 'confirmed' as const,
			staff: 3,
			from: '2026-10-01',
			search: 'علی',
			page: 2,
		};

		expect( routeOf( NO_FILTERS ) ).toBe( '/appointments' );
		expect( filtersOf( routeOf( filters ) ) ).toEqual( filters );
	} );

	it( 'ignores what it does not know', () => {
		expect(
			filtersOf(
				'/appointments?status=lost&staff=-1&from=01-10-2026&page=0'
			)
		).toEqual( NO_FILTERS );
	} );

	it( 'asks the API for one page, without empty filters', () => {
		expect( queryOf( { ...NO_FILTERS, search: '  ' } ) ).toEqual( {
			page: 1,
			per_page: PER_PAGE,
			status: undefined,
			staff: undefined,
			from: undefined,
			to: undefined,
			search: undefined,
		} );
	} );

	it( 'tells a detail route from the list', () => {
		expect( detailOf( '/appointments/42' ) ).toBe( 42 );
		expect( detailOf( '/appointments?page=2' ) ).toBeNull();
		expect( sectionOf( '/appointments?page=2' )?.path ).toBe(
			'/appointments'
		);
	} );
} );

describe( 'appointment actions', () => {
	it( 'offers what the status allows', () => {
		expect( actionsFor( 'pending_approval' ) ).toEqual( [
			'approve',
			'cancel',
		] );
		expect( actionsFor( 'confirmed' ) ).toEqual( [
			'complete',
			'no-show',
			'cancel',
		] );
		expect( actionsFor( 'cancelled' ) ).toEqual( [] );
	} );

	it( 'returns a policy refusal and throws anything else', async () => {
		const api = new ApiClient( {
			baseUrl: 'https://example.test/wp-json',
		} );
		const post = vi.spyOn( api, 'post' );

		post.mockRejectedValueOnce(
			new ApiError( 'policy.cancel_window_passed', 'Too late.', 409 )
		);
		await expect(
			act( api, 7, 'cancel', { reason: 'x' } )
		).resolves.toEqual( { done: false, refusal: 'Too late.' } );

		post.mockResolvedValueOnce( {} );
		await expect( act( api, 7, 'approve' ) ).resolves.toEqual( {
			done: true,
		} );
		expect( post ).toHaveBeenLastCalledWith(
			'/appointments/7/approve',
			{}
		);

		post.mockRejectedValueOnce( new ApiError( 'not_started', 'No.', 409 ) );
		await expect( act( api, 7, 'complete' ) ).rejects.toThrow( 'No.' );
	} );
} );
