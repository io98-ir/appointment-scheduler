import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { SmsOverview } from '@vaqtyar/shared';
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import type { FormEvent } from 'react';

import { useApi } from '../api';
import { SIZE } from '../catalog/fields';
import { errorMessage } from '../query';

export function providerName( id: string ): string {
	switch ( id ) {
		case 'kavenegar':
			return __( 'Kavenegar', 'vaqtyar' );
		case 'ippanel':
			return __( 'IPPanel', 'vaqtyar' );
		case 'smsir':
			return __( 'SMS.ir', 'vaqtyar' );
		case 'melipayamak':
			return __( 'Melipayamak', 'vaqtyar' );
		default:
			return id;
	}
}

/**
 * The secret's label: "sms_melipayamak_username" is the "username".
 *
 * @param name The secret's name.
 */
function secretLabel( name: string ): string {
	if ( name.endsWith( '_username' ) ) {
		return __( 'Username', 'vaqtyar' );
	}

	return name.endsWith( '_password' )
		? __( 'Password', 'vaqtyar' )
		: __( 'API key', 'vaqtyar' );
}

/**
 * What a secret's field says about its state.
 *
 * @param secret       The secret.
 * @param secret.set   Whether a value is stored.
 * @param secret.fixed Whether wp-config.php defines it.
 */
function secretHelp( secret: { set: boolean; fixed: boolean } ) {
	if ( secret.fixed ) {
		return __( 'Set in wp-config.php.', 'vaqtyar' );
	}

	return secret.set
		? __( 'Stored. Type a new value to replace it.', 'vaqtyar' )
		: undefined;
}

/**
 * Every provider but Kavenegar sends from a line the owner names.
 *
 * @param id The provider's id.
 */
function needsSender( id: string ): boolean {
	return id !== 'kavenegar';
}

/**
 * SMS: one provider's credentials and sender line, put first in the failover
 * order, and a test message. The other providers keep their settings. A
 * stored key is never shown; a blank field keeps it.
 *
 * @param props
 * @param props.onSaved Called after a successful save.
 */
export function SmsSettingsForm( { onSaved }: { onSaved?: () => void } ) {
	const api = useApi();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const stored = useQuery( {
		queryKey: [ '/sms' ],
		queryFn: () => api.get< SmsOverview >( '/sms' ),
	} );
	const [ chosen, setChosen ] = useState( '' );
	const [ secrets, setSecrets ] = useState< Record< string, string > >( {} );
	const [ sender, setSender ] = useState< string | null >( null );
	const [ phone, setPhone ] = useState( '' );

	const save = useMutation( {
		mutationFn: ( body: object ) => api.put< SmsOverview >( '/sms', body ),
		onSuccess: ( saved ) => {
			client.setQueryData( [ '/sms' ], saved );
			setSecrets( {} );
			setSender( null );
			void createSuccessNotice( __( 'Saved.', 'vaqtyar' ), {
				type: 'snackbar',
			} );
			onSaved?.();
		},
	} );
	const test = useMutation( {
		mutationFn: () =>
			api.post< { reference: string } >( '/sms/test', { phone } ),
		onSuccess: () =>
			void createSuccessNotice( __( 'Test message sent.', 'vaqtyar' ), {
				type: 'snackbar',
			} ),
	} );

	if ( stored.isPending ) {
		return <Spinner />;
	}
	if ( stored.isError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ errorMessage( stored.error ) }
			</Notice>
		);
	}
	const overview = stored.data;
	const id =
		chosen || overview.order[ 0 ] || overview.providers[ 0 ]?.id || '';
	const provider = overview.providers.find( ( item ) => item.id === id );
	if ( ! provider ) {
		return null;
	}

	const submit = ( event: FormEvent ) => {
		event.preventDefault();
		save.mutate( {
			order: [ id, ...overview.order.filter( ( item ) => item !== id ) ],
			senders: {
				...overview.senders,
				...( sender === null ? {} : { [ id ]: sender } ),
			},
			otp_patterns: overview.otp_patterns,
			secrets: Object.fromEntries(
				Object.entries( secrets ).filter(
					( [ , value ] ) => value.trim() !== ''
				)
			),
		} );
	};

	return (
		<form className="vqy-admin__form" onSubmit={ submit }>
			<SelectControl
				{ ...SIZE }
				label={ __( 'SMS provider', 'vaqtyar' ) }
				help={
					provider.configured
						? __( 'This provider is ready.', 'vaqtyar' )
						: __( 'Not set up yet.', 'vaqtyar' )
				}
				value={ id }
				options={ overview.providers.map( ( item ) => ( {
					value: item.id,
					label: providerName( item.id ),
				} ) ) }
				onChange={ ( value ) => {
					setChosen( value );
					setSecrets( {} );
					setSender( null );
				} }
			/>
			{ provider.secrets.map( ( secret ) => (
				<TextControl
					{ ...SIZE }
					key={ secret.name }
					type="password"
					autoComplete="off"
					label={ secretLabel( secret.name ) }
					help={ secretHelp( secret ) }
					disabled={ secret.fixed }
					value={ secrets[ secret.name ] ?? '' }
					onChange={ ( value ) =>
						setSecrets( { ...secrets, [ secret.name ]: value } )
					}
				/>
			) ) }
			{ needsSender( id ) && (
				<TextControl
					{ ...SIZE }
					label={ __( 'Sender line', 'vaqtyar' ) }
					value={ sender ?? overview.senders[ id ] ?? '' }
					onChange={ setSender }
				/>
			) }
			<div className="vqy-admin__buttons">
				<Button
					variant="primary"
					type="submit"
					isBusy={ save.isPending }
					disabled={ save.isPending }
				>
					{ __( 'Save SMS settings', 'vaqtyar' ) }
				</Button>
			</div>
			{ overview.providers.some( ( item ) => item.configured ) && (
				<div className="vqy-admin__row">
					<TextControl
						{ ...SIZE }
						type="tel"
						label={ __( 'Send a test message to', 'vaqtyar' ) }
						value={ phone }
						onChange={ setPhone }
					/>
					<Button
						variant="secondary"
						isBusy={ test.isPending }
						disabled={ test.isPending || phone.trim() === '' }
						onClick={ () => test.mutate() }
					>
						{ __( 'Send test', 'vaqtyar' ) }
					</Button>
				</div>
			) }
		</form>
	);
}
