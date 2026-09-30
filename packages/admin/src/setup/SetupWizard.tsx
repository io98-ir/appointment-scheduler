import { useMutation, useQueryClient } from '@tanstack/react-query';
import {
	SLUG,
	type Location,
	type ScheduleRule,
	type Service,
	type Staff,
} from '@vaqtyar/shared';
import { Button, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FormEvent, ReactNode } from 'react';

import { useApi } from '../api';
import { SIZE, toInt } from '../catalog/fields';
import { newVariant } from '../catalog/service-draft';
import { BrandForm } from './BrandForm';
import { DisplayForm } from './DisplayForm';
import { PaymentSettingsForm } from './PaymentSettingsForm';
import { SmsSettingsForm } from './SmsSettingsForm';

/**
 * The working week of a new business: the same hours Saturday to Thursday,
 * Friday closed (0 = Saturday … 6 = Friday, as the API counts).
 *
 * @param start "HH:MM".
 * @param end   "HH:MM".
 */
export function defaultWeek( start: string, end: string ): ScheduleRule[] {
	return [ 0, 1, 2, 3, 4, 5 ].map( ( weekday ) => ( {
		weekday,
		start,
		end,
		kind: 'work',
	} ) );
}

type StepId =
	'display' | 'brand' | 'place' | 'service' | 'sms' | 'payments' | 'done';

const STEPS: Array< { id: StepId; title: () => string } > = [
	{
		id: 'display',
		title: () => __( 'Language and calendar', 'vaqtyar' ),
	},
	{ id: 'brand', title: () => __( 'Your brand', 'vaqtyar' ) },
	{ id: 'place', title: () => __( 'Location and hours', 'vaqtyar' ) },
	{ id: 'service', title: () => __( 'First service', 'vaqtyar' ) },
	{ id: 'sms', title: () => __( 'SMS', 'vaqtyar' ) },
	{ id: 'payments', title: () => __( 'Online payment', 'vaqtyar' ) },
	{ id: 'done', title: () => __( 'Finish', 'vaqtyar' ) },
];

/**
 * The setup wizard (T6.1): brand, a location with its hours, a first
 * service with one staff member, then the optional SMS and payment
 * settings. Every step can be skipped, and each screen it uses also lives
 * under its own menu entry, so nothing here is the only way to a setting.
 * What a step created is kept in memory, so going back and forth does not
 * create it twice.
 */
export function SetupWizard() {
	const [ index, setIndex ] = useState( 0 );
	const [ locationId, setLocationId ] = useState< number | null >( null );
	const [ serviceName, setServiceName ] = useState< string | null >( null );
	const step = STEPS[ index ] as ( typeof STEPS )[ number ];
	const next = () => setIndex( ( i ) => Math.min( i + 1, STEPS.length - 1 ) );

	return (
		<div className="vqy-admin__wizard">
			<ol className="vqy-admin__steps">
				{ STEPS.map( ( item, i ) => (
					<li
						key={ item.id }
						aria-current={ i === index ? 'step' : undefined }
					>
						{ item.title() }
					</li>
				) ) }
			</ol>
			<section className="vqy-admin__panel">
				<h2>{ step.title() }</h2>
				{ step.id === 'display' && <DisplayForm onSaved={ next } /> }
				{ step.id === 'brand' && <BrandForm onSaved={ next } /> }
				{ step.id === 'place' && (
					<PlaceStep
						created={ locationId !== null }
						onCreated={ setLocationId }
						onDone={ next }
					/>
				) }
				{ step.id === 'service' && (
					<ServiceStep
						locationId={ locationId }
						created={ serviceName }
						onCreated={ setServiceName }
						onDone={ next }
					/>
				) }
				{ step.id === 'sms' && <SmsSettingsForm onSaved={ next } /> }
				{ step.id === 'payments' && (
					<PaymentSettingsForm onSaved={ next } />
				) }
				{ step.id === 'done' && <Finish serviceName={ serviceName } /> }
				{ step.id !== 'done' && (
					<div className="vqy-admin__buttons">
						{ index > 0 && (
							<Button
								variant="tertiary"
								onClick={ () => setIndex( index - 1 ) }
							>
								{ __( 'Back', 'vaqtyar' ) }
							</Button>
						) }
						<Button variant="tertiary" onClick={ next }>
							{ __( 'Skip this step', 'vaqtyar' ) }
						</Button>
					</div>
				) }
			</section>
		</div>
	);
}

