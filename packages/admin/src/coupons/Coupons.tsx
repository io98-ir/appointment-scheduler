import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { Coupon, CouponType } from '@vaqtyar/shared';
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import type { FormEvent } from 'react';

import { useApi } from '../api';
import { IntField, SIZE } from '../catalog/fields';
import { DateTimeField } from '../DateField';
import { errorMessage } from '../query';

const KEY = [ '/coupons' ];

function typeLabel( type: CouponType ): string {
	return type === 'percent'
		? __( 'Percent', 'vaqtyar' )
		: __( 'Fixed amount (IRR)', 'vaqtyar' );
}

function describe( coupon: Coupon ): string {
	const value =
		coupon.type === 'percent'
			? `${ coupon.value }%`
			: `${ coupon.value.toLocaleString( 'en-US' ) } IRR`;
	const uses =
		coupon.max_uses === null
			? `${ coupon.used }`
			: `${ coupon.used }/${ coupon.max_uses }`;
	const window = [ coupon.valid_from, coupon.valid_to ]
		.map( ( bound ) => bound?.slice( 0, 16 ).replace( 'T', ' ' ) ?? '…' )
		.join( ' → ' );

	return [
		value,
		`${ __( 'used', 'vaqtyar' ) } ${ uses }`,
		window,
		coupon.active ? __( 'active', 'vaqtyar' ) : __( 'inactive', 'vaqtyar' ),
	].join( ' — ' );
}

/**
 * A `datetime-local` value is in the admin's own timezone; the API takes UTC.
 *
 * @param value The input's value, or "" when cleared.
 */
function toUtc( value: string ): string | null {
	return value === '' ? null : new Date( value ).toISOString();
}

/**
 * The discount codes. Editing is delete-and-recreate, like `Fields`: a code
 * that was used keeps no history worth editing in place. `used` is counted by
 * bookings, never written here.
 */
export function Coupons() {
	const api = useApi();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const list = useQuery( {
		queryKey: KEY,
		queryFn: () => api.get< Coupon[] >( '/coupons' ),
	} );
	const done = ( message: string ) => {
		void client.invalidateQueries( { queryKey: KEY } );
		void createSuccessNotice( message, { type: 'snackbar' } );
	};
	const remove = useMutation( {
		mutationFn: ( id: number ) => api.delete< null >( `/coupons/${ id }` ),
		onSuccess: () => done( __( 'Deleted.', 'vaqtyar' ) ),
	} );

	return (
		<section className="vqy-admin__panel">
			<h2>{ __( 'Coupons', 'vaqtyar' ) }</h2>
			{ list.isError && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( list.error ) }
				</Notice>
			) }
			{ list.isPending && <Spinner /> }
			{ list.data?.length === 0 && (
				<p className="vqy-admin__muted">
					{ __( 'No coupons yet.', 'vaqtyar' ) }
				</p>
			) }
			{ !! list.data?.length && (
				<ul className="vqy-admin__exceptions">
					{ list.data.map( ( coupon ) => (
						<li key={ coupon.id }>
							<code>{ coupon.code }</code>
							{ ' — ' }
							{ describe( coupon ) }{ ' ' }
							<Button
								variant="link"
								isDestructive
								disabled={ remove.isPending }
								onClick={ () => remove.mutate( coupon.id ) }
							>
								{ __( 'Delete', 'vaqtyar' ) }
							</Button>
						</li>
					) ) }
				</ul>
			) }
			<AddCoupon onAdded={ () => done( __( 'Added.', 'vaqtyar' ) ) } />
		</section>
	);
}

function AddCoupon( { onAdded }: { onAdded: () => void } ) {
	const api = useApi();
	const [ code, setCode ] = useState( '' );
	const [ type, setType ] = useState< CouponType >( 'percent' );
	const [ value, setValue ] = useState( 10 );
	const [ maxUses, setMaxUses ] = useState( '' );
	const [ validFrom, setValidFrom ] = useState( '' );
	const [ validTo, setValidTo ] = useState( '' );
	const [ active, setActive ] = useState( true );
	const add = useMutation( {
		mutationFn: () =>
			api.post< Coupon >( '/coupons', {
				code,
				type,
				value,
				active,
				valid_from: toUtc( validFrom ),
				valid_to: toUtc( validTo ),
				max_uses:
					maxUses === '' ? null : Number.parseInt( maxUses, 10 ),
				service_ids: null,
			} ),
		onSuccess: () => {
			setCode( '' );
			setMaxUses( '' );
			setValidFrom( '' );
			setValidTo( '' );
			onAdded();
		},
	} );
	const submit = ( event: FormEvent ) => {
		event.preventDefault();
		add.mutate();
	};

	return (
		<form className="vqy-admin__inline-form" onSubmit={ submit }>
			<TextControl
				{ ...SIZE }
				label={ __( 'Coupon code', 'vaqtyar' ) }
				value={ code }
				required
				onChange={ setCode }
			/>
			<SelectControl
				{ ...SIZE }
				label={ __( 'Discount type', 'vaqtyar' ) }
				value={ type }
				options={ ( [ 'percent', 'fixed' ] as const ).map( ( v ) => ( {
					value: v,
					label: typeLabel( v ),
				} ) ) }
				onChange={ ( next ) => setType( next as CouponType ) }
			/>
			<IntField
				label={ __( 'Discount value', 'vaqtyar' ) }
				value={ value }
				min={ 1 }
				onChange={ setValue }
			/>
			<TextControl
				{ ...SIZE }
				type="number"
				min={ 1 }
				label={ __( 'Maximum uses (empty for unlimited)', 'vaqtyar' ) }
				value={ maxUses }
				onChange={ setMaxUses }
			/>
			<DateTimeField
				label={ __( 'Valid from', 'vaqtyar' ) }
				value={ validFrom }
				onChange={ setValidFrom }
			/>
			<DateTimeField
				label={ __( 'Valid until', 'vaqtyar' ) }
				value={ validTo }
				onChange={ setValidTo }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Active', 'vaqtyar' ) }
				checked={ active }
				onChange={ setActive }
			/>
			<Button
				variant="secondary"
				type="submit"
				isBusy={ add.isPending }
				disabled={ add.isPending }
			>
				{ __( 'Add coupon', 'vaqtyar' ) }
			</Button>
		</form>
	);
}
