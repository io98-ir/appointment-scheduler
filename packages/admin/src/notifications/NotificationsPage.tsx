import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type {
	NotificationTemplate,
	SmsOverview,
	SmsPatternValue,
	TemplateAudience,
	TemplateTrigger,
} from '@vaqtyar/shared';
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextareaControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import type { FormEvent } from 'react';

import { useApi } from '../api';
import { SIZE } from '../catalog/fields';
import { errorMessage } from '../query';
import { providerName } from '../setup/SmsSettingsForm';

const TRIGGERS: TemplateTrigger[] = [
	'booked',
	'cancelled',
	'rescheduled',
	'reminder',
];
const AUDIENCES: TemplateAudience[] = [ 'customer', 'staff', 'admin' ];
const CHANNELS = [ 'email', 'sms' ];

/** The names a message may use between braces (NotificationService's facts). */
const PLACEHOLDERS = [
	'code',
	'customer_name',
	'service',
	'staff',
	'location',
	'date',
	'time',
	'end_time',
	'party_size',
	'total',
];

const TRIGGER_LABEL: Record< TemplateTrigger, () => string > = {
	booked: () => __( 'Appointment booked', 'vaqtyar' ),
	cancelled: () => __( 'Appointment cancelled', 'vaqtyar' ),
	rescheduled: () => __( 'Appointment rescheduled', 'vaqtyar' ),
	reminder: () => __( 'Reminder before the appointment', 'vaqtyar' ),
};

const AUDIENCE_LABEL: Record< TemplateAudience, () => string > = {
	customer: () => __( 'Customer', 'vaqtyar' ),
	staff: () => __( 'Staff member', 'vaqtyar' ),
	admin: () => __( 'Site administrator', 'vaqtyar' ),
};

function channelLabel( channel: string ): string {
	if ( channel === 'email' ) {
		return __( 'Email', 'vaqtyar' );
	}

	return channel === 'sms' ? __( 'SMS', 'vaqtyar' ) : channel;
}

interface Draft {
	id: number | null;
	trigger: TemplateTrigger;
	audience: TemplateAudience;
	channel: string;
	offset: string;
	subject: string;
	body: string;
	enabled: boolean;
	/** By provider id: the code, and the names separated by commas. */
	patterns: Record< string, { code: string; args: string } >;
}

const EMPTY: Draft = {
	id: null,
	trigger: 'booked',
	audience: 'customer',
	channel: 'email',
	offset: '',
	subject: '',
	body: '',
	enabled: true,
	patterns: {},
};

function toDraft( template: NotificationTemplate ): Draft {
	return {
		id: template.id,
		trigger: template.trigger,
		audience: template.audience,
		channel: template.channel,
		offset:
			template.offset_min === null ? '' : String( template.offset_min ),
		subject: template.subject,
		body: template.body,
		enabled: template.enabled,
		patterns: Object.fromEntries(
			Object.entries( template.sms_patterns ).map(
				( [ id, pattern ] ) => [
					id,
					{ code: pattern.code, args: pattern.args.join( ', ' ) },
				]
			)
		),
	};
}

/**
 * The body a PUT or POST takes: a pattern with no code is left out, and an
 * email has none at all.
 *
 * @param draft The form's state.
 */
function toBody( draft: Draft ) {
	const patterns: Record< string, SmsPatternValue > = {};
	if ( draft.channel === 'sms' ) {
		for ( const [ id, pattern ] of Object.entries( draft.patterns ) ) {
			const code = pattern.code.trim();
			if ( code !== '' ) {
				patterns[ id ] = {
					code,
					args: pattern.args
						.split( ',' )
						.map( ( name ) => name.trim() )
						.filter( ( name ) => name !== '' ),
				};
			}
		}
	}

	return {
		trigger: draft.trigger,
		audience: draft.audience,
		channel: draft.channel,
		offset_min:
			draft.trigger === 'reminder'
				? Number.parseInt( draft.offset, 10 ) || null
				: null,
		subject: draft.channel === 'email' ? draft.subject : '',
		body: draft.body,
		enabled: draft.enabled,
		sms_patterns: patterns,
	};
}

function describeOffset( minutes: number ): string {
	if ( minutes % 1440 === 0 ) {
		return sprintf(
			/* translators: %d: number of days */
			__( '%d days before', 'vaqtyar' ),
			minutes / 1440
		);
	}
	if ( minutes % 60 === 0 ) {
		return sprintf(
			/* translators: %d: number of hours */
			__( '%d hours before', 'vaqtyar' ),
			minutes / 60
		);
	}

	return sprintf(
		/* translators: %d: number of minutes */
		__( '%d minutes before', 'vaqtyar' ),
		minutes
	);
}

