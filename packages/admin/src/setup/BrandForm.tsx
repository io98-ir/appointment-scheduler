import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { Brand } from '@vaqtyar/shared';
import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import type { FormEvent } from 'react';

import { useApi } from '../api';
import { SIZE } from '../catalog/fields';
import { DEFAULT_ACCENT, PRESETS } from '../palette';
import { errorMessage } from '../query';

interface MediaFrame {
	on: ( event: 'select', callback: () => void ) => void;
	open: () => void;
	state: () => {
		get: ( name: 'selection' ) => {
			first: () => { toJSON: () => { url?: string } };
		};
	};
}

/**
 * The media library, when wp-admin loaded it (AdminPage enqueues it); a page
 * without it still takes a pasted address.
 */
function mediaLibrary(): ( ( options: object ) => MediaFrame ) | undefined {
	return (
		window as unknown as {
			wp?: { media?: ( options: object ) => MediaFrame };
		}
	 ).wp?.media;
}

/**
 * The white-label look: the name that replaces the product name, a logo and
 * an accent colour. Empty means the default (T6.1).
 *
 * @param props
 * @param props.onSaved Called after a successful save (the wizard moves on).
 */
export function BrandForm( { onSaved }: { onSaved?: () => void } ) {
	const api = useApi();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const stored = useQuery( {
		queryKey: [ '/brand' ],
		queryFn: () => api.get< Brand >( '/brand' ),
	} );
	const [ draft, setDraft ] = useState< Brand >( {
		name: '',
		logo_url: '',
		color: '',
	} );
	useEffect( () => {
		if ( stored.data ) {
			setDraft( stored.data );
		}
	}, [ stored.data ] );

	const save = useMutation( {
		mutationFn: () => api.put< Brand >( '/brand', draft ),
		onSuccess: ( saved ) => {
			client.setQueryData( [ '/brand' ], saved );
			void createSuccessNotice( __( 'Saved.', 'vaqtyar' ), {
				type: 'snackbar',
			} );
			onSaved?.();
		},
	} );
	const submit = ( event: FormEvent ) => {
		event.preventDefault();
		save.mutate();
	};
	const chooseLogo = () => {
		const media = mediaLibrary();
		if ( ! media ) {
			return;
		}
		const frame = media( { multiple: false } );
		frame.on( 'select', () => {
			const url = frame.state().get( 'selection' ).first().toJSON().url;
			if ( url ) {
				setDraft( ( current ) => ( { ...current, logo_url: url } ) );
			}
		} );
		frame.open();
	};

	if ( stored.isPending ) {
		return <Spinner />;
	}

	return (
		<form className="vqy-admin__form" onSubmit={ submit }>
			{ stored.isError && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( stored.error ) }
				</Notice>
			) }
			<TextControl
				{ ...SIZE }
				label={ __( 'Brand name', 'vaqtyar' ) }
				help={ __(
					'Shown in the admin menu and header. Empty keeps the product name.',
					'vaqtyar'
				) }
				value={ draft.name }
				maxLength={ 60 }
				onChange={ ( name ) => setDraft( { ...draft, name } ) }
			/>
			<div className="vqy-admin__row">
				<TextControl
					{ ...SIZE }
					type="url"
					label={ __( 'Logo address', 'vaqtyar' ) }
					value={ draft.logo_url }
					onChange={ ( logoUrl ) =>
						setDraft( { ...draft, logo_url: logoUrl } )
					}
				/>
				{ mediaLibrary() && (
					<Button variant="secondary" onClick={ chooseLogo }>
						{ __( 'Choose logo', 'vaqtyar' ) }
					</Button>
				) }
			</div>
			<fieldset className="vqy-palette">
				<legend className="components-base-control__label">
					{ __( 'Palette', 'vaqtyar' ) }
				</legend>
				{ PRESETS.map( ( preset ) => (
					<button
						key={ preset.id }
						type="button"
						className="vqy-palette__option"
						aria-pressed={
							( draft.color || DEFAULT_ACCENT ).toLowerCase() ===
							preset.color
						}
						onClick={ () =>
							setDraft( { ...draft, color: preset.color } )
						}
					>
						<span
							className="vqy-palette__dot"
							style={ { background: preset.color } }
						/>
						{ preset.label() }
					</button>
				) ) }
			</fieldset>
			<div className="vqy-admin__row">
				<TextControl
					{ ...SIZE }
					label={ __( 'Accent colour', 'vaqtyar' ) }
					help={ __(
						'Any colour as #rrggbb; empty keeps the default. The rest of the palette follows it.',
						'vaqtyar'
					) }
					value={ draft.color }
					maxLength={ 7 }
					onChange={ ( color ) => setDraft( { ...draft, color } ) }
				/>
				<input
					type="color"
					aria-label={ __( 'Pick a colour', 'vaqtyar' ) }
					value={
						/^#[0-9a-f]{6}$/i.test( draft.color )
							? draft.color
							: DEFAULT_ACCENT
					}
					onChange={ ( event ) =>
						setDraft( { ...draft, color: event.target.value } )
					}
				/>
			</div>
			<div className="vqy-admin__buttons">
				<Button
					variant="primary"
					type="submit"
					isBusy={ save.isPending }
					disabled={ save.isPending }
				>
					{ __( 'Save look', 'vaqtyar' ) }
				</Button>
			</div>
		</form>
	);
}
