import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { ScheduleException, ScheduleOwnerType } from '@vaqtyar/shared';
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
import { DateField } from '../DateField';
import { useDate } from '../display';
import { errorMessage } from '../query';
import { SIZE } from './fields';

type Kind = ScheduleException[ 'kind' ];

/**
 * The dates a list of exceptions covers: today and the next 365 days, the
 * longest range the API reads (docs/api.md).
 *
 * @param first "YYYY-MM-DD"
 */
export function yearFrom( first: string ): { from: string; to: string } {
	const end = new Date( first + 'T00:00:00Z' );
	end.setUTCDate( end.getUTCDate() + 365 );

	return { from: first, to: end.toISOString().slice( 0, 10 ) };
}

/** Today in the browser's zone, "YYYY-MM-DD". */
function today(): string {
	const now = new Date();
	now.setMinutes( now.getMinutes() - now.getTimezoneOffset() );

	return now.toISOString().slice( 0, 10 );
}

function kindLabel( kind: Kind ): string {
	return {
		off: __( 'Time off', 'vaqtyar' ),
		extra: __( 'Extra hours', 'vaqtyar' ),
		blocked: __( 'Blocked', 'vaqtyar' ),
	}[ kind ];
}

/**
 * An owner's coming time off, extra hours and blocked time, one date each.
 *
 * @param props
 * @param props.ownerType
 * @param props.ownerId
 */
export function TimeOff( {
	ownerType,
	ownerId,
}: {
	ownerType: ScheduleOwnerType;
	ownerId: number;
} ) {
	const api = useApi();
	const showDate = useDate();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const range = yearFrom( today() );
	const key = [ '/schedule-exceptions', ownerType, ownerId ];
	const list = useQuery( {
		queryKey: key,
		queryFn: () =>
			api.get< ScheduleException[] >( '/schedule-exceptions', {
				owner_type: ownerType,
				owner_id: ownerId,
				...range,
			} ),
	} );
	const done = ( message: string ) => {
		void client.invalidateQueries( { queryKey: key } );
		void createSuccessNotice( message, { type: 'snackbar' } );
	};
	const remove = useMutation( {
		mutationFn: ( id: number ) =>
			api.delete< null >( `/schedule-exceptions/${ id }` ),
		onSuccess: () => done( __( 'Deleted.', 'vaqtyar' ) ),
	} );

	return (
		<section className="vqy-admin__panel">
			<h2>{ __( 'Time off and extra hours', 'vaqtyar' ) }</h2>
			{ list.isError && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( list.error ) }
				</Notice>
			) }
			{ list.isPending && <Spinner /> }
			{ list.data?.length === 0 && (
				<p className="vqy-admin__muted">
					{ __( 'Nothing planned.', 'vaqtyar' ) }
				</p>
			) }
			{ !! list.data?.length && (
				<ul className="vqy-admin__exceptions">
					{ list.data.map( ( exception ) => (
						<li key={ exception.id }>
							<span dir="ltr">
								{ showDate( exception.date ) }
							</span>{ ' ' }
							{ kindLabel( exception.kind ) }{ ' ' }
							{ exception.start === null
								? __( 'all day', 'vaqtyar' )
								: `${ exception.start }–${ exception.end }` }
							{ exception.note !== '' &&
								` · ${ exception.note }` }{ ' ' }
							<Button
								variant="link"
								isDestructive
								disabled={ remove.isPending }
								onClick={ () => remove.mutate( exception.id ) }
							>
								{ __( 'Delete', 'vaqtyar' ) }
							</Button>
						</li>
					) ) }
				</ul>
			) }
			<AddException
				ownerType={ ownerType }
				ownerId={ ownerId }
				onAdded={ () => done( __( 'Added.', 'vaqtyar' ) ) }
			/>
		</section>
	);
}

function AddException( {
	ownerType,
	ownerId,
	onAdded,
}: {
	ownerType: ScheduleOwnerType;
	ownerId: number;
	onAdded: () => void;
} ) {
	const api = useApi();
	const [ date, setDate ] = useState( today() );
	const [ kind, setKind ] = useState< Kind >( 'off' );
	const [ allDay, setAllDay ] = useState( true );
	const [ start, setStart ] = useState( '09:00' );
	const [ end, setEnd ] = useState( '17:00' );
	const [ note, setNote ] = useState( '' );
	// Extra hours are a range of the day by definition.
	const wholeDay = allDay && kind !== 'extra';
	const add = useMutation( {
		mutationFn: () =>
			api.post< ScheduleException >( '/schedule-exceptions', {
				owner_type: ownerType,
				owner_id: ownerId,
				date,
				kind,
				start: wholeDay ? null : start,
				end: wholeDay ? null : end,
				note,
			} ),
		onSuccess: () => {
			setNote( '' );
			onAdded();
		},
	} );
	const submit = ( event: FormEvent ) => {
		event.preventDefault();
		add.mutate();
	};

	return (
		<form className="vqy-admin__inline-form" onSubmit={ submit }>
			<DateField
				label={ __( 'Date', 'vaqtyar' ) }
				value={ date }
				onChange={ setDate }
			/>
			<SelectControl
				{ ...SIZE }
				label={ __( 'Kind', 'vaqtyar' ) }
				value={ kind }
				options={ ( [ 'off', 'extra', 'blocked' ] as Kind[] ).map(
					( value ) => ( {
						value,
						label: kindLabel( value ),
					} )
				) }
				onChange={ ( value ) => setKind( value as Kind ) }
			/>
			{ kind !== 'extra' && (
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'All day', 'vaqtyar' ) }
					checked={ allDay }
					onChange={ setAllDay }
				/>
			) }
			{ ! wholeDay && (
				<>
					<TextControl
						{ ...SIZE }
						type="time"
						label={ __( 'From', 'vaqtyar' ) }
						value={ start }
						onChange={ setStart }
					/>
					<TextControl
						{ ...SIZE }
						type="time"
						label={ __( 'To', 'vaqtyar' ) }
						value={ end }
						onChange={ setEnd }
					/>
				</>
			) }
			<TextControl
				{ ...SIZE }
				label={ __( 'Note', 'vaqtyar' ) }
				value={ note }
				onChange={ setNote }
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