function Hours( {
	start,
	end,
	onChange,
}: {
	start: string;
	end: string;
	onChange: ( start: string, end: string ) => void;
} ) {
	return (
		<div className="vqy-admin__row">
			<TextControl
				{ ...SIZE }
				type="time"
				label={ __( 'Opens at', 'vaqtyar' ) }
				value={ start }
				onChange={ ( value ) => onChange( value, end ) }
			/>
			<TextControl
				{ ...SIZE }
				type="time"
				label={ __( 'Closes at', 'vaqtyar' ) }
				value={ end }
				onChange={ ( value ) => onChange( start, value ) }
			/>
		</div>
	);
}

function Created( { children }: { children: ReactNode } ) {
	return (
		<p role="status" className="vqy-admin__muted">
			{ children }
		</p>
	);
}

function PlaceStep( {
	created,
	onCreated,
	onDone,
}: {
	created: boolean;
	onCreated: ( id: number ) => void;
	onDone: () => void;
} ) {
	const api = useApi();
	const [ name, setName ] = useState( '' );
	const [ address, setAddress ] = useState( '' );
	const [ hours, setHours ] = useState( { start: '09:00', end: '17:00' } );
	const create = useMutation( {
		mutationFn: async () => {
			const location = await api.post< Location >( '/locations', {
				name,
				timezone: 'Asia/Tehran',
				address,
				phone: null,
				holiday_calendar: 'ir',
				status: 'active',
				sort: 0,
			} );
			await api.put( `/schedules/location/${ location.id }`, {
				rules: defaultWeek( hours.start, hours.end ),
			} );

			return location.id;
		},
		onSuccess: onCreated,
	} );
	const submit = ( event: FormEvent ) => {
		event.preventDefault();
		create.mutate();
	};

	if ( created ) {
		return (
			<>
				<Created>
					{ __( 'The location is created.', 'vaqtyar' ) }
				</Created>
				<div className="vqy-admin__buttons">
					<Button variant="primary" onClick={ onDone }>
						{ __( 'Next', 'vaqtyar' ) }
					</Button>
				</div>
			</>
		);
	}

	return (
		<form className="vqy-admin__form" onSubmit={ submit }>
			<TextControl
				{ ...SIZE }
				label={ __( 'Location name', 'vaqtyar' ) }
				value={ name }
				required
				onChange={ setName }
			/>
			<TextControl
				{ ...SIZE }
				label={ __( 'Address', 'vaqtyar' ) }
				value={ address }
				onChange={ setAddress }
			/>
			<Hours
				start={ hours.start }
				end={ hours.end }
				onChange={ ( start, end ) => setHours( { start, end } ) }
			/>
			<p className="vqy-admin__muted">
				{ __(
					'Open Saturday to Thursday; change the days later under Locations.',
					'vaqtyar'
				) }
			</p>
			<div className="vqy-admin__buttons">
				<Button
					variant="primary"
					type="submit"
					isBusy={ create.isPending }
					disabled={ create.isPending || name.trim() === '' }
				>
					{ __( 'Create location', 'vaqtyar' ) }
				</Button>
			</div>
		</form>
	);
}

