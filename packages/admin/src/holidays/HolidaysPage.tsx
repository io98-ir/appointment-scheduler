import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { formatDate, type Holiday } from '@vaqtyar/shared';
import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import type { FormEvent } from 'react';

import { useApi } from '../api';
import { SIZE } from '../catalog/fields';
import { errorMessage } from '../query';

const DEFAULT_CALENDAR = 'ir';

/** Today in the browser's zone, "YYYY-MM-DD". */
function today(): string {
	const now = new Date();
	now.setMinutes( now.getMinutes() - now.getTimezoneOffset() );

	return now.toISOString().slice( 0, 10 );
}

/**
 * The dates a list of holidays covers: today and the next 365 days, the
 * longest range the API reads (docs/api.md).
 *
 * @param first "YYYY-MM-DD"
 */
function yearFrom( first: string ): { from: string; to: string } {
	const end = new Date( first + 'T00:00:00Z' );
	end.setUTCDate( end.getUTCDate() + 365 );

	return { from: first, to: end.toISOString().slice( 0, 10 ) };
}

/**
 * A holiday calendar's coming days off. A location names the calendar it
 * uses in its own settings; this screen edits any calendar by that key, so
 * it works before a location has picked one.
 */
export function HolidaysPage() {
	const [ calendar, setCalendar ] = useState( DEFAULT_CALENDAR );
	const api = useApi();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const range = yearFrom( today() );
	const key = [ '/holidays', calendar ];
	const list = useQuery( {
		queryKey: key,
		queryFn: () =>
			api.get< Holiday[] >( '/holidays', { calendar, ...range } ),
	} );
	const done = ( message: string ) => {
		void client.invalidateQueries( { queryKey: key } );
		void createSuccessNotice( message, { type: 'snackbar' } );
	};
	const remove = useMutation( {
		mutationFn: ( date: string ) =>
			api.delete< null >( `/holidays/${ calendar }/${ date }` ),
		onSuccess: () => done( __( 'Deleted.', 'vaqtyar' ) ),
	} );

	return (
		<section className="vqy-admin__panel">
			<TextControl
				{ ...SIZE }
				label={ __( 'Calendar', 'vaqtyar' ) }
				help={ __(
					'The key a location’s holiday calendar setting uses, e.g. "ir".',
					'vaqtyar'
				) }
				value={ calendar }
				onChange={ ( value ) =>
					setCalendar( value.trim().toLowerCase() )
				}
			/>
			{ list.isError && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( list.error ) }
				</Notice>
			) }
			{ list.isPending && <Spinner /> }
			{ list.data?.length === 0 && (
				<p className="vqy-admin__muted">
					{ __( 'No holidays in this calendar yet.', 'vaqtyar' ) }
				</p>
			) }
			{ !! list.data?.length && (
				<ul className="vqy-admin__exceptions">
					{ list.data.map( ( holiday ) => (
						<li key={ holiday.date }>
							<span dir="ltr">
								{ formatDate(
									holiday.date,
									'jalali',
									'latin'
								) }
							</span>{ ' ' }
							{ holiday.title }
							{ holiday.source === 'dataset' &&
								` (${ __(
									'from the shipped calendar',
									'vaqtyar'
								) })` }{ ' ' }
							<Button
								variant="link"
								isDestructive
								disabled={ remove.isPending }
								onClick={ () => remove.mutate( holiday.date ) }
							>
								{ __( 'Delete', 'vaqtyar' ) }
							</Button>
						</li>
					) ) }
				</ul>
			) }
			<AddHoliday
				calendar={ calendar }
				onAdded={ () => done( __( 'Saved.', 'vaqtyar' ) ) }
			/>
		</section>
	);
}

function AddHoliday( {
	calendar,
	onAdded,
}: {
	calendar: string;
	onAdded: () => void;
} ) {
	const api = useApi();
	const [ date, setDate ] = useState( today() );
	const [ title, setTitle ] = useState( '' );
	const add = useMutation( {
		mutationFn: () =>
			api.post< Holiday >( '/holidays', { calendar, date, title } ),
		onSuccess: () => {
			setTitle( '' );
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
				type="date"
				label={ __( 'Date', 'vaqtyar' ) }
				help={
					date !== ''
						? formatDate( date, 'jalali', 'latin' )
						: undefined
				}
				value={ date }
				required
				onChange={ setDate }
			/>
			<TextControl
				{ ...SIZE }
				label={ __( 'Title', 'vaqtyar' ) }
				value={ title }
				required
				onChange={ setTitle }
			/>
			<Button
				variant="secondary"
				type="submit"
				isBusy={ add.isPending }
				disabled={ add.isPending || title.trim() === '' }
			>
				{ __( 'Add', 'vaqtyar' ) }
			</Button>
		</form>
	);
}
