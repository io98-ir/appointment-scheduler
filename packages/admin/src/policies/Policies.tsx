import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type {
	CancellationConfig,
	PolicyResponse,
	PolicyType,
	RefundTier,
	RescheduleConfig,
} from '@vaqtyar/shared';
import { Button, Notice, Spinner, ToggleControl } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import type { FormEvent } from 'react';

import { useApi } from '../api';
import { IntField } from '../catalog/fields';
import { errorMessage } from '../query';

const DEFAULT_CANCELLATION: CancellationConfig = {
	notice_hours: 24,
	refund: [
		{ hours: 48, percent: 100 },
		{ hours: 24, percent: 50 },
	],
};

const DEFAULT_RESCHEDULE: RescheduleConfig = { notice_hours: 12, max_times: 1 };

/**
 * The cancellation and reschedule policy of a service, or the global one at
 * serviceId 0 (implementation-notes §4.12). A level left unset falls back
 * to the one below it, global to lenient (cancel and move freely until the
 * start, full refund).
 *
 * @param props
 * @param props.serviceId
 */
export function Policies( { serviceId }: { serviceId: number } ) {
	return (
		<section className="vqy-admin__panel">
			<h2>{ __( 'Cancellation and rescheduling', 'vaqtyar' ) }</h2>
			<CancellationForm serviceId={ serviceId } />
			<RescheduleForm serviceId={ serviceId } />
		</section>
	);
}

function usePolicy< Config >( type: PolicyType, serviceId: number ) {
	const api = useApi();

	return useQuery( {
		queryKey: [ '/policies', type, serviceId ],
		queryFn: () =>
			api.get< PolicyResponse< Config > >(
				`/policies/${ type }/${ serviceId }`
			),
	} );
}

function usePolicySave< Config >( type: PolicyType, serviceId: number ) {
	const api = useApi();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const key = [ '/policies', type, serviceId ];
	const done = ( message: string ) => {
		void client.invalidateQueries( { queryKey: key } );
		void createSuccessNotice( message, { type: 'snackbar' } );
	};
	const save = useMutation( {
		mutationFn: ( config: Config ) =>
			api.put< PolicyResponse< Config > >(
				`/policies/${ type }/${ serviceId }`,
				config
			),
		onSuccess: () => done( __( 'Saved.', 'vaqtyar' ) ),
	} );
	const clear = useMutation( {
		mutationFn: () =>
			api.delete< null >( `/policies/${ type }/${ serviceId }` ),
		onSuccess: () => done( __( 'Cleared.', 'vaqtyar' ) ),
	} );

	return { save, clear };
}

/**
 * The note under a policy that is not set at this level: what applies
 * instead, service falling back to global and global to lenient.
 *
 * @param serviceId
 */
function fallbackNote( serviceId: number ): string {
	return serviceId === 0
		? __(
				'Not set: cancelling and rescheduling are free until the start, fully refunded.',
				'vaqtyar'
			)
		: __( 'Not set: the global policy applies.', 'vaqtyar' );
}

function CancellationForm( { serviceId }: { serviceId: number } ) {
	const policy = usePolicy< CancellationConfig >( 'cancellation', serviceId );
	const { save, clear } = usePolicySave< CancellationConfig >(
		'cancellation',
		serviceId
	);
	const [ draft, setDraft ] =
		useState< CancellationConfig >( DEFAULT_CANCELLATION );
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
			<legend>{ __( 'Cancellation', 'vaqtyar' ) }</legend>
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
							{ fallbackNote( serviceId ) }
						</p>
					) }
					<NullableIntField
						label={ __(
							'Cancellation deadline (hours before the start)',
							'vaqtyar'
						) }
						unlimitedLabel={ __(
							'Any time before the start',
							'vaqtyar'
						) }
						value={ draft.notice_hours }
						defaultValue={ 24 }
						onChange={ ( noticeHours ) =>
							setDraft( { ...draft, notice_hours: noticeHours } )
						}
					/>
					<RefundTiers
						tiers={ draft.refund }
						onChange={ ( refund ) =>
							setDraft( { ...draft, refund } )
						}
					/>
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

