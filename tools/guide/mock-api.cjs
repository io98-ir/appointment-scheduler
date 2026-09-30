/**
 * A made-up business for the user guide's screenshots: the REST answers the
 * admin app and the widget ask for, in the shapes of docs/api.md, with
 * Persian names and dates relative to today. Nothing here is real data.
 */
const ZONE_OFFSET_MIN = 210; // Asia/Tehran, UTC+03:30, no daylight saving since 2023.

function tehranToday() {
	return new Date( Date.now() + ZONE_OFFSET_MIN * 60000 );
}

function pad( value ) {
	return String( value ).padStart( 2, '0' );
}

/** "YYYY-MM-DD" of the local day `offset` days from today. */
function day( offset = 0 ) {
	const date = tehranToday();
	date.setUTCDate( date.getUTCDate() + offset );

	return `${ date.getUTCFullYear() }-${ pad( date.getUTCMonth() + 1 ) }-${ pad( date.getUTCDate() ) }`;
}

function at( offset, hour, minute = 0 ) {
	return `${ day( offset ) }T${ pad( hour ) }:${ pad( minute ) }:00+03:30`;
}

const money = ( amount ) => ( { amount, currency: 'IRR' } );

const locations = [
	{
		id: 1,
		name: 'شعبه مرکزی',
		timezone: 'Asia/Tehran',
		address: 'تهران، خیابان ولیعصر، پلاک ۱۲',
		phone: '+982112345678',
		holiday_calendar: 'ir',
		status: 'active',
		sort: 0,
	},
];

const staff = [
	{ id: 1, name: 'دکتر سارا احمدی', color: '#0e7184', wp_user_id: null, location_id: 1, title: 'پزشک عمومی', email: null, phone: null, avatar_id: null, bio: '', status: 'active', sort: 0 },
	{ id: 2, name: 'علی رضایی', color: '#7c3aed', wp_user_id: null, location_id: 1, title: 'ماساژدرمانگر', email: null, phone: null, avatar_id: null, bio: '', status: 'active', sort: 1 },
	{ id: 3, name: 'مریم کریمی', color: '#db2777', wp_user_id: null, location_id: 1, title: 'پرستار', email: null, phone: null, avatar_id: null, bio: '', status: 'active', sort: 2 },
];

const categories = [
	{ id: 1, name: 'درمان', color: '#0e7184', sort: 0 },
	{ id: 2, name: 'آرامش', color: '#7c3aed', sort: 1 },
];

const variant = ( id, label, minutes, price, isDefault, buffer = 0 ) => ( {
	id,
	label,
	duration_min: minutes,
	price: money( price ),
	is_default: isDefault,
	buffer_before_min: 0,
	buffer_after_min: buffer,
	slot_step_min: null,
	sort: id,
} );

const services = [
	{
		id: 1,
		name: 'ویزیت عمومی',
		category_id: 1,
		description: 'معاینه و مشاوره پزشکی',
		image_id: null,
		capacity: 1,
		status: 'active',
		sort: 0,
		variants: [ variant( 1, 'ویزیت کوتاه', 30, 3000000, true, 5 ), variant( 2, 'ویزیت کامل', 60, 5000000, false, 5 ) ],
		staff: [
			{ staff_id: 1, variant_id: null, price: null, duration_min: null },
			{ staff_id: 3, variant_id: null, price: null, duration_min: null },
		],
		resources: [],
	},
	{
		id: 2,
		name: 'ماساژ ریلکسی',
		category_id: 2,
		description: 'ماساژ آرامش‌بخش با روغن معطر',
		image_id: null,
		capacity: 1,
		status: 'active',
		sort: 1,
		variants: [ variant( 3, '۴۵ دقیقه', 45, 4500000, true, 10 ), variant( 4, '۹۰ دقیقه', 90, 8000000, false, 10 ) ],
		staff: [ { staff_id: 2, variant_id: null, price: null, duration_min: null } ],
		resources: [ { group_key: 'room', quantity: 1 } ],
	},
	{
		id: 3,
		name: 'مشاوره تلفنی',
		category_id: 1,
		description: 'پیگیری نتیجه آزمایش و مشاوره',
		image_id: null,
		capacity: 1,
		status: 'active',
		sort: 2,
		variants: [ variant( 5, '۲۰ دقیقه', 20, 1500000, true ) ],
		staff: [ { staff_id: 1, variant_id: null, price: null, duration_min: null } ],
		resources: [],
	},
];

