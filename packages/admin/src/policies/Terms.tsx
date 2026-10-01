import type {
	ApprovalConfig,
	BookingWindowConfig,
	DepositConfig,
	PolicyType,
} from '@vaqtyar/shared';
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FormEvent, ReactNode } from 'react';

import { IntField, SIZE } from '../catalog/fields';
import { errorMessage } from '../query';
import { NullableIntField, usePolicy, usePolicySave } from './Policies';

const DEFAULT_DEPOSIT: DepositConfig = {
	kind: 'percent',
	value: 30,
	required: false,
};

/**
 * What a customer's own booking of a service asks of them besides its time:
 * what to pay online (a deposit, or all), whether staff approve it, and how
 * soon and far ahead it can be booked. A level left unset falls back to the
 * global one, and the global one to asking nothing.
 *
 * @param props
 * @param props.serviceId 0 for the global policy.
 */
export function Terms( { serviceId }: { serviceId: number } ) {
	return (
		<section className="vqy-admin__panel">
			<h2>{ __( 'Payment, approval and booking window', 'vaqtyar' ) }</h2>
			<DepositForm serviceId={ serviceId } />
			<ApprovalForm serviceId={ serviceId } />
			<WindowForm serviceId={ serviceId } />
		</section>
	);
}

function notSet( serviceId: number ): string {
	return serviceId === 0
		? __( 'Not set: nothing is asked.', 'vaqtyar' )
		: __( 'Not set: the global policy applies.', 'vaqtyar' );
}

/**
 * A policy's form: its query, the save and clear buttons and the note when
 * it is not set. The fields are the children, given the current draft.
 *
 * @param props
 * @param props.type
 * @param props.serviceId
 * @param props.title
 * @param props.initial
 * @param props.children
 */
function PolicyForm< Config >( {
	type,
	serviceId,
	title,
	initial,
	children,
}: {
	type: PolicyType;
	serviceId: number;
	title: string;
	initial: Config;
	children: ( draft: Config, set: ( next: Config ) => void ) => ReactNode;
} ) {
	const policy = usePolicy< Config >( type, serviceId );
	const { save, clear } = usePolicySave< Config >( type, serviceId );
	const [ draft, setDraft ] = useState< Config >( initial );
	const isSet = ( policy.data?.config ?? null ) !== null;
	useEffect( () => {
		if ( policy.data?.config ) {
			setDraft( policy.data.config );
		}
	}, [ policy.data ] );
	const submit = ( event: FormEvent ) => {
		event.preventDefault();
		save.mutate( draft );
	};

	return (
		<fieldset className="vqy-admin__panel">
			<legend>{ title }</legend>
			{ policy.isError && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( policy.error ) }
				</Notice>
			) }
			{ policy.isPending && <Spinner /> }
			{ ! policy.isPending && (
				<form onSubmit={ submit }>
					{ ! isSet && (
						<p className="vqy-admin__muted">
							{ notSet( serviceId ) }
						</p>
					) }
					{ children( draft, setDraft ) }
					<Button
						variant="secondary"
						type="submit"
						isBusy={ save.isPending }
						disabled={ save.isPending }
					>
						{ __( 'Save', 'vaqtyar' ) }
					</Button>
					{ isSet && (
						<Button
							variant="tertiary"
							isDestructive
							disabled={ clear.isPending }
							onClick={ () => clear.mutate() }
						>
							{ __( 'Clear', 'vaqtyar' ) }
						</Button>
					) }
				</form>
			) }
		</fieldset>
	);
}

function DepositForm( { serviceId }: { serviceId: number } ) {
	return (
		<PolicyForm< DepositConfig >
			type="deposit"
			serviceId={ serviceId }
			title={ __( 'Payment when booking online', 'vaqtyar' ) }
			initial={ DEFAULT_DEPOSIT }
		>
			{ ( draft, set ) => (
				<>
					<SelectControl
						{ ...SIZE }
						label={ __( 'The customer pays online', 'vaqtyar' ) }
						value={ draft.kind }
						options={ [
							{
								value: 'none',
								label: __( 'The whole price', 'vaqtyar' ),
							},
							{
								value: 'percent',
								label: __(
									'A deposit, a percent of the price',
									'vaqtyar'
								),
							},
							{
								value: 'fixed',
								label: __(
									'A deposit, a fixed amount',
									'vaqtyar'
								),
							},
						] }
						onChange={ ( kind ) =>
							set( {
								...draft,
								kind: kind as DepositConfig[ 'kind' ],
								value:
									kind === 'none'
										? 0
										: Math.max( draft.value, 1 ),
							} )
						}
					/>
					{ draft.kind !== 'none' && (
						<IntField
							label={
								draft.kind === 'percent'
									? __( 'Deposit percent', 'vaqtyar' )
									: __( 'Deposit amount (IRR)', 'vaqtyar' )
							}
							min={ 1 }
							value={ draft.value }
							onChange={ ( value ) =>
								set( {
									...draft,
									value:
										draft.kind === 'percent'
											? Math.min( 100, value )
											: value,
								} )
							}
						/>
					) }
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Customers must pay online to book',
							'vaqtyar'
						) }
						help={ __(
							'Without it, paying online stays their choice. The rest of a deposit is paid at the place, or from the customer panel.',
							'vaqtyar'
						) }
						checked={ draft.required }
						onChange={ ( required ) =>
							set( { ...draft, required } )
						}
					/>
				</>
			) }
		</PolicyForm>
	);
}

function ApprovalForm( { serviceId }: { serviceId: number } ) {
	return (
		<PolicyForm< ApprovalConfig >
			type="approval"
			serviceId={ serviceId }
			title={ __( 'Approval', 'vaqtyar' ) }
			initial={ { required: true } }
		>
			{ ( draft, set ) => (
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Staff approve each booking made from the booking form',
						'vaqtyar'
					) }
					help={ __(
						'The time is held at once; the booking is confirmed when you approve it from the appointment. Bookings you make yourself are never held back.',
						'vaqtyar'
					) }
					checked={ draft.required }
					onChange={ ( required ) => set( { required } ) }
				/>
			) }
		</PolicyForm>
	);
}

function WindowForm( { serviceId }: { serviceId: number } ) {
	return (
		<PolicyForm< BookingWindowConfig >
			type="booking_window"
			serviceId={ serviceId }
			title={ __( 'Booking window', 'vaqtyar' ) }
			initial={ { min_notice_min: null, max_advance_days: null } }
		>
			{ ( draft, set ) => (
				<>
					<NullableIntField
						label={ __( 'Minimum notice (minutes)', 'vaqtyar' ) }
						unlimitedLabel={ __(
							'Same minimum notice as the site',
							'vaqtyar'
						) }
						value={ draft.min_notice_min }
						defaultValue={ 60 }
						onChange={ ( minutes ) =>
							set( { ...draft, min_notice_min: minutes } )
						}
					/>
					<NullableIntField
						label={ __(
							'Open for booking up to (days ahead)',
							'vaqtyar'
						) }
						unlimitedLabel={ __(
							'Same opening time as the site',
							'vaqtyar'
						) }
						min={ 1 }
						value={ draft.max_advance_days }
						defaultValue={ 60 }
						onChange={ ( days ) =>
							set( { ...draft, max_advance_days: days } )
						}
					/>
				</>
			) }
		</PolicyForm>
	);
}