function RescheduleForm( { serviceId }: { serviceId: number } ) {
	const policy = usePolicy< RescheduleConfig >( 'reschedule', serviceId );
	const { save, clear } = usePolicySave< RescheduleConfig >(
		'reschedule',
		serviceId
	);
	const [ draft, setDraft ] =
		useState< RescheduleConfig >( DEFAULT_RESCHEDULE );
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
			<legend>{ __( 'Rescheduling', 'vaqtyar' ) }</legend>
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
							{ fallbackNote( serviceId ) }
						</p>
					) }
					<NullableIntField
						label={ __(
							'Reschedule deadline (hours before the start)',
							'vaqtyar'
						) }
						unlimitedLabel={ __(
							'Any time before the start',
							'vaqtyar'
						) }
						value={ draft.notice_hours }
						defaultValue={ 12 }
						onChange={ ( noticeHours ) =>
							setDraft( { ...draft, notice_hours: noticeHours } )
						}
					/>
					<NullableIntField
						label={ __( 'Most times', 'vaqtyar' ) }
						unlimitedLabel={ __( 'No limit', 'vaqtyar' ) }
						min={ 1 }
						value={ draft.max_times }
						defaultValue={ 1 }
						onChange={ ( maxTimes ) =>
							setDraft( { ...draft, max_times: maxTimes } )
						}
					/>
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

/**
 * A number the admin can also set to "no limit" (null). defaultValue fills
 * the field back in when the admin turns the limit back on.
 *
 * @param props
 * @param props.label
 * @param props.unlimitedLabel
 * @param props.value
 * @param props.defaultValue
 * @param props.min
 * @param props.onChange
 */
function NullableIntField( {
	label,
	unlimitedLabel,
	value,
	defaultValue,
	min = 0,
	onChange,
}: {
	label: string;
	unlimitedLabel: string;
	value: number | null;
	defaultValue: number;
	min?: number;
	onChange: ( value: number | null ) => void;
} ) {
	return (
		<div className="vqy-admin__row">
			<ToggleControl
				__nextHasNoMarginBottom
				label={ unlimitedLabel }
				checked={ value === null }
				onChange={ ( on ) => onChange( on ? null : defaultValue ) }
			/>
			{ value !== null && (
				<IntField
					label={ label }
					min={ min }
					value={ value }
					onChange={ onChange }
				/>
			) }
		</div>
	);
}

/**
 * The refund ladder: cancelled at least this many hours before the start,
 * this percent of what was paid comes back. Most hours first, as the
 * server sorts it (CancellationPolicy).
 *
 * @param props
 * @param props.tiers
 * @param props.onChange
 */
function RefundTiers( {
	tiers,
	onChange,
}: {
	tiers: RefundTier[];
	onChange: ( tiers: RefundTier[] ) => void;
} ) {
	const change = ( index: number, patch: Partial< RefundTier > ) =>
		onChange(
			tiers.map( ( tier, i ) =>
				i === index ? { ...tier, ...patch } : tier
			)
		);

	return (
		<fieldset className="vqy-admin__panel">
			<legend>{ __( 'Refund', 'vaqtyar' ) }</legend>
			{ tiers.length === 0 && (
				<p className="vqy-admin__muted">
					{ __( 'Nothing is refunded.', 'vaqtyar' ) }
				</p>
			) }
			{ tiers.map( ( tier, index ) => (
				<div className="vqy-admin__row" key={ index }>
					<IntField
						label={ __( 'Hours before the start', 'vaqtyar' ) }
						value={ tier.hours }
						onChange={ ( hours ) => change( index, { hours } ) }
					/>
					<IntField
						label={ __( 'Refund percent', 'vaqtyar' ) }
						min={ 0 }
						value={ tier.percent }
						onChange={ ( percent ) =>
							change( index, {
								percent: Math.min( 100, percent ),
							} )
						}
					/>
					<Button
						variant="tertiary"
						isDestructive
						onClick={ () =>
							onChange( tiers.filter( ( _, i ) => i !== index ) )
						}
					>
						{ __( 'Remove', 'vaqtyar' ) }
					</Button>
				</div>
			) ) }
			<Button
				variant="secondary"
				onClick={ () =>
					onChange( [ ...tiers, { hours: 0, percent: 100 } ] )
				}
			>
				{ __( 'Add a tier', 'vaqtyar' ) }
			</Button>
		</fieldset>
	);
}
