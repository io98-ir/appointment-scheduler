// @vitest-environment jsdom
import { ApiClient } from '@vaqtyar/shared';
import { render } from 'preact';
import { act } from 'preact/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';

import {
	SLOT_EVENT,
	Widget,
	type SlotChoice,
	type WidgetConfig,
} from './Widget';

const MENU = {
	locations: [
		{ id: 1, name: 'Main', timezone: 'Asia/Tehran', address: '' },
	],
	categories: [],
	services: [
		{
			id: 10,
			name: 'Haircut',
			category_id: null,
			description: '',
			capacity: 1,
			variants: [
				{
					id: 100,
					label: '30 min',
					duration_min: 30,
					price: { amount: 1000000, currency: 'IRR' },
					is_default: true,
				},
				{
					id: 101,
					label: '60 min',
					duration_min: 60,
					price: { amount: 1800000, currency: 'IRR' },
					is_default: false,
				},
			],
			staff: [
				{
					staff_id: 2,
					name: 'Sara',
					title: '',
					location_id: null,
					variant_id: null,
					duration_min: null,
					price: null,
				},
				{
					staff_id: 3,
					name: 'Ali',
					title: '',
					location_id: null,
					variant_id: 101,
					duration_min: null,
					price: null,
				},
			],
		},
	],
};

function fakeServer() {
	const requests: URLSearchParams[] = [];
	const json = ( body: unknown ) => new Response( JSON.stringify( body ) );

	const options = { required: false };
	const posts: { path: string; body: Record< string, unknown > }[] = [];
	const fetch = async ( resource: RequestInfo | URL, init?: RequestInit ) => {
		const url = new URL( String( resource ) );
		const path = url.pathname.replace( '/wp-json/x/v1', '' );
		if ( init?.method === 'POST' ) {
			const body = JSON.parse( String( init.body ) ) as Record<
				string,
				unknown
			>;
			posts.push( { path, body } );
			if ( path === '/otp/request' ) {
				return new Response(
					JSON.stringify( { expires_in: 300, resend_after: 60 } ),
					{ status: 202 }
				);
			}
			if ( path === '/otp/verify' ) {
				return json( {
					token: 'SESSION',
					expires_at: '2027-01-01T00:00:00Z',
				} );
			}
			if ( path === '/holds' ) {
				return new Response(
					JSON.stringify( {
						token: 'T'.repeat( 43 ),
						expires_at: new Date(
							Date.now() + 600000
						).toISOString(),
						staff_id: 2,
						start: body.start,
						end: body.start,
						price: {
							total: { amount: 900000, currency: 'IRR' },
							lines: [
								{
									code: 'base',
									amount: {
										amount: 1000000,
										currency: 'IRR',
									},
									ref: null,
									qty: 1,
								},
								{
									code: 'coupon',
									amount: {
										amount: -100000,
										currency: 'IRR',
									},
									ref: 4,
									qty: 1,
								},
							],
						},
					} ),
					{ status: 201 }
				);
			}

			return new Response(
				JSON.stringify( {
					code: 'AB12CD34',
					status: 'confirmed',
					start: '2027-01-10T10:00:00+03:30',
					end: '2027-01-10T10:30:00+03:30',
					price: {
						total: { amount: 900000, currency: 'IRR' },
						lines: [],
					},
				} ),
				{ status: 201 }
			);
		}
		if ( path === '/nonce' ) {
			return json( { nonce: 'fresh-nonce' } );
		}
		if ( path === '/otp/config' ) {
			return json( { required: options.required, code_length: 6 } );
		}
		if ( path === '/captcha' ) {
			return json( { a: 3, b: 4, token: 'captcha-token' } );
		}
		if ( path === '/service-fields' ) {
			return json( [
				{
					field_key: 'has_car',
					type: 'checkbox',
					label: 'Has a car?',
					required: false,
					options: [],
					show_if: null,
				},
				{
					field_key: 'plate',
					type: 'text',
					label: 'Plate number',
					required: true,
					options: [],
					show_if: { field: 'has_car', equals: '1' },
				},
			] );
		}
		if ( path === '/catalog' ) {
			return json( MENU );
		}
		requests.push( url.searchParams );
		const view = url.searchParams.get( 'view' );
		const date = url.searchParams.get( 'date' ) ?? '';
		if ( view === 'month' ) {
			const start = new Date( date + 'T00:00:00Z' );
			const days = Number( url.searchParams.get( 'days' ) );

			return json( {
				timezone: 'Asia/Tehran',
				days: Array.from( { length: days }, ( _, i ) => ( {
					date: new Date( start.getTime() + i * 86400000 )
						.toISOString()
						.slice( 0, 10 ),
					status: i === 2 ? 'available' : 'closed',
				} ) ),
			} );
		}
		if ( view === 'first' ) {
			return json( {
				timezone: 'Asia/Tehran',
				date: '2027-01-10',
				slots: [],
			} );
		}

		return json( {
			timezone: 'Asia/Tehran',
			date,
			status: 'available',
			slots: [
				{
					start: `${ date }T10:00:00+03:30`,
					end: `${ date }T10:30:00+03:30`,
					staff_ids: [ 2 ],
					seats_left: 1,
				},
			],
		} );
	};

	return { requests, posts, options, fetch };
}

