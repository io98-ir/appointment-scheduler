import type { CatalogStatus, Location } from '@vaqtyar/shared';
import {
	BaseControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useId } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import type { Column } from './CatalogList';
import { useAll } from './crud';

/** The sizes of the components' next major version, as core's new screens use. */
export const SIZE = {
	__next40pxDefaultSize: true,
	__nextHasNoMarginBottom: true,
} as const;

/**
 * "" while the admin clears the field; a number otherwise.
 *
 * @param value A TextControl's value.
 * @param min   The smallest value the API takes.
 */
export function toInt( value: string, min = 0 ): number {
	const number = Number.parseInt( value, 10 );

	return Number.isNaN( number ) ? min : Math.max( min, number );
}

export function IntField( {
	label,
	value,
	onChange,
	min = 0,
	help,
}: {
	label: string;
	value: number;
	onChange: ( value: number ) => void;
	min?: number;
	help?: string;
} ) {
	return (
		<TextControl
			{ ...SIZE }
			type="number"
			min={ min }
			label={ label }
			help={ help }
			value={ String( value ) }
			onChange={ ( text ) => onChange( toInt( text, min ) ) }
		/>
	);
}

/**
 * "#rrggbb" through the browser's color picker.
 *
 * @param props
 * @param props.label
 * @param props.value
 * @param props.onChange
 */
export function ColorField( {
	label,
	value,
	onChange,
}: {
	label: string;
	value: string;
	onChange: ( value: string ) => void;
} ) {
	const id = useId();

	return (
		<BaseControl __nextHasNoMarginBottom id={ id } label={ label }>
			<input
				id={ id }
				type="color"
				className="vqy-admin__color"
				value={ value }
				onChange={ ( event ) => onChange( event.target.value ) }
			/>
		</BaseControl>
	);
}

export function StatusField( {
	value,
	onChange,
}: {
	value: CatalogStatus;
	onChange: ( value: CatalogStatus ) => void;
} ) {
	return (
		<ToggleControl
			__nextHasNoMarginBottom
			label={ __( 'Active: customers can book it', 'vaqtyar' ) }
			checked={ value === 'active' }
			onChange={ ( on ) => onChange( on ? 'active' : 'inactive' ) }
		/>
	);
}

/**
 * A location, or none ("every location" for staff and resources).
 * @param root0
 * @param root0.value
 * @param root0.onChange
 */
export function LocationField( {
	value,
	onChange,
}: {
	value: number | null;
	onChange: ( value: number | null ) => void;
} ) {
	const locations = useAll< Location >( '/locations' );

	return (
		<SelectControl
			{ ...SIZE }
			label={ __( 'Location', 'vaqtyar' ) }
			value={ value === null ? '' : String( value ) }
			options={ [
				{ value: '', label: __( 'Every location', 'vaqtyar' ) },
				...( locations.data ?? [] ).map( ( location ) => ( {
					value: String( location.id ),
					label: location.name,
				} ) ),
			] }
			onChange={ ( id ) => onChange( id === '' ? null : Number( id ) ) }
		/>
	);
}

/**
 * The status column of the catalog lists.
 */
export function statusColumn<
	T extends { status: CatalogStatus },
>(): Column< T > {
	return {
		id: 'status',
		label: __( 'Status', 'vaqtyar' ),
		render: ( item ) =>
			item.status === 'active'
				? __( 'Active', 'vaqtyar' )
				: __( 'Inactive', 'vaqtyar' ),
	};
}
