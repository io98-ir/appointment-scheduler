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

/** Whose calendar a schedule belongs to. */
export type ScheduleOwnerType = 'staff' | 'resource' | 'location';

/** One range of /schedules/{owner_type}/{owner_id}. */
export interface ScheduleRule {
	/** 0 = Saturday … 6 = Friday. */
	weekday: number;
	/** "HH:MM", wall-clock time of the location. */
	start: string;
	end: string;
	kind: 'work' | 'break';
}

export interface WeeklySchedule {
	rules: ScheduleRule[];
}

/** /schedule-exceptions: time off, extra hours or blocked time on one date. */
export interface ScheduleException {
	id: number;
	owner_type: ScheduleOwnerType;
	owner_id: number;
	/** "YYYY-MM-DD", Gregorian. */
	date: string;
	/** Both null for the whole day. */
	start: string | null;
	end: string | null;
	kind: 'off' | 'extra' | 'blocked';
	note: string;
}

/** /holidays: one day off in a named calendar (a location's holidayCalendar). */
export interface Holiday {
	calendar: string;
	/** "YYYY-MM-DD", Gregorian. */
	date: string;
	title: string;
	source: 'dataset' | 'manual';
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

export type PriceLineCode =
	'base' | 'time_rule' | 'extra' | 'party' | 'coupon' | 'rounding';

export interface PriceLine {
	code: PriceLineCode;
	amount: Money;
	/** The extra, price rule or coupon the line comes from. */
	ref: number | null;
	qty: number;
}

export interface PriceQuote {
	total: Money;
	lines: PriceLine[];
}

/** POST /holds, 201 (docs/api.md). */
export interface PlacedHold {
	token: string;
	expires_at: string;
	staff_id: number;
	start: string;
	end: string;
	price: PriceQuote;
}

/** GET /service-fields: a custom field the booking form asks for. */
export interface PublicField {
	field_key: string;
	type: FieldType;
	label: string;
	required: boolean;
	options: string[];
	show_if: FieldShowIf | null;
}

/** What a policy says about a change (booking-engine §6). */
export interface PolicyDecision {
	allowed: boolean;
	/** Why not, e.g. "policy.cancel_window_passed"; null when allowed. */
	reason_code: string | null;
	refund_percent: number;
	refund: Money;
}

/** GET /my/appointments: an appointment of the signed-in customer. */
export interface PanelAppointment extends AppointmentListItem {
	/** Null once the appointment can no longer be changed. */
	cancel: PolicyDecision | null;
	reschedule: PolicyDecision | null;
}

/** POST /book, 201: what a guest sees of their appointment. */
export interface GuestBooking {
	/** The 8-character tracking code. */
	code: string;
	status: AppointmentStatus;
	/** ISO 8601 with the location's offset. */
	start: string;
	end: string;
	price: PriceQuote;
	/** The gateway's page, when the booking waits for an online payment. */
	payment_url?: string | null;
}

export type CustomerStatus = 'active' | 'blocked';

/** /customers (docs/api.md). */
export interface Customer {
	id: number;
	/** The public id (ULID). */
	uuid: string;
	first_name: string;
	last_name: string;
	/** E.164, e.g. "+989121234567". */
	phone: string;
	email: string | null;
	wp_user_id: number | null;
	/** "YYYY-MM-DD", Gregorian. */
	birth_date: string | null;
	note: string;
	tags: string[];
	status: CustomerStatus;
}

export type AppointmentStatus =
	| 'pending_approval'
	| 'pending_payment'
	| 'confirmed'
	| 'completed'
	| 'no_show'
	| 'cancelled'
	| 'expired';

export type PaymentStatus =
	'unpaid' | 'deposit_paid' | 'paid' | 'refunded' | 'partially_refunded';

/** GET /appointments and GET /calendar (docs/api.md). */
export interface AppointmentListItem {
	id: number;
	/** The 8-character tracking code. */
	code: string;
	status: AppointmentStatus;
	payment_status: PaymentStatus;
	customer_id: number;
	/** Null when the customer row is gone. */
	customer: {
		id: number;
		name: string;
		/** E.164; null once the customer is deleted. */
		phone: string | null;
		deleted: boolean;
	} | null;
	location_id: number;
	service_id: number;
	variant_id: number;
	staff_id: number;
	/** ISO 8601 with the location's offset. */
	start: string;
	end: string;
	party_size: number;
	total: Money;
}

/** GET /appointments/{id} (docs/api.md). */
export interface AppointmentDetail extends AppointmentListItem {
	uuid: string;
	source: string;
	price: PriceQuote;
	customer_note: string;
	internal_note: string;
	extras: { extra_id: number; qty: number; unit_price: Money }[];
	/** By field key. */
	answers: Record< string, string >;
	/** Oldest first. */
	history: {
		action: string;
		from: AppointmentStatus | null;
		to: AppointmentStatus | null;
		changes: Record< string, unknown > | unknown[];
		actor_type: string;
		actor_id: number | null;
		reason: string | null;
		at: string;
	}[];
	created_by: number | null;
	created_at: string;
	cancelled_at: string | null;
	cancel_reason: string | null;
}

export type PolicyType = 'cancellation' | 'reschedule';

export interface RefundTier {
	hours: number;
	percent: number;
}

/** GET|PUT /policies/cancellation/{service_id} (docs/api.md). */
export interface CancellationConfig {
	notice_hours: number | null;
	refund: RefundTier[];
}

/** GET|PUT /policies/reschedule/{service_id} (docs/api.md). */
export interface RescheduleConfig {
	notice_hours: number | null;
	max_times: number | null;
}

/** GET|PUT /policies/{type}/{service_id}: null when this level is not set. */
export interface PolicyResponse< Config > {
	config: Config | null;
}

export type FieldScope = 'global' | 'service';

export type FieldType = 'text' | 'textarea' | 'number' | 'select' | 'checkbox';

export interface FieldShowIf {
	field: string;
	equals: string;
}

/** GET /catalog (docs/api.md): what a customer may pick from, without login. */
export interface MenuLocation {
	id: number;
	name: string;
	timezone: string;
	address: string;
}

export interface MenuVariant {
	id: number;
	label: string;
	duration_min: number;
	price: Money;
	is_default: boolean;
}

/** A staff member's assignment to a service; `variant_id` null covers every variant. */
export interface MenuStaff {
	staff_id: number;
	name: string;
	title: string;
	/** Null serves every location. */
	location_id: number | null;
	variant_id: number | null;
	duration_min: number | null;
	price: Money | null;
}

export interface MenuService {
	id: number;
	name: string;
	category_id: number | null;
	description: string;
	capacity: number;
	variants: MenuVariant[];
	staff: MenuStaff[];
}

export interface PublicMenu {
	locations: MenuLocation[];
	categories: { id: number; name: string }[];
	services: MenuService[];
}

/** GET /reports/summary (docs/api.md): the figures for a range of local dates. */
export interface ReportSummary {
	from: string;
	to: string;
	totals: {
		/** Confirmed and completed ones. */
		appointments: number;
		/** IRR, of those. */
		revenue: number;
		cancelled: number;
		no_show: number;
		/** Percent, one decimal, of the appointments that were decided. */
		cancel_rate: number;
	};
	/** Every status with at least one appointment. */
	statuses: Record< string, number >;
	days: { date: string; appointments: number; revenue: number }[];
	services: { id: number; appointments: number; revenue: number }[];
	staff: { id: number; appointments: number; revenue: number }[];
}

export type CouponType = 'percent' | 'fixed';

/** /coupons: a discount code. `value` is a percent, or rials for `fixed`. */
export interface Coupon {
	id: number;
	code: string;
	type: CouponType;
	value: number;
	active: boolean;
	valid_from: string | null;
	valid_to: string | null;
	max_uses: number | null;
	used: number;
	service_ids: number[] | null;
}

/** /time-rules: a change of the base price for starts at some local times. */
export interface TimeRule {
	id: number;
	service_id: number | null;
	priority: number;
	active: boolean;
	/** 0 is Saturday … 6 is Friday; empty is every day. */
	weekdays: number[];
	from: string;
	to: string;
	valid_from: string | null;
	valid_to: string | null;
	percent: number;
}

/** /fields: a custom field definition (implementation-notes §4.13). */
export interface FieldDefinition {
	id: number;
	scope: FieldScope;
	service_id: number | null;
	field_key: string;
	type: FieldType;
	label: string;
	required: boolean;
	options: string[];
	show_if: FieldShowIf | null;
	sort: number;
}

/** /brand: the owner's own look; "" means the default (T6.1). */
export interface Brand {
	name: string;
	logo_url: string;
	/** "#rrggbb" or "". */
	color: string;
}

/** /booking-rules: what a customer may book, site-wide. */
export interface BookingRules {
	/** Minutes between offered start times, 1 to 1440. */
	slot_step_min: number;
	/** Minutes before the start that booking closes. */
	min_notice_min: number;
	/** Days ahead that booking is open, 1 to 730. */
	max_advance_days: number;
	/** Who gets the booking when the customer lets the business choose. */
	staff_choice: 'least_busy' | 'priority';
}

/** /general: how dates, numbers and the plugin's own screens are shown. */
export interface DisplaySettings {
	calendar: 'jalali' | 'gregorian';
	digits: 'persian' | 'latin';
	/** "auto" follows the site's language. */
	language: 'auto' | 'fa' | 'en';
}

/** /payments/settings: never a merchant id, only whether it is set. */
export interface PaymentSettings {
	gateways: Array< {
		id: string;
		/** The secret name a PUT sets, e.g. "zarinpal_merchant". */
		secret: string;
		set: boolean;
		/** Defined in wp-config.php, so read-only here. */
		fixed: boolean;
	} >;
	woocommerce: { available: boolean; enabled: boolean };
}

/** GET /sms: never a key, only whether it is set. */
export interface SmsOverview {
	/** The failover order; a provider that is not listed is off. */
	order: string[];
	senders: Record< string, string >;
	otp_patterns: Record< string, string >;
	providers: Array< {
		id: string;
		configured: boolean;
		secrets: Array< { name: string; set: boolean; fixed: boolean } >;
	} >;
}

/** GET /status: the server, the plugin's own state and its recent trouble (T6.2). */
export interface SystemStatus {
	versions: {
		plugin: string;
		wordpress: string;
		php: string;
		database: string;
	};
	checks: Array< {
		id: string;
		status: 'good' | 'recommended' | 'critical';
		label: string;
		description: string;
	} >;
	queue: { pending: number; late: number; failed: number };
	/** Migrations run per owner (a module id, or "kernel"). */
	schema: Record< string, number >;
	modules: ModuleSwitch[];
	/** Whether deleting the plugin also deletes its data; off until the owner opts in. */
	delete_on_uninstall: boolean;
	/** Newest first; `at` is UTC, "Y-m-d H:i:s". */
	errors: Array< { at: string; channel: string; message: string } >;
}

export interface ModuleSwitch {
	id: string;
	switchable: boolean;
	enabled: boolean;
}

export type TemplateTrigger =
	'booked' | 'cancelled' | 'rescheduled' | 'reminder';
export type TemplateAudience = 'customer' | 'staff' | 'admin';

/** What an SMS provider's pattern needs: its code and the placeholder names it takes, in order. */
export interface SmsPatternValue {
	code: string;
	args: string[];
}

/** GET /notification-templates (T5.4, T5.5). */
export interface NotificationTemplate {
	id: number;
	trigger: TemplateTrigger;
	audience: TemplateAudience;
	/** "email", or "sms" once a provider is set up. */
	channel: string;
	/** Minutes before the start; only a reminder has one. */
	offset_min: number | null;
	subject: string;
	body: string;
	enabled: boolean;
	/** By SMS provider id; a provider that is not listed gets the plain text. */
	sms_patterns: Record< string, SmsPatternValue >;
}