const resources = [
	{ id: 1, name: 'اتاق ۱', group_key: 'room', location_id: 1, capacity: 1, status: 'active' },
	{ id: 2, name: 'اتاق ۲', group_key: 'room', location_id: 1, capacity: 1, status: 'active' },
];

const extras = [
	{ id: 1, name: 'روغن معطر', price: money( 500000 ), duration_min: 0, service_id: 2, max_qty: 1, status: 'active' },
];

const people = [
	[ 'نیما', 'مهدی‌زاده', '+989121110001' ],
	[ 'زهرا', 'محمدی', '+989121110002' ],
	[ 'رضا', 'کاظمی', '+989121110003' ],
	[ 'مینا', 'صادقی', '+989121110004' ],
	[ 'امیرحسین', 'نوری', '+989121110005' ],
	[ 'سحر', 'حیدری', '+989121110006' ],
	[ 'بهرام', 'فرهادی', '+989121110007' ],
	[ 'لیلا', 'رحیمی', '+989121110008' ],
];

const customers = people.map( ( [ first, last, phone ], index ) => ( {
	id: index + 1,
	uuid: `01J0000000000000000000000${ index }`,
	first_name: first,
	last_name: last,
	phone,
	email: index % 2 === 0 ? `user${ index + 1 }@example.com` : null,
	wp_user_id: null,
	birth_date: null,
	note: '',
	tags: index === 0 ? [ 'vip' ] : [],
	status: 'active',
} ) );

// [day offset, hour, minute, service, variant, staff, customer, status, paid]
const plan = [
	[ 0, 9, 0, 1, 1, 1, 1, 'confirmed', 'paid' ],
	[ 0, 10, 0, 1, 2, 1, 2, 'confirmed', 'unpaid' ],
	[ 0, 11, 30, 3, 5, 1, 3, 'pending_approval', 'unpaid' ],
	[ 0, 9, 30, 2, 3, 2, 4, 'confirmed', 'paid' ],
	[ 0, 14, 0, 2, 4, 2, 5, 'confirmed', 'deposit_paid' ],
	[ 0, 10, 30, 1, 1, 3, 6, 'confirmed', 'unpaid' ],
	[ 1, 9, 0, 1, 1, 1, 7, 'confirmed', 'paid' ],
	[ 1, 15, 0, 2, 3, 2, 8, 'confirmed', 'paid' ],
	[ -1, 9, 0, 1, 1, 1, 1, 'completed', 'paid' ],
	[ -1, 10, 0, 1, 2, 1, 2, 'no_show', 'unpaid' ],
	[ -2, 16, 0, 2, 3, 2, 3, 'cancelled', 'refunded' ],
	[ 2, 12, 0, 3, 5, 1, 4, 'confirmed', 'unpaid' ],
];

const appointments = plan.map(
	( [ offset, hour, minute, serviceId, variantId, staffId, customerId, status, paid ], index ) => {
		const service = services.find( ( item ) => item.id === serviceId );
		const chosen = service.variants.find( ( item ) => item.id === variantId );
		const end = hour * 60 + minute + chosen.duration_min;
		const customer = customers.find( ( item ) => item.id === customerId );

		return {
			id: index + 1,
			code: `K${ 7 + index }M${ 3 + index }QZ${ index }`.slice( 0, 8 ),
			status,
			payment_status: paid,
			customer_id: customerId,
			customer: {
				id: customerId,
				name: `${ customer.first_name } ${ customer.last_name }`,
				phone: customer.phone,
				deleted: false,
			},
			location_id: 1,
			service_id: serviceId,
			variant_id: variantId,
			staff_id: staffId,
			start: at( offset, hour, minute ),
			end: at( offset, Math.floor( end / 60 ), end % 60 ),
			party_size: 1,
			total: chosen.price,
		};
	}
);

const templates = [
	[ 1, 'booked', 'customer', 'sms', null, '', 'نوبت شما برای {service} در تاریخ {date} ساعت {time} ثبت شد. کد پیگیری: {code}' ],
	[ 2, 'reminder', 'customer', 'sms', 1440, '', 'یادآوری: فردا ساعت {time} نوبت {service} دارید.' ],
	[ 3, 'cancelled', 'customer', 'email', null, 'لغو نوبت', 'نوبت {service} در تاریخ {date} لغو شد.' ],
	[ 4, 'booked', 'staff', 'email', null, 'نوبت تازه', 'نوبت تازه‌ای برای {customer_name} ثبت شد.' ],
].map( ( [ id, trigger, audience, channel, offset, subject, body ] ) => ( {
	id,
	trigger,
	audience,
	channel,
	offset_min: offset,
	subject,
	body,
	enabled: true,
	sms_patterns: {},
} ) );

