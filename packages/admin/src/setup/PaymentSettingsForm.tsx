import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { PaymentSettings } from '@vaqtyar/shared';
import {
	Button,
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
import { SIZE } from '../catalog/fields';
import { errorMessage } from '../query';

function gatewayName( id: string ): string {
	return id === 'zarinpal'
		? __( 'Zarinpal', 'vaqtyar' )
		: __( 'Zibal', 'vaqtyar' );
}

function gatewayHelp( set: boolean, fixed: boolean ): string {
	if ( fixed ) {
		return __( 'Set in wp-config.php.', 'vaqtyar' );
	}

	return set
		? __(
				'A merchant id is stored. Type a new one to replace it.',
				'vaqtyar'
			)
		: __( 'Merchant id', 'vaqtyar' );
}

/**
 * Online payment: the merchant id of each gateway and the WooCommerce
 * switch. A stored id is never shown; a blank field keeps it, and "Remove"
 * clears it.
 *
 * @param props
 * @param props.onSaved Called after a successful save.
 */
export function PaymentSettingsForm( { onSaved }: { onSaved?: () => void } ) {
	const api = useApi();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const stored = useQuery( {
		queryKey: [ '/payments/settings' ],
		queryFn: () => api.get< PaymentSettings >( '/payments/settings' ),
	} );
	const [ secrets, setSecrets ] = useState< Record< string, string > >( {} );
	const [ woo, setWoo ] = useState< boolean | null >( null );

	const save = useMutation( {
		mutationFn: ( body: {
			secrets: Record< string, string >;
			woocommerce?: boolean;
		} ) => api.put< PaymentSettings >( '/payments/settings', body ),
		onSuccess: ( saved ) => {
			client.setQueryData( [ '/payments/settings' ], saved );
			setSecrets( {} );
			setWoo( null );
			void createSuccessNotice( __( 'Saved.', 'vaqtyar' ), {
				type: 'snackbar',
			} );
			onSaved?.();
		},
	} );
	const submit = ( event: FormEvent ) => {
		event.preventDefault();
		const changed = Object.fromEntries(
			Object.entries( secrets ).filter(
				( [ , value ] ) => value.trim() !== ''
			)
		);
		save.mutate( {
			secrets: changed,
			...( woo === null ? {} : { woocommerce: woo } ),
		} );
	};

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
	const { gateways, woocommerce } = stored.data;

	return (
		<form className="vqy-admin__form" onSubmit={ submit }>
			{ gateways.map( ( gateway ) => (
				<div className="vqy-admin__row" key={ gateway.id }>
					<TextControl
						{ ...SIZE }
						label={ gatewayName( gateway.id ) }
						help={ gatewayHelp( gateway.set, gateway.fixed ) }
						disabled={ gateway.fixed }
						autoComplete="off"
						value={ secrets[ gateway.secret ] ?? '' }
						onChange={ ( value ) =>
							setSecrets( {
								...secrets,
								[ gateway.secret ]: value,
							} )
						}
					/>
					{ gateway.set && ! gateway.fixed && (
						<Button
							variant="tertiary"
							isDestructive
							onClick={ () =>
								save.mutate( {
									secrets: { [ gateway.secret ]: '' },
								} )
							}
						>
							{ __( 'Remove', 'vaqtyar' ) }
						</Button>
					) }
				</div>
			) ) }
			{ woocommerce.available && (
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Pay through WooCommerce', 'vaqtyar' ) }
					checked={ woo ?? woocommerce.enabled }
					onChange={ setWoo }
				/>
			) }
			<div className="vqy-admin__buttons">
				<Button
					variant="primary"
					type="submit"
					isBusy={ save.isPending }
					disabled={ save.isPending }
				>
					{ __( 'Save payment settings', 'vaqtyar' ) }
				</Button>
			</div>
		</form>
	);
}
