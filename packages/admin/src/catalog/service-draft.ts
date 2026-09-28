import type { ServiceStaff, ServiceVariant } from '@vaqtyar/shared';

/**
 * Edits of a service draft that keep it valid for the API: exactly one
 * default variant, and one assignment per staff member unless the admin
 * gave them per-variant terms (docs/api.md).
 */

export function newVariant( isDefault: boolean ): ServiceVariant {
	return {
		id: null,
		label: '',
		duration_min: 30,
		price: { amount: 0, currency: 'IRR' },
		is_default: isDefault,
		buffer_before_min: 0,
		buffer_after_min: 0,
		slot_step_min: null,
		sort: 0,
	};
}

export function makeDefault(
	variants: ServiceVariant[],
	index: number
): ServiceVariant[] {
	return variants.map( ( variant, i ) => ( {
		...variant,
		is_default: i === index,
	} ) );
}

/**
 * Removes a variant; the first one left becomes the default if the default
 * went. The last variant stays: a service has at least one.
 *
 * @param variants
 * @param index
 */
export function removeVariant(
	variants: ServiceVariant[],
	index: number
): ServiceVariant[] {
	if ( variants.length <= 1 ) {
		return variants;
	}
	const left = variants.filter( ( _, i ) => i !== index );

	return left.some( ( variant ) => variant.is_default )
		? left
		: makeDefault( left, 0 );
}

/**
 * Assigns a staff member to every variant at the service's terms, or takes
 * every assignment of theirs away.
 *
 * @param staff
 * @param staffId
 * @param on
 */
export function toggleStaff(
	staff: ServiceStaff[],
	staffId: number,
	on: boolean
): ServiceStaff[] {
	const others = staff.filter( ( row ) => row.staff_id !== staffId );

	return on
		? [
				...others,
				{
					staff_id: staffId,
					variant_id: null,
					price: null,
					duration_min: null,
				},
			]
		: others;
}

/**
 * Own terms of one staff member for every variant; the API takes them only
 * on a service with a single variant. Null keeps the service's.
 *
 * @param staff
 * @param staffId
 * @param terms
 */
export function setStaffTerms(
	staff: ServiceStaff[],
	staffId: number,
	terms: Pick< ServiceStaff, 'price' | 'duration_min' >
): ServiceStaff[] {
	return staff.map( ( row ) =>
		row.staff_id === staffId && row.variant_id === null
			? { ...row, ...terms }
			: row
	);
}

/**
 * Terms for every variant are allowed on one variant only, so a second
 * variant drops them (the staff member keeps the assignment).
 *
 * @param staff
 * @param variantCount
 */
export function fitStaffTerms(
	staff: ServiceStaff[],
	variantCount: number
): ServiceStaff[] {
	return variantCount <= 1
		? staff
		: staff.map( ( row ) =>
				row.variant_id === null
					? { ...row, price: null, duration_min: null }
					: row
			);
}