function summary( from, to ) {
	const days = [];
	for ( let offset = -29; offset <= 0; offset++ ) {
		const weekday = new Date( `${ day( offset ) }T12:00:00Z` ).getUTCDay();
		const count = weekday === 5 ? 0 : 3 + ( ( offset * offset ) % 6 );
		days.push( { date: day( offset ), appointments: count, revenue: count * 3400000 } );
	}
	const total = days.reduce( ( sum, item ) => sum + item.appointments, 0 );

	return {
		from,
		to,
		totals: { appointments: total, revenue: total * 3400000, cancelled: 6, no_show: 3, cancel_rate: 5.1 },
		statuses: { confirmed: total, completed: 41, cancelled: 6, no_show: 3 },
		days,
		services: [
			{ id: 1, appointments: Math.round( total * 0.5 ), revenue: Math.round( total * 0.5 ) * 3500000 },
			{ id: 2, appointments: Math.round( total * 0.35 ), revenue: Math.round( total * 0.35 ) * 5200000 },
			{ id: 3, appointments: Math.round( total * 0.15 ), revenue: Math.round( total * 0.15 ) * 1500000 },
		],
		staff: [
			{ id: 1, appointments: Math.round( total * 0.6 ), revenue: Math.round( total * 0.6 ) * 2800000 },
			{ id: 2, appointments: Math.round( total * 0.3 ), revenue: Math.round( total * 0.3 ) * 5200000 },
			{ id: 3, appointments: Math.round( total * 0.1 ), revenue: Math.round( total * 0.1 ) * 3000000 },
		],
	};
}

function catalog() {
	return {
		locations: locations.map( ( { id, name, timezone, address } ) => ( { id, name, timezone, address } ) ),
		categories: categories.map( ( { id, name } ) => ( { id, name } ) ),
		services: services.map( ( service ) => ( {
			id: service.id,
			name: service.name,
			category_id: service.category_id,
			description: service.description,
			capacity: service.capacity,
			variants: service.variants.map( ( { id, label, duration_min: minutes, price, is_default: isDefault } ) => ( {
				id,
				label,
				duration_min: minutes,
				price,
				is_default: isDefault,
			} ) ),
			staff: service.staff.map( ( assignment ) => {
				const person = staff.find( ( item ) => item.id === assignment.staff_id );

				return {
					staff_id: person.id,
					name: person.name,
					title: person.title,
					location_id: person.location_id,
					variant_id: assignment.variant_id,
					duration_min: assignment.duration_min,
					price: assignment.price,
				};
			} ),
		} ) ),
	};
}

function availabilityDay( date ) {
	const weekday = new Date( `${ date }T12:00:00Z` ).getUTCDay();
	if ( weekday === 5 ) {
		return { timezone: 'Asia/Tehran', date, status: 'closed', slots: [] };
	}
	const slots = [];
	for ( let minutes = 9 * 60; minutes < 17 * 60; minutes += 30 ) {
		// Lunch, and a few starts already taken.
		if ( ( minutes >= 12 * 60 && minutes < 13 * 60 ) || minutes === 10 * 60 + 30 || minutes === 14 * 60 ) {
			continue;
		}
		const start = `${ date }T${ pad( Math.floor( minutes / 60 ) ) }:${ pad( minutes % 60 ) }:00+03:30`;
		const end = minutes + 30;
		slots.push( {
			start,
			end: `${ date }T${ pad( Math.floor( end / 60 ) ) }:${ pad( end % 60 ) }:00+03:30`,
			staff_ids: [ 1 ],
			seats_left: 1,
		} );
	}

	return { timezone: 'Asia/Tehran', date, status: 'available', slots };
}

function availabilityMonth( from ) {
	const days = [];
	const start = new Date( `${ from }T12:00:00Z` );
	for ( let index = 0; index < 62; index++ ) {
		const date = new Date( start );
		date.setUTCDate( start.getUTCDate() + index );
		const iso = date.toISOString().slice( 0, 10 );
		const weekday = date.getUTCDay();
		const past = iso < day( 0 );
		days.push( {
			date: iso,
			status: weekday === 5 || past ? 'closed' : index % 9 === 4 ? 'full' : 'available',
		} );
	}

	return { timezone: 'Asia/Tehran', days };
}