/**
 * The messages the plugin sends (T5.4, T5.5): one template per event,
 * audience and channel, edited here instead of through the API. An SMS
 * template can carry a pattern for each provider, since most Iranian
 * providers send only approved patterns.
 */
export function NotificationsPage() {
	const api = useApi();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const [ editing, setEditing ] = useState< Draft | null >( null );
	const templates = useQuery( {
		queryKey: [ '/notification-templates' ],
		queryFn: () =>
			api.get< NotificationTemplate[] >( '/notification-templates' ),
	} );
	const remove = useMutation( {
		mutationFn: ( id: number ) =>
			api.delete( '/notification-templates/' + id ),
		onSuccess: async () => {
			await client.invalidateQueries( {
				queryKey: [ '/notification-templates' ],
			} );
			void createSuccessNotice( __( 'Deleted.', 'vaqtyar' ), {
				type: 'snackbar',
			} );
		},
	} );

	if ( templates.isPending ) {
		return <Spinner />;
	}
	if ( templates.isError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ errorMessage( templates.error ) }
			</Notice>
		);
	}

	return (
		<>
			<p className="vqy-admin__muted">
				{ __(
					'Write what customers and staff are told. Put a name between braces to fill in the appointment, for example {customer_name}.',
					'vaqtyar'
				) }
			</p>
			{ remove.isError && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( remove.error ) }
				</Notice>
			) }
			{ editing ? (
				<TemplateForm
					draft={ editing }
					onCancel={ () => setEditing( null ) }
					onSaved={ async () => {
						setEditing( null );
						await client.invalidateQueries( {
							queryKey: [ '/notification-templates' ],
						} );
						void createSuccessNotice( __( 'Saved.', 'vaqtyar' ), {
							type: 'snackbar',
						} );
					} }
				/>
			) : (
				<div className="vqy-admin__buttons">
					<Button
						variant="primary"
						onClick={ () => setEditing( EMPTY ) }
					>
						{ __( 'Add template', 'vaqtyar' ) }
					</Button>
				</div>
			) }
			<table className="widefat striped vqy-admin__table">
				<thead>
					<tr>
						<th>{ __( 'When', 'vaqtyar' ) }</th>
						<th>{ __( 'To', 'vaqtyar' ) }</th>
						<th>{ __( 'Channel', 'vaqtyar' ) }</th>
						<th>{ __( 'Status', 'vaqtyar' ) }</th>
						<th>{ __( 'Actions', 'vaqtyar' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ templates.data.map( ( template ) => (
						<tr key={ template.id }>
							<td>
								{ TRIGGER_LABEL[ template.trigger ]() }
								{ template.offset_min !== null && (
									<>
										{ ' — ' }
										{ describeOffset(
											template.offset_min
										) }
									</>
								) }
							</td>
							<td>{ AUDIENCE_LABEL[ template.audience ]() }</td>
							<td>{ channelLabel( template.channel ) }</td>
							<td>
								{ template.enabled
									? __( 'On', 'vaqtyar' )
									: __( 'Off', 'vaqtyar' ) }
							</td>
							<td>
								<Button
									variant="link"
									onClick={ () =>
										setEditing( toDraft( template ) )
									}
								>
									{ __( 'Edit', 'vaqtyar' ) }
								</Button>{ ' ' }
								<Button
									variant="link"
									isDestructive
									disabled={ remove.isPending }
									onClick={ () =>
										remove.mutate( template.id )
									}
								>
									{ __( 'Delete', 'vaqtyar' ) }
								</Button>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</>
	);
}

function TemplateForm( {
	draft: initial,
	onCancel,
	onSaved,
}: {
	draft: Draft;
	onCancel: () => void;
	onSaved: () => void;
} ) {
	const api = useApi();
	const [ draft, setDraft ] = useState< Draft >( initial );
	const sms = useQuery( {
		queryKey: [ '/sms' ],
		queryFn: () => api.get< SmsOverview >( '/sms' ),
	} );
	const providers = ( sms.data?.providers ?? [] ).filter(
		( provider ) => provider.configured
	);
	const save = useMutation( {
		mutationFn: () =>
			draft.id === null
				? api.post( '/notification-templates', toBody( draft ) )
				: api.put(
						'/notification-templates/' + draft.id,
						toBody( draft )
					),
		onSuccess: onSaved,
	} );
	const submit = ( event: FormEvent ) => {
		event.preventDefault();
		save.mutate();
	};
	const setPattern = (
		id: string,
		change: Partial< { code: string; args: string } >
	) =>
		setDraft( ( current ) => ( {
			...current,
			patterns: {
				...current.patterns,
				[ id ]: {
					code: current.patterns[ id ]?.code ?? '',
					args: current.patterns[ id ]?.args ?? '',
					...change,
				},
			},
		} ) );

	return (
		<form className="vqy-admin__form" onSubmit={ submit }>
			{ save.isError && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( save.error ) }
				</Notice>
			) }
			<SelectControl
				{ ...SIZE }
				label={ __( 'When', 'vaqtyar' ) }
				value={ draft.trigger }
				options={ TRIGGERS.map( ( value ) => ( {
					value,
					label: TRIGGER_LABEL[ value ](),
				} ) ) }
				onChange={ ( value ) =>
					setDraft( { ...draft, trigger: value as TemplateTrigger } )
				}
			/>
			{ draft.trigger === 'reminder' && (
				<TextControl
					{ ...SIZE }
					type="number"
					min={ 1 }
					required
					label={ __( 'Minutes before the start', 'vaqtyar' ) }
					help={ __( '1440 is one day.', 'vaqtyar' ) }
					value={ draft.offset }
					onChange={ ( offset ) => setDraft( { ...draft, offset } ) }
				/>
			) }
			<SelectControl
				{ ...SIZE }
				label={ __( 'To', 'vaqtyar' ) }
				value={ draft.audience }
				options={ AUDIENCES.map( ( value ) => ( {
					value,
					label: AUDIENCE_LABEL[ value ](),
				} ) ) }
				onChange={ ( value ) =>
					setDraft( {
						...draft,
						audience: value as TemplateAudience,
					} )
				}
			/>
			<SelectControl
				{ ...SIZE }
				label={ __( 'Channel', 'vaqtyar' ) }
				value={ draft.channel }
				options={ CHANNELS.map( ( value ) => ( {
					value,
					label: channelLabel( value ),
				} ) ) }
				onChange={ ( channel ) => setDraft( { ...draft, channel } ) }
			/>
			{ draft.channel === 'email' && (
				<TextControl
					{ ...SIZE }
					label={ __( 'Subject', 'vaqtyar' ) }
					maxLength={ 191 }
					value={ draft.subject }
					onChange={ ( subject ) =>
						setDraft( { ...draft, subject } )
					}
				/>
			) }
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Message', 'vaqtyar' ) }
				help={ PLACEHOLDERS.map( ( name ) => `{${ name }}` ).join(
					' '
				) }
				rows={ 5 }
				maxLength={ 2000 }
				required
				value={ draft.body }
				onChange={ ( body ) => setDraft( { ...draft, body } ) }
			/>
			{ draft.channel === 'sms' && (
				<fieldset className="vqy-admin__fieldset">
					<legend>{ __( 'SMS patterns', 'vaqtyar' ) }</legend>
					<p className="vqy-admin__muted">
						{ __(
							'A provider that needs an approved pattern takes its code here and the names, separated by commas, in the order the pattern lists its values. Leave the code empty to send the message as plain text.',
							'vaqtyar'
						) }
					</p>
					{ providers.length === 0 && (
						<p className="vqy-admin__muted">
							{ __(
								'Set up an SMS provider in Settings first.',
								'vaqtyar'
							) }
						</p>
					) }
					{ providers.map( ( provider ) => (
						<div key={ provider.id } className="vqy-admin__row">
							<TextControl
								{ ...SIZE }
								label={ `${ providerName(
									provider.id
								) }: ${ __( 'Pattern code', 'vaqtyar' ) }` }
								value={
									draft.patterns[ provider.id ]?.code ?? ''
								}
								onChange={ ( code ) =>
									setPattern( provider.id, { code } )
								}
							/>
							<TextControl
								{ ...SIZE }
								label={ `${ providerName(
									provider.id
								) }: ${ __( 'Values', 'vaqtyar' ) }` }
								help={ 'customer_name, code' }
								value={
									draft.patterns[ provider.id ]?.args ?? ''
								}
								onChange={ ( args ) =>
									setPattern( provider.id, { args } )
								}
							/>
						</div>
					) ) }
				</fieldset>
			) }
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Send this message', 'vaqtyar' ) }
				checked={ draft.enabled }
				onChange={ ( enabled ) => setDraft( { ...draft, enabled } ) }
			/>
			<div className="vqy-admin__buttons">
				<Button
					variant="primary"
					type="submit"
					isBusy={ save.isPending }
					disabled={ save.isPending }
				>
					{ __( 'Save template', 'vaqtyar' ) }
				</Button>
				<Button variant="tertiary" onClick={ onCancel }>
					{ __( 'Cancel', 'vaqtyar' ) }
				</Button>
			</div>
		</form>
	);
}
