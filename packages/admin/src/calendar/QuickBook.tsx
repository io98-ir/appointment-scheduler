import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type {
	AvailabilityDay,
	Customer,
	Location,
	PlacedHold,
	Service,
	Staff,
} from '@vaqtyar/shared';
import {
	Button,
	Modal,
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
import { SIZE } from '../catalog/fields';
import { errorMessage } from '../query';
import { wallClock } from './time';

/** Where staff clicked: a date and time of the location, and a column. */
export interface Slot {
	date: string;
	minutes: number;
	staffId: number;
}

/**
 * The start closest to where staff clicked.
 *
 * @param starts  ISO times, as availability gives them.
 * @param minutes The click.
 */
export function nearest( starts: string[], minutes: number ): string {
	let best = starts[ 0 ] ?? '';
	for ( const start of starts ) {
		if (
			Math.abs( wallClock( start ).minutes - minutes ) <
			Math.abs( wallClock( best ).minutes - minutes )
		) {
			best = start;
		}
	}

	return best;
}

/**
 * Books a free start for a customer: the same hold and confirm the widget
 * uses (POST /holds, POST /bookings), so the locks and the policy apply as
 * for any booking. Starts come from availability, so only real ones are
 * offered.
 *
 * @param props
 * @param props.slot
 * @param props.location
 * @param props.staff
 * @param props.services
 * @param props.onClose
 */
export function QuickBook( {
	slot,
	location,
	staff,
	services,
	onClose,
}: {
	slot: Slot;
	location: Location;
	staff: Staff[];
	services: Service[];
	onClose: () => void;
} ) {
	const api = useApi();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const [ staffId, setStaffId ] = useState( slot.staffId );
	const [ date, setDate ] = useState( slot.date );
	const offered = services.filter(
		( service ) =>
			service.status === 'active' &&
			service.staff.some( ( item ) => item.staff_id === staffId )
	);
	const [ serviceId, setServiceId ] = useState< number | null >( null );
	const service =
		offered.find( ( item ) => item.id === serviceId ) ?? offered[ 0 ];
	const [ variantId, setVariantId ] = useState< number | null >( null );
	const variant =
		service?.variants.find( ( item ) => item.id === variantId ) ??
		service?.variants.find( ( item ) => item.is_default ) ??
		service?.variants[ 0 ];
	const [ chosenStart, setStart ] = useState< string | null >( null );
	const [ search, setSearch ] = useState( '' );
	const [ customerId, setCustomerId ] = useState< number | null >( null );
	const [ isNew, setIsNew ] = useState( false );
	const [ name, setName ] = useState( '' );
	const [ phone, setPhone ] = useState( '' );

	const availability = useQuery( {
		queryKey: [ '/availability', variant?.id, location.id, staffId, date ],
		queryFn: () =>
			api.get< AvailabilityDay >( '/availability', {
				variant: variant?.id ?? 0,
				location: location.id,
				staff: staffId,
				date,
			} ),
		enabled: typeof variant?.id === 'number',
	} );
	const starts = ( availability.data?.slots ?? [] ).map(
		( item ) => item.start
	);
	const start =
		chosenStart !== null && starts.includes( chosenStart )
			? chosenStart
			: nearest( starts, date === slot.date ? slot.minutes : 0 );
	const customers = useQuery( {
		queryKey: [ '/customers', search ],
		queryFn: () =>
			api.get< Customer[] >( '/customers', { search, per_page: 10 } ),
		enabled: ! isNew && search.trim() !== '',
	} );
	const found = customers.data ?? [];
	const customer =
		found.find( ( item ) => item.id === customerId ) ?? found[ 0 ];

	const book = useMutation( {
		mutationFn: async () => {
			// The hold first: a start taken meanwhile leaves no customer behind.
			const hold = await api.post< PlacedHold >( '/holds', {
				variant: variant?.id,
				location: location.id,
				staff: staffId,
				start,
			} );
			let id = customer?.id;
			if ( isNew ) {
				const [ first, ...rest ] = name.trim().split( /\s+/ );
				id = (
					await api.post< Customer >( '/customers', {
						first_name: first ?? '',
						last_name: rest.join( ' ' ),
						phone,
					} )
				).id;
			}
			await api.post( '/bookings', {
				hold_token: hold.token,
				customer_id: id,
			} );
		},
		onSuccess: () => {
			void client.invalidateQueries( { queryKey: [ '/calendar' ] } );
			void createSuccessNotice( __( 'Appointment booked.', 'vaqtyar' ), {
				type: 'snackbar',
			} );
			onClose();
		},
		// A start taken meanwhile: offer fresh ones.
		onError: () =>
			void client.invalidateQueries( { queryKey: [ '/availability' ] } ),
	} );
	const ready =
		start !== '' && ( isNew ? name.trim() !== '' : customer !== undefined );
	const submit = ( event: FormEvent ) => {
		event.preventDefault();
		if ( ready ) {
			book.mutate();
		}
	};

	return (
		<Modal
			title={ __( 'New appointment', 'vaqtyar' ) }
			onRequestClose={ onClose }
		>
			<form className="vqy-admin__form" onSubmit={ submit }>
				<SelectControl
					{ ...SIZE }
					label={ __( 'Staff', 'vaqtyar' ) }
					value={ String( staffId ) }
					options={ staff.map( ( person ) => ( {
						value: String( person.id ),
						label: person.name,
					} ) ) }
					onChange={ ( value ) => setStaffId( Number( value ) ) }
				/>
				{ service === undefined ? (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'This staff member serves no active service.',
							'vaqtyar'
						) }
					</Notice>
				) : (
					<>
						<SelectControl
							{ ...SIZE }
							label={ __( 'Service', 'vaqtyar' ) }
							value={ String( service.id ) }
							options={ offered.map( ( item ) => ( {
								value: String( item.id ),
								label: item.name,
							} ) ) }
							onChange={ ( value ) =>
								setServiceId( Number( value ) )
							}
						/>
						{ service.variants.length > 1 && (
							<SelectControl
								{ ...SIZE }
								label={ __( 'Option', 'vaqtyar' ) }
								value={ String( variant?.id ) }
								options={ service.variants.map( ( item ) => ( {
									value: String( item.id ),
									label: item.label || service.name,
								} ) ) }
								onChange={ ( value ) =>
									setVariantId( Number( value ) )
								}
							/>
						) }
					</>
				) }
				<TextControl
					{ ...SIZE }
					type="date"
					label={ __( 'Date', 'vaqtyar' ) }
					value={ date }
					required
					onChange={ setDate }
				/>
				{ availability.isError && (
					<Notice status="error" isDismissible={ false }>
						{ errorMessage( availability.error ) }
					</Notice>
				) }
				{ availability.isFetching && <Spinner /> }
				{ availability.data && starts.length === 0 && (
					<p className="vqy-admin__muted">
						{ __( 'No free start on this day.', 'vaqtyar' ) }
					</p>
				) }
				{ starts.length > 0 && (
					<SelectControl
						{ ...SIZE }
						label={ __( 'Start', 'vaqtyar' ) }
						value={ start }
						options={ starts.map( ( item ) => ( {
							value: item,
							label: item.slice( 11, 16 ),
						} ) ) }
						onChange={ setStart }
					/>
				) }
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'New customer', 'vaqtyar' ) }
					checked={ isNew }
					onChange={ setIsNew }
				/>
				{ isNew ? (
					<>
						<TextControl
							{ ...SIZE }
							label={ __( 'Customer name', 'vaqtyar' ) }
							value={ name }
							required
							onChange={ setName }
						/>
						<TextControl
							{ ...SIZE }
							type="tel"
							label={ __( 'Phone', 'vaqtyar' ) }
							value={ phone }
							required
							onChange={ setPhone }
						/>
					</>
				) : (
					<>
						<TextControl
							{ ...SIZE }
							type="search"
							label={ __( 'Find customer', 'vaqtyar' ) }
							help={ __( 'Name, phone or email.', 'vaqtyar' ) }
							value={ search }
							onChange={ setSearch }
						/>
						{ found.length > 0 && customer && (
							<SelectControl
								{ ...SIZE }
								label={ __( 'Customer', 'vaqtyar' ) }
								value={ String( customer.id ) }
								options={ found.map( ( item ) => ( {
									value: String( item.id ),
									label: `${ item.first_name } ${ item.last_name } · ${ item.phone }`,
								} ) ) }
								onChange={ ( value ) =>
									setCustomerId( Number( value ) )
								}
							/>
						) }
						{ customers.data?.length === 0 && (
							<p className="vqy-admin__muted">
								{ __( 'No customer found.', 'vaqtyar' ) }
							</p>
						) }
					</>
				) }
				<div className="vqy-admin__buttons">
					<Button
						variant="primary"
						type="submit"
						isBusy={ book.isPending }
						disabled={ book.isPending || ! ready }
					>
						{ __( 'Book', 'vaqtyar' ) }
					</Button>
					<Button variant="tertiary" onClick={ onClose }>
						{ __( 'Cancel', 'vaqtyar' ) }
					</Button>
				</div>
			</form>
		</Modal>
	);
}
