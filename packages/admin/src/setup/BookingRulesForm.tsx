import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { BookingRules } from '@vaqtyar/shared';
import { Button, Notice, SelectControl, Spinner } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import type { FormEvent } from 'react';

import { useApi } from '../api';
import { IntField, SIZE } from '../catalog/fields';
import { errorMessage } from '../query';

/**
 * The site-wide rules for what a customer may book: how far apart start
 * times are, how soon and how far ahead booking is open, and who gets a
 * booking the customer left open. A service's own duration step, where it
 * has one, still wins over the first.
 */
export function BookingRulesForm() {
	const api = useApi();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const stored = useQuery( {
		queryKey: [ '/booking-rules' ],
		queryFn: () => api.get< BookingRules >( '/booking-rules' ),
	} );
	const [ draft, setDraft ] = useState< BookingRules | null >( null );
	useEffect( () => {
		if ( stored.data ) {
			setDraft( stored.data );
		}
	}, [ stored.data ] );

	const save = useMutation( {
		mutationFn: ( rules: BookingRules ) =>
			api.put< BookingRules >( '/booking-rules', rules ),
		onSuccess: ( saved ) => {
			client.setQueryData( [ '/booking-rules' ], saved );
			// The times a customer is offered were computed under the old rules.
			void client.invalidateQueries( { queryKey: [ '/availability' ] } );
			void createSuccessNotice( __( 'Saved.', 'vaqtyar' ), {
				type: 'snackbar',
			} );
		},
	} );
	const submit = ( event: FormEvent ) => {
		event.preventDefault();
		if ( draft ) {
			save.mutate( draft );
		}
	};

	if ( stored.isError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ errorMessage( stored.error ) }
			</Notice>
		);
	}
	if ( draft === null ) {
		return <Spinner />;
	}

	return (
		<form className="vqy-admin__form" onSubmit={ submit }>
			<IntField
				label={ __( 'Start times every (minutes)', 'vaqtyar' ) }
				help={ __(
					'How far apart the start times a customer can pick are.',
					'vaqtyar'
				) }
				min={ 1 }
				value={ draft.slot_step_min }
				onChange={ ( minutes ) =>
					setDraft( { ...draft, slot_step_min: minutes } )
				}
			/>
			<IntField
				label={ __( 'Minimum notice (minutes)', 'vaqtyar' ) }
				help={ __(
					'Booking closes this long before the start. 0 allows booking right up to the start.',
					'vaqtyar'
				) }
				value={ draft.min_notice_min }
				onChange={ ( minutes ) =>
					setDraft( { ...draft, min_notice_min: minutes } )
				}
			/>
			<IntField
				label={ __( 'Open for booking up to (days ahead)', 'vaqtyar' ) }
				min={ 1 }
				value={ draft.max_advance_days }
				onChange={ ( days ) =>
					setDraft( { ...draft, max_advance_days: days } )
				}
			/>
			<SelectControl
				{ ...SIZE }
				label={ __(
					'When the customer does not choose staff',
					'vaqtyar'
				) }
				value={ draft.staff_choice }
				options={ [
					{
						value: 'least_busy',
						label: __( 'The least busy that day', 'vaqtyar' ),
					},
					{
						value: 'priority',
						label: __(
							'In the order of the staff list',
							'vaqtyar'
						),
					},
				] }
				onChange={ ( choice ) =>
					setDraft( {
						...draft,
						staff_choice: choice as BookingRules[ 'staff_choice' ],
					} )
				}
			/>
			<div className="vqy-admin__buttons">
				<Button
					variant="primary"
					type="submit"
					isBusy={ save.isPending }
					disabled={ save.isPending }
				>
					{ __( 'Save booking rules', 'vaqtyar' ) }
				</Button>
			</div>
		</form>
	);
}