const holidays = [
	[ '2027-02-11', 'پیروزی انقلاب اسلامی' ],
	[ '2027-03-20', 'روز ملی شدن صنعت نفت ایران' ],
	[ '2027-03-21', 'عید نوروز' ],
	[ '2027-03-22', 'عید نوروز' ],
	[ '2027-03-23', 'عید نوروز' ],
	[ '2027-03-24', 'عید نوروز' ],
	[ '2027-04-01', 'روز جمهوری اسلامی ایران' ],
	[ '2027-04-02', 'روز طبیعت' ],
].map( ( [ date, title ] ) => ( { calendar: 'ir', date, title, source: 'dataset' } ) );

/**
 * @param {string} path   Below the REST namespace, e.g. "/staff".
 * @param {string} method HTTP method.
 * @param {URLSearchParams} query The query string.
 * @param {unknown} body  The parsed JSON body, if any.
 * @param {object} state  What the screenshots changed (brand, general, …).
 * @return {{status: number, body: unknown, headers?: Record<string,string>}}
 */
function answer( path, method, query, body, state ) {
	const ok = ( payload, headers ) => ( { status: 200, body: payload, headers } );
	const list = ( items ) =>
		ok( items, { 'X-WP-Total': String( items.length ), 'X-WP-TotalPages': '1' } );

	if ( method !== 'GET' ) {
		if ( path === '/brand' ) {
			Object.assign( state.brand, body );

			return ok( state.brand );
		}
		if ( path === '/general' ) {
			Object.assign( state.general, body );

			return ok( state.general );
		}

		return ok( body ?? {} );
	}
	let match;
	switch ( true ) {
		case path === '/brand':
			return ok( state.brand );
		case path === '/general':
			return ok( state.general );
		case path === '/onboarding':
			return ok( { done: state.onboarded } );
		case path === '/booking-rules':
			return ok( { slot_step_min: 30, min_notice_min: 60, max_advance_days: 60, staff_choice: 'least_busy' } );
		case ( match = /^\/(services|staff|locations|resources|service-categories|extras)\/(\d+)$/.exec( path ) ) !== null: {
			const pool = { services, staff, locations, resources, 'service-categories': categories, extras }[ match[ 1 ] ];

			return ok( pool.find( ( item ) => item.id === Number( match[ 2 ] ) ) ?? pool[ 0 ] );
		}
		case path === '/locations':
			return list( locations );
		case path === '/staff':
			return list( staff );
		case path === '/services':
			return list( services );
		case path === '/service-categories':
			return list( categories );
		case path === '/resources':
			return list( resources );
		case path === '/extras':
			return list( extras );
		case ( match = /^\/schedules\/(staff|resource|location)\/\d+$/.exec( path ) ) !== null:
			return ok( {
				rules: [ 0, 1, 2, 3, 4, 5 ].flatMap( ( weekday ) => [
					{ weekday, start: '09:00', end: '17:00', kind: 'work' },
					{ weekday, start: '12:30', end: '13:30', kind: 'break' },
				] ),
			} );
		case path === '/schedule-exceptions':
			return ok( [
				{ id: 1, owner_type: 'staff', owner_id: 1, date: day( 6 ), start: null, end: null, kind: 'off', note: 'مرخصی' },
				{ id: 2, owner_type: 'staff', owner_id: 1, date: day( 12 ), start: '09:00', end: '11:00', kind: 'blocked', note: 'جلسه' },
			] );
		case path === '/holidays':
			return ok( holidays );
		case path === '/appointments':
			return list( appointments );
		case ( match = /^\/appointments\/(\d+)$/.exec( path ) ) !== null: {
			const item = appointments.find( ( entry ) => entry.id === Number( match[ 1 ] ) ) ?? appointments[ 0 ];

			return ok( {
				...item,
				uuid: '01J00000000000000000000000',
				source: 'admin',
				price: { total: item.total, lines: [ { code: 'base', amount: item.total, ref: null, qty: 1 } ] },
				customer_note: 'لطفاً ساعت دقیق را یادآوری کنید.',
				internal_note: 'مشتری پیگیر، ترجیح می‌دهد صبح بیاید.',
				extras: [],
				answers: {},
				history: [
					{ action: 'created', from: null, to: 'confirmed', changes: [], actor_type: 'admin', actor_id: 1, reason: null, at: at( -3, 11, 20 ) },
				],
				created_by: 1,
				created_at: at( -3, 11, 20 ),
				cancelled_at: null,
				cancel_reason: null,
			} );
		}
		case path === '/calendar':
			return ok( appointments.filter( ( item ) => item.status !== 'cancelled' ) );
		case path === '/customers':
			return list( customers );
		case ( match = /^\/customers\/(\d+)$/.exec( path ) ) !== null:
			return ok( customers.find( ( item ) => item.id === Number( match[ 1 ] ) ) ?? customers[ 0 ] );
		case path === '/reports/summary':
			return ok( summary( query.get( 'from' ), query.get( 'to' ) ) );
		case path === '/notification-templates':
			return ok( templates );
		case path === '/sms':
			return ok( {
				order: [ 'kavenegar' ],
				senders: { kavenegar: '10008663' },
				otp_patterns: {},
				providers: [
					{ id: 'kavenegar', configured: true, secrets: [ { name: 'sms_kavenegar_key', set: true, fixed: false } ] },
					{ id: 'ippanel', configured: false, secrets: [ { name: 'sms_ippanel_key', set: false, fixed: false } ] },
				],
			} );
		case path === '/payments/settings':
			return ok( {
				gateways: [
					{ id: 'zarinpal', secret: 'zarinpal_merchant', set: true, fixed: false },
					{ id: 'zibal', secret: 'zibal_merchant', set: false, fixed: false },
				],
				woocommerce: { available: false, enabled: false },
			} );
		case path === '/status':
			return ok( {
				versions: { plugin: '1.1.0', wordpress: '7.0', php: '8.3.12', database: 'MariaDB 10.11' },
				checks: [
					{ id: 'schema', status: 'good', label: 'پایگاه داده به‌روز است', description: 'همه مهاجرت‌ها اجرا شده‌اند.' },
					{ id: 'queue', status: 'good', label: 'صف کارها سالم است', description: 'کار عقب‌افتاده‌ای نیست.' },
					{ id: 'sms', status: 'recommended', label: 'پیامک آزمایش نشده', description: 'یک پیامک آزمایشی بفرستید.' },
				],
				queue: { pending: 4, late: 0, failed: 0 },
				schema: { kernel: 3, catalog: 2, scheduling: 2, booking: 6 },
				modules: [
					{ id: 'notifications', switchable: true, enabled: true },
					{ id: 'widget', switchable: true, enabled: true },
				],
				delete_on_uninstall: false,
				errors: [],
			} );
		case path === '/policies/cancellation/0':
			return ok( { config: { notice_hours: 12, refund: [ { hours: 48, percent: 100 }, { hours: 12, percent: 50 } ] } } );
		case path.startsWith( '/policies/' ):
			return ok( { config: null } );
		case path === '/fields' || path === '/coupons' || path === '/time-rules':
			return ok( [] );
		case path === '/catalog':
			return ok( catalog() );
		case path === '/nonce':
			return ok( { nonce: 'x' } );
		case path === '/otp/config':
			return ok( { required: false, code_length: 6 } );
		case path === '/payment-options':
			return ok( { online: true } );
		case path === '/service-fields':
			return ok( [] );
		case path === '/availability':
			if ( query.get( 'view' ) === 'month' ) {
				return ok( availabilityMonth( query.get( 'from' ) ?? day( 0 ) ) );
			}
			if ( query.get( 'view' ) === 'first' ) {
				const first = availabilityDay( day( 1 ) );

				return ok( { timezone: 'Asia/Tehran', date: first.date, slots: first.slots } );
			}

			return ok( availabilityDay( query.get( 'date' ) ?? day( 1 ) ) );
		case path === '/my/appointments':
			return ok(
				appointments
					.filter( ( item ) => item.customer_id === 1 && item.status === 'confirmed' )
					.concat( appointments.filter( ( item ) => item.id === 7 ) )
					.map( ( item ) => ( {
						...item,
						cancel: { allowed: true, reason_code: null, refund_percent: 50, refund: money( 0 ) },
						reschedule: { allowed: true, reason_code: null, refund_percent: 0, refund: money( 0 ) },
					} ) )
			);
		default:
			return ok( [] );
	}
}

module.exports = { answer, day, at };