async function settle() {
	for ( let i = 0; i < 5; i++ ) {
		await act( async () => {
			await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		} );
	}
}

describe( 'the booking widget', () => {
	let container: HTMLElement;
	let server: ReturnType< typeof fakeServer >;

	async function open( config: WidgetConfig = { restUrl: 'x' } ) {
		const api = new ApiClient( {
			baseUrl: 'https://example.test/wp-json/x/v1/',
			fetch: server.fetch,
		} );
		await act( () => {
			render( <Widget config={ config } api={ api } />, container );
		} );
		await settle();
	}

	beforeEach( () => {
		server = fakeServer();
		container = document.createElement( 'div' );
		document.body.append( container );
	} );

	afterEach( () => {
		render( null, container );
		container.remove();
	} );

	const days = () => [
		...container.querySelectorAll< HTMLButtonElement >(
			'.vqy-widget__day'
		),
	];
	const text = () => container.textContent ?? '';

	it( 'picks the only service, its default variant and location, and colours the free days', async () => {
		await open();

		expect( container.querySelector( 'select' ) ).not.toBeNull();
		expect( text() ).toContain( 'Haircut' );
		const free = days().filter( ( day ) => ! day.disabled );
		expect( free ).toHaveLength( 1 );
		expect( free[ 0 ]?.className ).toContain(
			'vqy-widget__day--available'
		);
		const [ month ] = server.requests;
		expect( month?.get( 'variant' ) ).toBe( '100' );
		expect( month?.get( 'location' ) ).toBe( '1' );
		expect( month?.get( 'view' ) ).toBe( 'month' );
	} );

	it( 'shows the free starts of a day and fires an event when one is chosen', async () => {
		await open();
		const events: SlotChoice[] = [];
		container.addEventListener( SLOT_EVENT, ( event ) =>
			events.push( ( event as CustomEvent< SlotChoice > ).detail )
		);

		await act( () =>
			days()
				.find( ( day ) => ! day.disabled )
				?.click()
		);
		await settle();
		const slot = container.querySelector< HTMLButtonElement >(
			'.vqy-widget__slot-list button'
		);
		expect( slot?.textContent ).toBe( '10:00' );

		await act( () => slot?.click() );

		expect( events ).toHaveLength( 1 );
		expect( events[ 0 ] ).toMatchObject( {
			service: 10,
			variant: 100,
			location: 1,
			staff: null,
		} );
		expect( events[ 0 ]?.slot.staff_ids ).toEqual( [ 2 ] );
		expect( text() ).toContain( 'Selected:' );
	} );

	it( 'offers only the staff who serve the chosen variant, and asks for the chosen one', async () => {
		await open();
		const staffSelect = () =>
			[ ...container.querySelectorAll( 'select' ) ].find( ( select ) =>
				select.textContent?.includes( 'Any available' )
			);
		expect( staffSelect() ).toBeUndefined();

		const variant = [ ...container.querySelectorAll( 'select' ) ].find(
			( select ) => select.textContent?.includes( '60 min' )
		);
		await act( () => {
			if ( variant ) {
				variant.value = '101';
				variant.dispatchEvent(
					new Event( 'change', { bubbles: true } )
				);
			}
		} );
		await settle();
		expect( staffSelect()?.textContent ).toContain( 'Ali' );

		await act( () => {
			const select = staffSelect();
			if ( select ) {
				select.value = '3';
				select.dispatchEvent(
					new Event( 'change', { bubbles: true } )
				);
			}
		} );
		await settle();

		const last = server.requests.at( -1 );
		expect( last?.get( 'variant' ) ).toBe( '101' );
		expect( last?.get( 'staff' ) ).toBe( '3' );
	} );

	it( 'jumps to the first free day', async () => {
		await open();
		const button = [ ...container.querySelectorAll( 'button' ) ].find(
			( element ) => element.textContent === 'First available'
		);

		await act( () => button?.click() );
		await settle();

		expect(
			server.requests.some( ( r ) => r.get( 'view' ) === 'first' )
		).toBe( true );
		const day = server.requests
			.filter( ( r ) => r.get( 'view' ) === 'day' )
			.at( -1 );
		expect( day?.get( 'date' ) ).toBe( '2027-01-10' );
	} );

	it( 'holds the time, asks for the details and books', async () => {
		await open();
		await act( () =>
			days()
				.find( ( day ) => ! day.disabled )
				?.click()
		);
		await settle();
		await act( () =>
			container
				.querySelector< HTMLButtonElement >(
					'.vqy-widget__slot-list button'
				)
				?.click()
		);
		const coupon = container.querySelector< HTMLInputElement >(
			'input[maxlength="64"]'
		);
		await act( () => {
			if ( coupon ) {
				coupon.value = 'NOWRUZ';
				coupon.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			}
		} );
		const next = [ ...container.querySelectorAll( 'button' ) ].find(
			( element ) => element.textContent === 'Continue'
		);
		await act( () => next?.click() );
		await settle();

		const hold = server.posts.find( ( post ) => post.path === '/holds' );
		expect( hold?.body ).toMatchObject( {
			variant: 100,
			location: 1,
			coupon: 'NOWRUZ',
		} );
		expect( text() ).toContain( 'We are holding this time for you' );
		expect( text() ).toContain( 'Total: 900,000 IRR' );
		// A field with a show_if stays hidden until its condition holds.
		expect( text() ).not.toContain( 'Plate number' );

		const field = ( label: string ) =>
			[ ...container.querySelectorAll( 'label' ) ]
				.find( ( element ) => element.textContent?.startsWith( label ) )
				?.querySelector< HTMLInputElement >( 'input, textarea' );
		const type = async (
			element: HTMLInputElement | null | undefined,
			value: string
		) => {
			await act( () => {
				if ( element ) {
					element.value = value;
					element.dispatchEvent(
						new Event( 'input', { bubbles: true } )
					);
				}
			} );
		};
		await type( field( 'First name' ), 'Sara' );
		await type( field( 'Mobile number' ), '09351112233' );
		await act( () => field( 'Has a car?' )?.click() );
		expect( text() ).toContain( 'Plate number' );
		await type( field( 'Plate number' ), '12A345' );
		await act( () => {
			container
				.querySelector( 'form' )
				?.dispatchEvent(
					new Event( 'submit', { bubbles: true, cancelable: true } )
				);
		} );
		await settle();

		const booked = server.posts.find( ( post ) => post.path === '/book' );
		expect( booked?.body ).toMatchObject( {
			hold_token: 'T'.repeat( 43 ),
			first_name: 'Sara',
			phone: '09351112233',
			email: null,
			answers: { has_car: true, plate: '12A345' },
		} );
		expect( text() ).toContain( 'Your appointment is booked.' );
		expect( text() ).toContain( 'Tracking code: AB12CD34' );
	} );

	it( 'verifies the phone with a captcha and a code before booking, when the site asks for it', async () => {
		server.options.required = true;
		await open();
		await act( () =>
			days()
				.find( ( day ) => ! day.disabled )
				?.click()
		);
		await settle();
		await act( () =>
			container
				.querySelector< HTMLButtonElement >(
					'.vqy-widget__slot-list button'
				)
				?.click()
		);
		const button = ( label: string ) =>
			[ ...container.querySelectorAll( 'button' ) ].find(
				( element ) => element.textContent === label
			);
		const field = ( label: string ) =>
			[ ...container.querySelectorAll( 'label' ) ]
				.find( ( element ) => element.textContent?.startsWith( label ) )
				?.querySelector< HTMLInputElement >( 'input' );
		const type = async (
			element: HTMLInputElement | null | undefined,
			value: string
		) => {
			await act( () => {
				if ( element ) {
					element.value = value;
					element.dispatchEvent(
						new Event( 'input', { bubbles: true } )
					);
				}
			} );
		};
		await act( () => button( 'Continue' )?.click() );
		await settle();

		const confirm = button( 'Confirm booking' ) as HTMLButtonElement;
		expect( confirm.disabled ).toBe( true );
		await type( field( 'First name' ), 'Sara' );
		await type( field( 'Mobile number' ), '09351112233' );
		await act( () => button( 'Send a verification code' )?.click() );
		await settle();
		expect( text() ).toContain( 'What is 3 + 4?' );

		await type( field( 'What is' ), '7' );
		await act( () => button( 'Send code' )?.click() );
		await settle();
		expect(
			server.posts.find( ( post ) => post.path === '/otp/request' )?.body
		).toEqual( {
			phone: '09351112233',
			captcha_token: 'captcha-token',
			captcha_answer: '7',
		} );

		await type( field( 'Code we sent you' ), '123456' );
		await act( () => button( 'Verify' )?.click() );
		await settle();
		expect( text() ).toContain( 'Your phone number is verified.' );
		expect(
			( button( 'Confirm booking' ) as HTMLButtonElement ).disabled
		).toBe( false );

		await act( () => {
			container
				.querySelector( 'form' )
				?.dispatchEvent(
					new Event( 'submit', { bubbles: true, cancelable: true } )
				);
		} );
		await settle();
		expect(
			server.posts.find( ( post ) => post.path === '/book' )?.body
		).toMatchObject( { session_token: 'SESSION', phone: '09351112233' } );
		expect( text() ).toContain( 'Your appointment is booked.' );
	} );

	it( 'says so when it is not configured', async () => {
		render( <Widget config={ {} } />, container );

		expect( text() ).toContain( 'not configured' );
	} );
} );
