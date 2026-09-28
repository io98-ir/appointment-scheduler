import type {
	Resource,
	Location,
	ServiceCategory,
	Staff,
} from '@vaqtyar/shared';
import {
	SelectControl,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { ReactNode } from 'react';

import { NotFound } from '../NotFound';
import { useRoute } from '../router';
import { CatalogList, type Column } from './CatalogList';
import { screenOf, useAll } from './crud';
import {
	ColorField,
	IntField,
	LocationField,
	SIZE,
	statusColumn,
	StatusField,
} from './fields';
import { ItemEditor } from './ItemEditor';
import { TimeOff } from './TimeOff';
import { WeeklySchedule } from './WeeklySchedule';

/**
 * The list at the base route, the editor below it.
 *
 * @param base
 * @param list
 * @param editor
 */
function useScreen(
	base: string,
	list: () => ReactNode,
	editor: ( id: 'new' | number ) => ReactNode
): ReactNode {
	const screen = screenOf( useRoute(), base );
	if ( screen === null ) {
		return <NotFound />;
	}

	return screen === 'list' ? list() : editor( screen );
}

/** Every zone the browser knows, Tehran first. */
function timezones(): string[] {
	const all =
		typeof Intl.supportedValuesOf === 'function'
			? Intl.supportedValuesOf( 'timeZone' )
			: [];

	return [
		'Asia/Tehran',
		...all.filter( ( zone ) => zone !== 'Asia/Tehran' ),
	];
}

const LOCATION_COLUMNS: Column< Location >[] = [
	{
		id: 'timezone',
		label: __( 'Time zone', 'vaqtyar' ),
		render: ( item ) => item.timezone,
	},
	statusColumn(),
];

export function LocationsPage() {
	return useScreen(
		'/locations',
		() => (
			<CatalogList< Location >
				base="/locations"
				columns={ LOCATION_COLUMNS }
				addLabel={ __( 'Add location', 'vaqtyar' ) }
			/>
		),
		( id ) => (
			<ItemEditor< Location >
				base="/locations"
				id={ id }
				empty={ {
					name: '',
					timezone: 'Asia/Tehran',
					address: '',
					phone: null,
					holiday_calendar: 'ir',
					status: 'active',
					sort: 0,
				} }
				after={ ( location ) => (
					<WeeklySchedule
						ownerType="location"
						ownerId={ location.id }
					/>
				) }
			>
				{ ( draft, change ) => (
					<>
						<TextControl
							{ ...SIZE }
							label={ __( 'Name', 'vaqtyar' ) }
							value={ draft.name }
							required
							onChange={ ( name ) => change( { name } ) }
						/>
						<SelectControl
							{ ...SIZE }
							label={ __( 'Time zone', 'vaqtyar' ) }
							value={ draft.timezone }
							options={ timezones().map( ( zone ) => ( {
								value: zone,
								label: zone,
							} ) ) }
							onChange={ ( timezone ) => change( { timezone } ) }
						/>
						<TextareaControl
							__nextHasNoMarginBottom
							label={ __( 'Address', 'vaqtyar' ) }
							value={ draft.address }
							onChange={ ( address ) => change( { address } ) }
						/>
						<TextControl
							{ ...SIZE }
							type="tel"
							label={ __( 'Phone', 'vaqtyar' ) }
							value={ draft.phone ?? '' }
							onChange={ ( phone ) => change( { phone } ) }
						/>
						<SelectControl
							{ ...SIZE }
							label={ __( 'Holiday calendar', 'vaqtyar' ) }
							value={ draft.holiday_calendar ?? '' }
							options={
								[
									{
										value: '',
										label: __( 'None', 'vaqtyar' ),
									},
									{
										value: 'ir',
										label: __( 'Iran', 'vaqtyar' ),
									},
								] as { value: string; label: string }[]
							}
							onChange={ ( value ) =>
								change( {
									holiday_calendar:
										value === '' ? null : value,
								} )
							}
						/>
						<StatusField
							value={ draft.status }
							onChange={ ( status ) => change( { status } ) }
						/>
						<IntField
							label={ __( 'Order', 'vaqtyar' ) }
							value={ draft.sort }
							onChange={ ( sort ) => change( { sort } ) }
						/>
					</>
				) }
			</ItemEditor>
		)
	);
}

const STAFF_COLUMNS: Column< Staff >[] = [
	{
		id: 'title',
		label: __( 'Title', 'vaqtyar' ),
		render: ( item ) => item.title,
	},
	{
		id: 'color',
		label: __( 'Color', 'vaqtyar' ),
		render: ( item ) => (
			<span
				className="vqy-admin__swatch"
				style={ { background: item.color } }
				aria-label={ item.color }
			/>
		),
	},
	statusColumn(),
];

export function StaffPage() {
	return useScreen(
		'/staff',
		() => (
			<CatalogList< Staff >
				base="/staff"
				columns={ STAFF_COLUMNS }
				addLabel={ __( 'Add staff member', 'vaqtyar' ) }
			/>
		),
		( id ) => (
			<ItemEditor< Staff >
				base="/staff"
				id={ id }
				empty={ {
					name: '',
					color: '#3858e9',
					wp_user_id: null,
					location_id: null,
					title: '',
					email: null,
					phone: null,
					avatar_id: null,
					bio: '',
					status: 'active',
					sort: 0,
				} }
				after={ ( staff ) => (
					<>
						<WeeklySchedule
							ownerType="staff"
							ownerId={ staff.id }
						/>
						<TimeOff ownerType="staff" ownerId={ staff.id } />
					</>
				) }
			>
				{ ( draft, change ) => (
					<>
						<TextControl
							{ ...SIZE }
							label={ __( 'Name', 'vaqtyar' ) }
							value={ draft.name }
							required
							onChange={ ( name ) => change( { name } ) }
						/>
						<TextControl
							{ ...SIZE }
							label={ __( 'Title', 'vaqtyar' ) }
							help={ __( 'e.g. Dentist', 'vaqtyar' ) }
							value={ draft.title }
							onChange={ ( title ) => change( { title } ) }
						/>
						<ColorField
							label={ __( 'Calendar color', 'vaqtyar' ) }
							value={ draft.color }
							onChange={ ( color ) => change( { color } ) }
						/>
						<LocationField
							value={ draft.location_id }
							onChange={ ( locationId ) =>
								change( { location_id: locationId } )
							}
						/>
						<TextControl
							{ ...SIZE }
							type="email"
							label={ __( 'Email', 'vaqtyar' ) }
							value={ draft.email ?? '' }
							onChange={ ( email ) => change( { email } ) }
						/>
						<TextControl
							{ ...SIZE }
							type="tel"
							label={ __( 'Phone', 'vaqtyar' ) }
							value={ draft.phone ?? '' }
							onChange={ ( phone ) => change( { phone } ) }
						/>
						<TextareaControl
							__nextHasNoMarginBottom
							label={ __( 'Bio', 'vaqtyar' ) }
							value={ draft.bio }
							onChange={ ( bio ) => change( { bio } ) }
						/>
						<StatusField
							value={ draft.status }
							onChange={ ( status ) => change( { status } ) }
						/>
						<IntField
							label={ __( 'Order', 'vaqtyar' ) }
							value={ draft.sort }
							onChange={ ( sort ) => change( { sort } ) }
						/>
					</>
				) }
			</ItemEditor>
		)
	);
}

const RESOURCE_COLUMNS: Column< Resource >[] = [
	{
		id: 'group_key',
		label: __( 'Group', 'vaqtyar' ),
		render: ( item ) => item.group_key,
	},
	{
		id: 'capacity',
		label: __( 'Capacity', 'vaqtyar' ),
		render: ( item ) => item.capacity,
	},
	statusColumn(),
];

export function ResourcesPage() {
	return useScreen(
		'/resources',
		() => (
			<CatalogList< Resource >
				base="/resources"
				columns={ RESOURCE_COLUMNS }
				addLabel={ __( 'Add resource', 'vaqtyar' ) }
			/>
		),
		( id ) => (
			<ItemEditor< Resource >
				base="/resources"
				id={ id }
				empty={ {
					name: '',
					group_key: '',
					location_id: null,
					capacity: 1,
					status: 'active',
				} }
			>
				{ ( draft, change ) => (
					<>
						<TextControl
							{ ...SIZE }
							label={ __( 'Name', 'vaqtyar' ) }
							value={ draft.name }
							required
							onChange={ ( name ) => change( { name } ) }
						/>
						<TextControl
							{ ...SIZE }
							label={ __( 'Group', 'vaqtyar' ) }
							help={ __(
								'A service asks for one resource of a group, e.g. "room".',
								'vaqtyar'
							) }
							value={ draft.group_key }
							required
							onChange={ ( groupKey ) =>
								change( { group_key: groupKey } )
							}
						/>
						<LocationField
							value={ draft.location_id }
							onChange={ ( locationId ) =>
								change( { location_id: locationId } )
							}
						/>
						<IntField
							label={ __( 'Capacity', 'vaqtyar' ) }
							help={ __(
								'Bookings it holds at the same time.',
								'vaqtyar'
							) }
							min={ 1 }
							value={ draft.capacity }
							onChange={ ( capacity ) => change( { capacity } ) }
						/>
						<StatusField
							value={ draft.status }
							onChange={ ( status ) => change( { status } ) }
						/>
					</>
				) }
			</ItemEditor>
		)
	);
}

const CATEGORY_COLUMNS: Column< ServiceCategory >[] = [
	{
		id: 'sort',
		label: __( 'Order', 'vaqtyar' ),
		render: ( item ) => item.sort,
	},
];

export function CategoriesPage() {
	return useScreen(
		'/service-categories',
		() => (
			<CatalogList< ServiceCategory >
				base="/service-categories"
				columns={ CATEGORY_COLUMNS }
				addLabel={ __( 'Add category', 'vaqtyar' ) }
				extra={
					<a href="#/services">{ __( 'Services', 'vaqtyar' ) }</a>
				}
			/>
		),
		( id ) => (
			<ItemEditor< ServiceCategory >
				base="/service-categories"
				id={ id }
				empty={ { name: '', color: '#3858e9', sort: 0 } }
			>
				{ ( draft, change ) => (
					<>
						<TextControl
							{ ...SIZE }
							label={ __( 'Name', 'vaqtyar' ) }
							value={ draft.name }
							required
							onChange={ ( name ) => change( { name } ) }
						/>
						<ColorField
							label={ __( 'Color', 'vaqtyar' ) }
							value={ draft.color }
							onChange={ ( color ) => change( { color } ) }
						/>
						<IntField
							label={ __( 'Order', 'vaqtyar' ) }
							value={ draft.sort }
							onChange={ ( sort ) => change( { sort } ) }
						/>
					</>
				) }
			</ItemEditor>
		)
	);
}

/** The categories, for the services list and form. */
export function useCategories() {
	return useAll< ServiceCategory >( '/service-categories' );
}
