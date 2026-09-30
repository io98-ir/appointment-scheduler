import { useMutation, useQueryClient } from '@tanstack/react-query';
import {
	SLUG,
	type Extra,
	type Resource,
	type Service,
	type ServiceVariant,
	type Staff,
} from '@vaqtyar/shared';
import {
	Button,
	CheckboxControl,
	RadioControl,
	SelectControl,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import type { FormEvent } from 'react';

import { useApi } from '../api';
import { Fields } from '../fields/Fields';
import { NotFound } from '../NotFound';
import { Policies } from '../policies/Policies';
import { useRoute } from '../router';
import { CatalogList, type Column } from './CatalogList';
import { screenOf, useAll } from './crud';
import { IntField, SIZE, statusColumn, StatusField } from './fields';
import { ItemEditor } from './ItemEditor';
import { useCategories } from './screens';
import {
	fitStaffTerms,
	makeDefault,
	newVariant,
	removeVariant,
	setStaffTerms,
	toggleStaff,
} from './service-draft';

const COLUMNS: Column< Service >[] = [
	{
		id: 'variants',
		label: __( 'Options', 'vaqtyar' ),
		render: ( item ) => item.variants.length,
	},
	{
		id: 'duration',
		label: __( 'Minutes', 'vaqtyar' ),
		render: ( item ) =>
			item.variants.find( ( variant ) => variant.is_default )
				?.duration_min ?? 0,
	},
	{
		id: 'shortcode',
		label: __( 'Booking form for this service', 'vaqtyar' ),
		// A page can offer just this service: paste the shortcode into it.
		render: ( item ) => (
			<code dir="ltr">{ `[${ SLUG }_booking service="${ item.id }"]` }</code>
		),
	},
	statusColumn(),
];

export function ServicesPage() {
	const screen = screenOf( useRoute(), '/services' );
	if ( screen === null ) {
		return <NotFound />;
	}
	if ( screen === 'list' ) {
		return (
			<CatalogList< Service >
				base="/services"
				columns={ COLUMNS }
				addLabel={ __( 'Add service', 'vaqtyar' ) }
				extra={
					<a href="#/service-categories">
						{ __( 'Categories', 'vaqtyar' ) }
					</a>
				}
			/>
		);
	}

	return (
		<ItemEditor< Service >
			base="/services"
			id={ screen }
			empty={ {
				name: '',
				category_id: null,
				description: '',
				image_id: null,
				capacity: 1,
				status: 'active',
				sort: 0,
				variants: [ newVariant( true ) ],
				staff: [],
				resources: [],
			} }
			after={ ( service ) => (
				<>
					<Extras serviceId={ service.id } />
					<Policies serviceId={ service.id } />
					<Fields scope="service" serviceId={ service.id } />
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
					<CategoryField
						value={ draft.category_id }
						onChange={ ( categoryId ) =>
							change( { category_id: categoryId } )
						}
					/>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Description', 'vaqtyar' ) }
						value={ draft.description }
						onChange={ ( description ) =>
							change( { description } )
						}
					/>
					<IntField
						label={ __( 'Customers at the same time', 'vaqtyar' ) }
						help={ __(
							'More than 1 for a class or group session.',
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
					<IntField
						label={ __( 'Order', 'vaqtyar' ) }
						value={ draft.sort }
						onChange={ ( sort ) => change( { sort } ) }
					/>
					<Variants
						variants={ draft.variants }
						onChange={ ( variants ) =>
							change( {
								variants,
								staff: fitStaffTerms(
									draft.staff,
									variants.length
								),
							} )
						}
					/>
					<StaffAssignments
						draft={ draft }
						onChange={ ( staff ) => change( { staff } ) }
					/>
					<ResourceNeeds
						needs={ draft.resources }
						onChange={ ( resources ) => change( { resources } ) }
					/>
				</>
			) }
		</ItemEditor>
	);
}

function CategoryField( {
	value,
	onChange,
}: {
	value: number | null;
	onChange: ( value: number | null ) => void;
} ) {
	const categories = useCategories();

	return (
		<SelectControl
			{ ...SIZE }
			label={ __( 'Category', 'vaqtyar' ) }
			value={ value === null ? '' : String( value ) }
			options={ [
				{ value: '', label: __( 'None', 'vaqtyar' ) },
				...( categories.data ?? [] ).map( ( category ) => ( {
					value: String( category.id ),
					label: category.name,
				} ) ),
			] }
			onChange={ ( id ) => onChange( id === '' ? null : Number( id ) ) }
		/>
	);
}

/**
 * The bookable options of the service, e.g. "30 minutes" and "60 minutes",
 * each with its duration and price.
 *
 * @param props
 * @param props.variants
 * @param props.onChange
 */
function Variants( {
	variants,
	onChange,
}: {
	variants: ServiceVariant[];
	onChange: ( variants: ServiceVariant[] ) => void;
} ) {
	const change = ( index: number, patch: Partial< ServiceVariant > ) =>
		onChange(
			variants.map( ( variant, i ) =>
				i === index ? { ...variant, ...patch } : variant
			)
		);

	return (
		<fieldset className="vqy-admin__panel">
			<legend>{ __( 'Options and prices', 'vaqtyar' ) }</legend>
			<RadioControl
				label={ __( 'Default option', 'vaqtyar' ) }
				selected={ String(
					variants.findIndex( ( variant ) => variant.is_default )
				) }
				options={ variants.map( ( variant, index ) => ( {
					value: String( index ),
					label:
						variant.label ||
						sprintf(
							/* translators: %d: the option's number. */
							__( 'Option %d', 'vaqtyar' ),
							index + 1
						),
				} ) ) }
				onChange={ ( index ) =>
					onChange( makeDefault( variants, Number( index ) ) )
				}
			/>
			{ variants.map( ( variant, index ) => (
				<div
					className="vqy-admin__row"
					key={ variant.id ?? `new-${ index }` }
				>
					<TextControl
						{ ...SIZE }
						label={ __( 'Label', 'vaqtyar' ) }
						value={ variant.label }
						onChange={ ( label ) => change( index, { label } ) }
					/>
					<IntField
						label={ __( 'Minutes', 'vaqtyar' ) }
						min={ 1 }
						value={ variant.duration_min }
						onChange={ ( durationMin ) =>
							change( index, { duration_min: durationMin } )
						}
					/>
					<IntField
						label={ __( 'Price (IRR)', 'vaqtyar' ) }
						value={ variant.price.amount }
						onChange={ ( amount ) =>
							change( index, {
								price: { amount, currency: 'IRR' },
							} )
						}
					/>
					<IntField
						label={ __( 'Buffer before', 'vaqtyar' ) }
						value={ variant.buffer_before_min }
						onChange={ ( bufferBeforeMin ) =>
							change( index, {
								buffer_before_min: bufferBeforeMin,
							} )
						}
					/>
					<IntField
						label={ __( 'Buffer after', 'vaqtyar' ) }
						value={ variant.buffer_after_min }
						onChange={ ( bufferAfterMin ) =>
							change( index, {
								buffer_after_min: bufferAfterMin,
							} )
						}
					/>
					<Button
						variant="tertiary"
						isDestructive
						disabled={ variants.length <= 1 }
						onClick={ () =>
							onChange( removeVariant( variants, index ) )
						}
					>
						{ __( 'Remove', 'vaqtyar' ) }
					</Button>
				</div>
			) ) }
			<Button
				variant="secondary"
				onClick={ () =>
					onChange( [ ...variants, newVariant( false ) ] )
				}
			>
				{ __( 'Add option', 'vaqtyar' ) }
			</Button>
		</fieldset>
	);
}

/**
 * Who serves the service. With a single option, a staff member may have
 * their own price and duration; empty keeps the service's.
 *
 * @param props
 * @param props.draft
 * @param props.onChange
 */
function StaffAssignments( {
	draft,
	onChange,
}: {
	draft: Omit< Service, 'id' >;
	onChange: ( staff: Service[ 'staff' ] ) => void;
} ) {
	const staff = useAll< Staff >( '/staff' );
	const single = draft.variants.length === 1;

	return (
		<fieldset className="vqy-admin__panel">
			<legend>{ __( 'Staff', 'vaqtyar' ) }</legend>
			{ staff.data?.length === 0 && (
				<p className="vqy-admin__muted">
					<a href="#/staff/new">
						{ __( 'Add a staff member first.', 'vaqtyar' ) }
					</a>
				</p>
			) }
			{ ( staff.data ?? [] ).map( ( member ) => {
				const rows = draft.staff.filter(
					( row ) => row.staff_id === member.id
				);
				const all = rows.find( ( row ) => row.variant_id === null );

				return (
					<div className="vqy-admin__row" key={ member.id }>
						<CheckboxControl
							__nextHasNoMarginBottom
							label={ member.name }
							checked={ rows.length > 0 }
							onChange={ ( on ) =>
								onChange(
									toggleStaff( draft.staff, member.id, on )
								)
							}
						/>
						{ single && all && (
							<>
								<TextControl
									{ ...SIZE }
									type="number"
									min={ 0 }
									label={ __( 'Own price (IRR)', 'vaqtyar' ) }
									value={
										all.price === null
											? ''
											: String( all.price.amount )
									}
									onChange={ ( text ) =>
										onChange(
											setStaffTerms(
												draft.staff,
												member.id,
												{
													price:
														text === ''
															? null
															: {
																	amount: Math.max(
																		0,
																		Number.parseInt(
																			text,
																			10
																		) || 0
																	),
																	currency:
																		'IRR',
																},
													duration_min:
														all.duration_min,
												}
											)
										)
									}
								/>
								<TextControl
									{ ...SIZE }
									type="number"
									min={ 1 }
									label={ __( 'Own minutes', 'vaqtyar' ) }
									value={
										all.duration_min === null
											? ''
											: String( all.duration_min )
									}
									onChange={ ( text ) =>
										onChange(
											setStaffTerms(
												draft.staff,
												member.id,
												{
													price: all.price,
													duration_min:
														text === ''
															? null
															: Math.max(
																	1,
																	Number.parseInt(
																		text,
																		10
																	) || 1
																),
												}
											)
										)
									}
								/>
							</>
						) }
						{ rows.some( ( row ) => row.variant_id !== null ) && (
							<span className="vqy-admin__muted">
								{ __( 'Has terms per option.', 'vaqtyar' ) }
							</span>
						) }
					</div>
				);
			} ) }
		</fieldset>
	);
}

/**
 * The resources each booking takes, one or more of a group.
 *
 * @param props
 * @param props.needs
 * @param props.onChange
 */
function ResourceNeeds( {
	needs,
	onChange,
}: {
	needs: Service[ 'resources' ];
	onChange: ( needs: Service[ 'resources' ] ) => void;
} ) {
	const resources = useAll< Resource >( '/resources' );
	const groups = [
		...new Set( ( resources.data ?? [] ).map( ( r ) => r.group_key ) ),
	];
	const free = groups.filter(
		( group ) => ! needs.some( ( need ) => need.group_key === group )
	);

	return (
		<fieldset className="vqy-admin__panel">
			<legend>{ __( 'Resources', 'vaqtyar' ) }</legend>
			{ needs.map( ( need, index ) => (
				<div className="vqy-admin__row" key={ need.group_key }>
					<span>{ need.group_key }</span>
					<IntField
						label={ __( 'Quantity', 'vaqtyar' ) }
						min={ 1 }
						value={ need.quantity }
						onChange={ ( quantity ) =>
							onChange(
								needs.map( ( other, i ) =>
									i === index ? { ...other, quantity } : other
								)
							)
						}
					/>
					<Button
						variant="tertiary"
						isDestructive
						onClick={ () =>
							onChange( needs.filter( ( _, i ) => i !== index ) )
						}
					>
						{ __( 'Remove', 'vaqtyar' ) }
					</Button>
				</div>
			) ) }
			{ free.length > 0 && (
				<SelectControl
					{ ...SIZE }
					label={ __( 'Needs a resource of', 'vaqtyar' ) }
					value=""
					options={ [
						{ value: '', label: __( 'Choose a group', 'vaqtyar' ) },
						...free.map( ( group ) => ( {
							value: group,
							label: group,
						} ) ),
					] }
					onChange={ ( group ) =>
						group !== '' &&
						onChange( [
							...needs,
							{ group_key: group, quantity: 1 },
						] )
					}
				/>
			) }
		</fieldset>
	);
}

/**
 * The service's add-ons. Each is its own item of /extras, saved at once.
 *
 * @param props
 * @param props.serviceId
 */
function Extras( { serviceId }: { serviceId: number } ) {
	const api = useApi();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const extras = useAll< Extra >( '/extras' );
	const mine = ( extras.data ?? [] ).filter(
		( extra ) => extra.service_id === serviceId
	);
	const done = ( message: string ) => {
		void client.invalidateQueries( { queryKey: [ '/extras' ] } );
		void createSuccessNotice( message, { type: 'snackbar' } );
	};
	const remove = useMutation( {
		mutationFn: ( id: number ) => api.delete< null >( `/extras/${ id }` ),
		onSuccess: () => done( __( 'Deleted.', 'vaqtyar' ) ),
	} );

	return (
		<section className="vqy-admin__panel">
			<h2>{ __( 'Add-ons', 'vaqtyar' ) }</h2>
			{ mine.map( ( extra ) => (
				<ExtraForm
					key={ extra.id }
					serviceId={ serviceId }
					extra={ extra }
					onSaved={ () => done( __( 'Saved.', 'vaqtyar' ) ) }
					onDelete={ () => remove.mutate( extra.id ) }
				/>
			) ) }
			<ExtraForm
				key={ `new-${ mine.length }` }
				serviceId={ serviceId }
				extra={ null }
				onSaved={ () => done( __( 'Added.', 'vaqtyar' ) ) }
			/>
		</section>
	);
}

function ExtraForm( {
	serviceId,
	extra,
	onSaved,
	onDelete,
}: {
	serviceId: number;
	extra: Extra | null;
	onSaved: () => void;
	onDelete?: () => void;
} ) {
	const api = useApi();
	const [ draft, setDraft ] = useState< Omit< Extra, 'id' > >(
		extra ?? {
			name: '',
			price: { amount: 0, currency: 'IRR' },
			duration_min: 0,
			service_id: serviceId,
			max_qty: 1,
			status: 'active',
		}
	);
	const save = useMutation( {
		mutationFn: () =>
			extra === null
				? api.post< Extra >( '/extras', draft )
				: api.put< Extra >( `/extras/${ extra.id }`, draft ),
		onSuccess: onSaved,
	} );
	const submit = ( event: FormEvent ) => {
		event.preventDefault();
		save.mutate();
	};

	return (
		<form className="vqy-admin__row" onSubmit={ submit }>
			<TextControl
				{ ...SIZE }
				label={ __( 'Add-on', 'vaqtyar' ) }
				value={ draft.name }
				required
				onChange={ ( name ) => setDraft( { ...draft, name } ) }
			/>
			<IntField
				label={ __( 'Price (IRR)', 'vaqtyar' ) }
				value={ draft.price.amount }
				onChange={ ( amount ) =>
					setDraft( { ...draft, price: { amount, currency: 'IRR' } } )
				}
			/>
			<IntField
				label={ __( 'Extra minutes', 'vaqtyar' ) }
				value={ draft.duration_min }
				onChange={ ( durationMin ) =>
					setDraft( { ...draft, duration_min: durationMin } )
				}
			/>
			<IntField
				label={ __( 'Most per booking', 'vaqtyar' ) }
				min={ 1 }
				value={ draft.max_qty }
				onChange={ ( maxQty ) =>
					setDraft( { ...draft, max_qty: maxQty } )
				}
			/>
			<Button
				variant="secondary"
				type="submit"
				isBusy={ save.isPending }
				disabled={ save.isPending }
			>
				{ extra === null
					? __( 'Add', 'vaqtyar' )
					: __( 'Save', 'vaqtyar' ) }
			</Button>
			{ onDelete && (
				<Button variant="tertiary" isDestructive onClick={ onDelete }>
					{ __( 'Delete', 'vaqtyar' ) }
				</Button>
			) }
		</form>
	);
}
