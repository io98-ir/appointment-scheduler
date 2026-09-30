import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { TimeRule } from '@vaqtyar/shared';
import {
	Button,
	CheckboxControl,
	Notice,
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
import { DateField } from '../DateField';
import { errorMessage } from '../query';

const KEY = [ '/time-rules' ];

/** The API's weekday numbering: 0 is Saturday, 6 is Friday. */
function weekdayNames(): string[] {
	return [
		__( 'Saturday', 'vaqtyar' ),
		__( 'Sunday', 'vaqtyar' ),
		__( 'Monday', 'vaqtyar' ),
		__( 'Tuesday', 'vaqtyar' ),
		__( 'Wednesday', 'vaqtyar' ),
		__( 'Thursday', 'vaqtyar' ),
		__( 'Friday', 'vaqtyar' ),
	];
}

function describe( rule: TimeRule ): string {
	const names = weekdayNames();
	const days =
		rule.weekdays.length === 0
			? __( 'every day', 'vaqtyar' )
			: rule.weekdays.map( ( day ) => names[ day ] ).join( ', ' );
	const dates = [ rule.valid_from, rule.valid_to ].some( Boolean )
		? ` — ${ rule.valid_from ?? '…' } → ${ rule.valid_to ?? '…' }`
		: '';
	const sign = rule.percent > 0 ? '+' : '';

	return [
		`${ sign }${ rule.percent }%`,
		`${ days } ${ rule.from }–${ rule.to }${ dates }`,
		rule.active ? __( 'active', 'vaqtyar' ) : __( 'inactive', 'vaqtyar' ),
	].join( ' — ' );
}

/**
 * The global time-based price rules (e.g. +20% on Friday evenings). Editing is
 * delete-and-recreate, like `Coupons`. A rule for one service is API-only for
 * now: this form always sends `service_id: null`.
 */
export function TimeRules() {
	const api = useApi();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const list = useQuery( {
		queryKey: KEY,
		queryFn: () => api.get< TimeRule[] >( '/time-rules' ),
	} );
	const done = ( message: string ) => {
		void client.invalidateQueries( { queryKey: KEY } );
		void createSuccessNotice( message, { type: 'snackbar' } );
	};
	const remove = useMutation( {
		mutationFn: ( id: number ) =>
			api.delete< null >( `/time-rules/${ id }` ),
		onSuccess: () => done( __( 'Deleted.', 'vaqtyar' ) ),
	} );

	return (
		<section className="vqy-admin__panel">
			<h2>{ __( 'Time-based prices', 'vaqtyar' ) }</h2>
			{ list.isError && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( list.error ) }
				</Notice>
			) }
			{ list.isPending && <Spinner /> }
			{ list.data?.length === 0 && (
				<p className="vqy-admin__muted">
					{ __( 'No time-based prices yet.', 'vaqtyar' ) }
				</p>
			) }
			{ !! list.data?.length && (
				<ul className="vqy-admin__exceptions">
					{ list.data.map( ( rule ) => (
						<li key={ rule.id }>
							{ describe( rule ) }{ ' ' }
							<Button
								variant="link"
								isDestructive
								disabled={ remove.isPending }
								onClick={ () => remove.mutate( rule.id ) }
							>
								{ __( 'Delete', 'vaqtyar' ) }
							</Button>
						</li>
					) ) }
				</ul>
			) }
			<AddTimeRule onAdded={ () => done( __( 'Added.', 'vaqtyar' ) ) } />
		</section>
	);
}

function AddTimeRule( { onAdded }: { onAdded: () => void } ) {
	const api = useApi();
	const [ weekdays, setWeekdays ] = useState< number[] >( [] );
	const [ from, setFrom ] = useState( '18:00' );
	const [ to, setTo ] = useState( '22:00' );
	const [ validFrom, setValidFrom ] = useState( '' );
	const [ validTo, setValidTo ] = useState( '' );
	const [ percent, setPercent ] = useState( 10 );
	const [ priority, setPriority ] = useState( 0 );
	const [ active, setActive ] = useState( true );
	const add = useMutation( {
		mutationFn: () =>
			api.post< TimeRule >( '/time-rules', {
				service_id: null,
				priority,
				active,
				weekdays: [ ...weekdays ].sort( ( a, b ) => a - b ),
				from,
				to,
				valid_from: validFrom === '' ? null : validFrom,
				valid_to: validTo === '' ? null : validTo,
				percent,
			} ),
		onSuccess: () => {
			setWeekdays( [] );
			setValidFrom( '' );
			setValidTo( '' );
			onAdded();
		},
	} );
	const submit = ( event: FormEvent ) => {
		event.preventDefault();
		add.mutate();
	};
	const toggle = ( day: number, checked: boolean ) =>
		setWeekdays( ( current ) =>
			checked
				? [ ...current, day ]
				: current.filter( ( other ) => other !== day )
		);

	return (
		<form className="vqy-admin__inline-form" onSubmit={ submit }>
			<fieldset>
				<legend>
					{ __( 'Weekdays (none means every day)', 'vaqtyar' ) }
				</legend>
				{ weekdayNames().map( ( name, day ) => (
					<CheckboxControl
						key={ day }
						__nextHasNoMarginBottom
						label={ name }
						checked={ weekdays.includes( day ) }
						onChange={ ( checked ) => toggle( day, checked ) }
					/>
				) ) }
			</fieldset>
			<TextControl
				{ ...SIZE }
				type="time"
				label={ __( 'Starts from', 'vaqtyar' ) }
				value={ from }
				required
				onChange={ setFrom }
			/>
			<TextControl
				{ ...SIZE }
				type="time"
				label={ __( 'Starts before', 'vaqtyar' ) }
				value={ to }
				required
				onChange={ setTo }
			/>
			<DateField
				label={ __( 'Applies from date', 'vaqtyar' ) }
				value={ validFrom }
				onChange={ setValidFrom }
			/>
			<DateField
				label={ __( 'Applies until date', 'vaqtyar' ) }
				value={ validTo }
				onChange={ setValidTo }
			/>
			<IntField
				label={ __(
					'Price change (%, negative for a discount)',
					'vaqtyar'
				) }
				value={ percent }
				min={ -100 }
				onChange={ setPercent }
			/>
			<IntField
				label={ __( 'Priority', 'vaqtyar' ) }
				value={ priority }
				onChange={ setPriority }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Rule active', 'vaqtyar' ) }
				checked={ active }
				onChange={ setActive }
			/>
			<Button
				variant="secondary"
				type="submit"
				isBusy={ add.isPending }
				disabled={ add.isPending }
			>
				{ __( 'Add time-based price', 'vaqtyar' ) }
			</Button>
		</form>
	);
}
