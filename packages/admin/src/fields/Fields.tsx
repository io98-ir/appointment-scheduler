import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { FieldDefinition, FieldType } from '@vaqtyar/shared';
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
import { errorMessage } from '../query';

const TYPES: FieldType[] = [
	'text',
	'textarea',
	'number',
	'select',
	'checkbox',
];

function typeLabel( type: FieldType ): string {
	return {
		text: __( 'Text', 'vaqtyar' ),
		textarea: __( 'Multi-line text', 'vaqtyar' ),
		number: __( 'Number', 'vaqtyar' ),
		select: __( 'Choice', 'vaqtyar' ),
		checkbox: __( 'Checkbox', 'vaqtyar' ),
	}[ type ];
}

type Scope = { scope: 'global' } | { scope: 'service'; serviceId: number };

/**
 * The global custom fields, or one service's own (implementation-notes
 * §4.13). Editing is delete-and-recreate, like `TimeOff`: there is no
 * inline edit here either.
 *
 * @param props
 */
export function Fields( props: Scope ) {
	const api = useApi();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const key =
		props.scope === 'global'
			? [ '/fields', 'global' ]
			: [ '/fields', 'service', props.serviceId ];
	const list = useQuery( {
		queryKey: key,
		queryFn: () =>
			api.get< FieldDefinition[] >(
				'/fields',
				props.scope === 'global'
					? { scope: 'global' }
					: { scope: 'service', service_id: props.serviceId }
			),
	} );
	const done = ( message: string ) => {
		void client.invalidateQueries( { queryKey: key } );
		void createSuccessNotice( message, { type: 'snackbar' } );
	};
	const remove = useMutation( {
		mutationFn: ( id: number ) => api.delete< null >( `/fields/${ id }` ),
		onSuccess: () => done( __( 'Deleted.', 'vaqtyar' ) ),
	} );

	return (
		<section className="vqy-admin__panel">
			<h2>{ __( 'Custom fields', 'vaqtyar' ) }</h2>
			{ list.isError && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( list.error ) }
				</Notice>
			) }
			{ list.isPending && <Spinner /> }
			{ list.data?.length === 0 && (
				<p className="vqy-admin__muted">
					{ __( 'No custom fields yet.', 'vaqtyar' ) }
				</p>
			) }
			{ !! list.data?.length && (
				<ul className="vqy-admin__exceptions">
					{ list.data.map( ( field ) => (
						<li key={ field.id }>
							<code>{ field.field_key }</code>
							{ ' — ' }
							{ typeLabel( field.type ) }
							{ ' — ' }
							{ field.label }
							{ field.required &&
								` (${ __( 'required', 'vaqtyar' ) })` }
							{ field.show_if &&
								` — ${ __(
									'shown when',
									'vaqtyar'
								) } ${ field.show_if.field } = "${ field.show_if.equals }"` }{ ' ' }
							<Button
								variant="link"
								isDestructive
								disabled={ remove.isPending }
								onClick={ () => remove.mutate( field.id ) }
							>
								{ __( 'Delete', 'vaqtyar' ) }
							</Button>
						</li>
					) ) }
				</ul>
			) }
			<AddField
				{ ...props }
				onAdded={ () => done( __( 'Added.', 'vaqtyar' ) ) }
			/>
		</section>
	);
}

function AddField( props: Scope & { onAdded: () => void } ) {
	const api = useApi();
	const [ key, setKey ] = useState( '' );
	const [ type, setType ] = useState< FieldType >( 'text' );
	const [ label, setLabel ] = useState( '' );
	const [ required, setRequired ] = useState( false );
	const [ options, setOptions ] = useState( '' );
	const [ showIfKey, setShowIfKey ] = useState( '' );
	const [ showIfEquals, setShowIfEquals ] = useState( '' );
	const [ sort, setSort ] = useState( 0 );
	const add = useMutation( {
		mutationFn: () =>
			api.post< FieldDefinition >( '/fields', {
				scope: props.scope,
				service_id: props.scope === 'service' ? props.serviceId : null,
				field_key: key,
				type,
				label,
				required,
				options:
					type === 'select'
						? options
								.split( ',' )
								.map( ( option ) => option.trim() )
								.filter( ( option ) => option !== '' )
						: [],
				show_if:
					showIfKey.trim() === ''
						? null
						: { field: showIfKey.trim(), equals: showIfEquals },
				sort,
			} ),
		onSuccess: () => {
			setKey( '' );
			setLabel( '' );
			setOptions( '' );
			setShowIfKey( '' );
			setShowIfEquals( '' );
			props.onAdded();
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
				label={ __( 'Key', 'vaqtyar' ) }
				value={ key }
				required
				onChange={ setKey }
			/>
			<SelectControl
				{ ...SIZE }
				label={ __( 'Type', 'vaqtyar' ) }
				value={ type }
				options={ TYPES.map( ( value ) => ( {
					value,
					label: typeLabel( value ),
				} ) ) }
				onChange={ ( value ) => setType( value as FieldType ) }
			/>
			<TextControl
				{ ...SIZE }
				label={ __( 'Label', 'vaqtyar' ) }
				value={ label }
				required
				onChange={ setLabel }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Required', 'vaqtyar' ) }
				checked={ required }
				onChange={ setRequired }
			/>
			{ type === 'select' && (
				<TextControl
					{ ...SIZE }
					label={ __( 'Options (comma separated)', 'vaqtyar' ) }
					value={ options }
					onChange={ setOptions }
				/>
			) }
			<TextControl
				{ ...SIZE }
				label={ __( 'Show only when field…', 'vaqtyar' ) }
				value={ showIfKey }
				onChange={ setShowIfKey }
			/>
			{ showIfKey.trim() !== '' && (
				<TextControl
					{ ...SIZE }
					label={ __( '…equals', 'vaqtyar' ) }
					value={ showIfEquals }
					onChange={ setShowIfEquals }
				/>
			) }
			<IntField
				label={ __( 'Sort', 'vaqtyar' ) }
				value={ sort }
				onChange={ setSort }
			/>
			<Button
				variant="secondary"
				type="submit"
				isBusy={ add.isPending }
				disabled={ add.isPending }
			>
				{ __( 'Add', 'vaqtyar' ) }
			</Button>
		</form>
	);
}
