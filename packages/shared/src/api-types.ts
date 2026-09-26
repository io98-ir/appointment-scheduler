/**
 * Shapes of the REST API, kept by hand next to the PHP schemas (tech-stack
 * §2): a change of a schema in PHP updates its type here in the same commit.
 */

/** Money in the smallest unit of the currency (architecture §9). */
export interface Money {
	amount: number;
	currency: 'IRR';
}

/**
 * Every error response (architecture §8). Errors WordPress raises before the
 * plugin's code runs (unknown route, a parameter failing the schema) have no
 * details or request_id.
 */
export interface ErrorEnvelope {
	code: string;
	message: string;
	data: {
		status: number;
		details?: Record< string, unknown >;
		request_id?: string;
	};
}

/** Whether customers can book a catalog item. */
export type CatalogStatus = 'active' | 'inactive';

/**
 * The admin catalog API (docs/api.md). Each resource is read and written in
 * the same shape; `id` is ignored on writes, where the URL names the item. A
 * PUT replaces every field, so a field left out takes its default.
 */
export interface Location {
	id: number;
	name: string;
	/** An IANA region name such as "Asia/Tehran". */
	timezone: string;
	address: string;
	/** E.164, e.g. "+982112345678". */
	phone: string | null;
	/** The slug of a holiday calendar, e.g. "ir". */
	holiday_calendar: string | null;
	status: CatalogStatus;
	sort: number;
}

export interface Staff {
	id: number;
	name: string;
	/** "#rrggbb", lowercase. */
	color: string;
	wp_user_id: number | null;
	/** Null serves every location. */
	location_id: number | null;
	title: string;
	email: string | null;
	phone: string | null;
	/** A media library image. */
	avatar_id: number | null;
	bio: string;
	status: CatalogStatus;
	sort: number;
}

export interface Resource {
	id: number;
	name: string;
	/** Services ask for one of a group, e.g. "room". */
	group_key: string;
	location_id: number | null;
	capacity: number;
	status: CatalogStatus;
}

export interface ServiceCategory {
	id: number;
	name: string;
	color: string;
	sort: number;
}

export interface ServiceVariant {
	/** Null for a new variant; a stored variant left out of a PUT is deleted. */
	id: number | null;
	label: string;
	duration_min: number;
	price: Money;
	/** Exactly one per service. */
	is_default: boolean;
	buffer_before_min: number;
	buffer_after_min: number;
	/** Null uses the site setting. */
	slot_step_min: number | null;
	sort: number;
}

/** A staff member's assignment, with their own price or duration. */
export interface ServiceStaff {
	staff_id: number;
	/** Null covers every variant; then price and duration only with a single variant. */
	variant_id: number | null;
	price: Money | null;
	duration_min: number | null;
}

export interface ServiceResource {
	group_key: string;
	quantity: number;
}

export interface Service {
	id: number;
	name: string;
	category_id: number | null;
	description: string;
	image_id: number | null;
	/** Customers booked at the same time with one staff member. */
	capacity: number;
	status: CatalogStatus;
	sort: number;
	variants: ServiceVariant[];
	staff: ServiceStaff[];
	resources: ServiceResource[];
}

export interface Extra {
	id: number;
	name: string;
	price: Money;
	/** Minutes each unit adds. */
	duration_min: number;
	/** Null for an extra every service offers. */
	service_id: number | null;
	max_qty: number;
	status: CatalogStatus;
}

/** GET /availability: a day of the location. */
export type DayStatus = 'available' | 'full' | 'closed';

export interface AvailabilitySlot {
	/** ISO-8601 with the location's offset. */
	start: string;
	/** The end for the first staff member, who is assigned at hold time. */
	end: string;
	/** In assignment order. */
	staff_ids: number[];
	seats_left: number;
}

export interface AvailabilityDay {
	timezone: string;
	date: string;
	status: DayStatus;
	slots: AvailabilitySlot[];
}

export interface AvailabilityMonth {
	timezone: string;
	days: { date: string; status: DayStatus }[];
}

export interface AvailabilityFirst {
	timezone: string;
	/** Null when no day within `days` has a free start. */
	date: string | null;
	slots: AvailabilitySlot[];
}

/** POST /holds, 201 (docs/api.md). */
export interface PlacedHold {
	token: string;
	expires_at: string;
	staff_id: number;
	start: string;
	end: string;
}