function ServiceStep( {
	locationId,
	created,
	onCreated,
	onDone,
}: {
	locationId: number | null;
	created: string | null;
	onCreated: ( name: string ) => void;
	onDone: () => void;
} ) {
	const api = useApi();
	const client = useQueryClient();
	const [ staffName, setStaffName ] = useState( '' );
	const [ name, setName ] = useState( '' );
	const [ duration, setDuration ] = useState( 30 );
	const [ price, setPrice ] = useState( 0 );
	const create = useMutation( {
		mutationFn: async () => {
			const staff = await api.post< Staff >( '/staff', {
				name: staffName,
				color: '#3858e9',
				wp_user_id: null,
				location_id: locationId,
				title: '',
				email: null,
				phone: null,
				avatar_id: null,
				bio: '',
				status: 'active',
				sort: 0,
			} );
			await api.put( `/schedules/staff/${ staff.id }`, {
				rules: defaultWeek( '09:00', '17:00' ),
			} );
			const variant = {
				...newVariant( true ),
				duration_min: duration,
				price: { amount: price, currency: 'IRR' },
			};
			await api.post< Service >( '/services', {
				name,
				category_id: null,
				description: '',
				image_id: null,
				capacity: 1,
				status: 'active',
				sort: 0,
				variants: [ variant ],
				staff: [
					{
						staff_id: staff.id,
						variant_id: null,
						price: null,
						duration_min: null,
					},
				],
				resources: [],
			} );

			return name;
		},
		onSuccess: ( serviceName ) => {
			void client.invalidateQueries();
			onCreated( serviceName );
		},
	} );
	const submit = ( event: FormEvent ) => {
		event.preventDefault();
		create.mutate();
	};

	if ( created !== null ) {
		return (
			<>
				<Created>
					{ __( 'The service is created.', 'vaqtyar' ) }
				</Created>
				<div className="vqy-admin__buttons">
					<Button variant="primary" onClick={ onDone }>
						{ __( 'Next', 'vaqtyar' ) }
					</Button>
				</div>
			</>
		);
	}

	return (
		<form className="vqy-admin__form" onSubmit={ submit }>
			{ locationId === null && (
				<p className="vqy-admin__muted">
					{ __(
						'No location was created here, so the staff member serves every location.',
						'vaqtyar'
					) }
				</p>
			) }
			<TextControl
				{ ...SIZE }
				label={ __( 'Staff member name', 'vaqtyar' ) }
				value={ staffName }
				required
				onChange={ setStaffName }
			/>
			<TextControl
				{ ...SIZE }
				label={ __( 'Service name', 'vaqtyar' ) }
				value={ name }
				required
				onChange={ setName }
			/>
			<div className="vqy-admin__row">
				<TextControl
					{ ...SIZE }
					type="number"
					label={ __( 'Duration (minutes)', 'vaqtyar' ) }
					value={ String( duration ) }
					onChange={ ( value ) => setDuration( toInt( value, 5 ) ) }
				/>
				<TextControl
					{ ...SIZE }
					type="number"
					label={ __( 'Price (rials)', 'vaqtyar' ) }
					value={ String( price ) }
					onChange={ ( value ) => setPrice( toInt( value ) ) }
				/>
			</div>
			<p className="vqy-admin__muted">
				{ __(
					'The staff member works 09:00 to 17:00, Saturday to Thursday.',
					'vaqtyar'
				) }
			</p>
			<div className="vqy-admin__buttons">
				<Button
					variant="primary"
					type="submit"
					isBusy={ create.isPending }
					disabled={
						create.isPending ||
						staffName.trim() === '' ||
						name.trim() === ''
					}
				>
					{ __( 'Create service', 'vaqtyar' ) }
				</Button>
			</div>
		</form>
	);
}

function Finish( { serviceName }: { serviceName: string | null } ) {
	const api = useApi();
	const client = useQueryClient();
	const finish = useMutation( {
		mutationFn: () => api.put( '/onboarding', { done: true } ),
		onSuccess: () => {
			client.setQueryData( [ '/onboarding' ], { done: true } );
			window.location.hash = '#/';
		},
	} );

	return (
		<>
			<p>
				{ serviceName === null
					? __( 'Setup is done.', 'vaqtyar' )
					: __(
							'Setup is done. The service can be booked now.',
							'vaqtyar'
						) }
			</p>
			<p>
				{ __(
					'Put this shortcode on a page to show the booking form:',
					'vaqtyar'
				) }
			</p>
			<code>{ `[${ SLUG }_booking]` }</code>
			<div className="vqy-admin__buttons">
				<Button
					variant="primary"
					isBusy={ finish.isPending }
					disabled={ finish.isPending }
					onClick={ () => finish.mutate() }
				>
					{ __( 'Go to the dashboard', 'vaqtyar' ) }
				</Button>
			</div>
		</>
	);
}
