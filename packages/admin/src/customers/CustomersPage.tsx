import type { Customer, CustomerStatus } from '@vaqtyar/shared';
import {
	SelectControl,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { ReactNode } from 'react';

import { screenOf } from '../catalog/crud';
import { SIZE } from '../catalog/fields';
import { ItemEditor } from '../catalog/ItemEditor';
import { NotFound } from '../NotFound';
import { useRoute } from '../router';
import { CustomerHistory } from './CustomerHistory';
import { CustomerList } from './CustomerList';

/**
 * A comma-separated list, as the form shows tags. Empty entries (a
 * trailing comma while typing) are dropped only on save, so the field does
 * not fight what the admin is typing.
 *
 * @param text
 */
function tagsOf( text: string ): string[] {
	return text
		.split( ',' )
		.map( ( tag ) => tag.trim() )
		.filter( ( tag ) => tag !== '' );
}

/**
 * The customers screen (T3.5): the list, a new customer, or one customer's
 * profile with their appointment history.
 */
export function CustomersPage(): ReactNode {
	const screen = screenOf( useRoute(), '/customers' );
	if ( screen === null ) {
		return <NotFound />;
	}
	if ( screen === 'list' ) {
		return <CustomerList />;
	}

	return (
		<ItemEditor< Customer >
			base="/customers"
			id={ screen }
			empty={ {
				// The server assigns the uuid; it ignores this one (not in
				// CustomerRoutes::fields()). ItemEditor's Draft<T> only
				// omits "id", and Customer has no other way to leave it out.
				uuid: '',
				first_name: '',
				last_name: '',
				phone: '',
				email: null,
				wp_user_id: null,
				birth_date: null,
				note: '',
				tags: [],
				status: 'active',
			} }
			after={ ( customer ) => (
				<CustomerHistory customerId={ customer.id } />
			) }
		>
			{ ( draft, change ) => (
				<>
					<TextControl
						{ ...SIZE }
						label={ __( 'First name', 'vaqtyar' ) }
						value={ draft.first_name }
						onChange={ ( value ) =>
							change( { first_name: value } )
						}
					/>
					<TextControl
						{ ...SIZE }
						label={ __( 'Last name', 'vaqtyar' ) }
						value={ draft.last_name }
						onChange={ ( value ) => change( { last_name: value } ) }
					/>
					<TextControl
						{ ...SIZE }
						type="tel"
						label={ __( 'Phone', 'vaqtyar' ) }
						help={ __( 'E.164, e.g. +989121234567.', 'vaqtyar' ) }
						value={ draft.phone }
						required
						onChange={ ( phone ) => change( { phone } ) }
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
						type="date"
						label={ __( 'Birth date', 'vaqtyar' ) }
						value={ draft.birth_date ?? '' }
						onChange={ ( value ) =>
							change( { birth_date: value } )
						}
					/>
					<TextControl
						{ ...SIZE }
						label={ __( 'Tags', 'vaqtyar' ) }
						help={ __( 'Comma-separated.', 'vaqtyar' ) }
						value={ draft.tags.join( ', ' ) }
						onChange={ ( text ) =>
							change( { tags: tagsOf( text ) } )
						}
					/>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __( 'Note', 'vaqtyar' ) }
						value={ draft.note }
						onChange={ ( note ) => change( { note } ) }
					/>
					<SelectControl
						{ ...SIZE }
						label={ __( 'Status', 'vaqtyar' ) }
						value={ draft.status }
						options={ [
							{
								value: 'active',
								label: __( 'Active', 'vaqtyar' ),
							},
							{
								value: 'blocked',
								label: __( 'Blocked', 'vaqtyar' ),
							},
						] }
						onChange={ ( status ) =>
							change( { status: status as CustomerStatus } )
						}
					/>
				</>
			) }
		</ItemEditor>
	);
}
