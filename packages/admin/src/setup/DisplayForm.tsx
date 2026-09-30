import { useMutation, useQueryClient } from '@tanstack/react-query';
import type { DisplaySettings } from '@vaqtyar/shared';
import { Button, Notice, SelectControl } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import type { FormEvent } from 'react';

import { useApi } from '../api';
import { SIZE } from '../catalog/fields';
import { useDisplay } from '../display';

/**
 * The language of the plugin's own screens, the calendar dates are written
 * in and the digits numbers use. Dates and digits change at once; the
 * language is loaded with the page, so it asks for a reload.
 *
 * @param props
 * @param props.onSaved Called after a successful save (the wizard moves on).
 */
export function DisplayForm( { onSaved }: { onSaved?: () => void } ) {
	const api = useApi();
	const client = useQueryClient();
	const current = useDisplay();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const [ draft, setDraft ] = useState< DisplaySettings >( current );
	const [ reload, setReload ] = useState( false );

	const save = useMutation( {
		mutationFn: () => api.put< DisplaySettings >( '/general', draft ),
		onSuccess: ( saved ) => {
			const changed = saved.language !== current.language;
			setReload( changed );
			client.setQueryData( [ '/general' ], saved );
			void createSuccessNotice( __( 'Saved.', 'vaqtyar' ), {
				type: 'snackbar',
			} );
			// A new language needs the page reloaded first; the wizard waits for that.
			if ( ! changed ) {
				onSaved?.();
			}
		},
	} );
	const submit = ( event: FormEvent ) => {
		event.preventDefault();
		save.mutate();
	};

	return (
		<form className="vqy-admin__form" onSubmit={ submit }>
			<SelectControl
				{ ...SIZE }
				label={ __( 'Language', 'vaqtyar' ) }
				help={ __(
					'The language of this plugin’s screens and of the booking form. It does not have to be the language of WordPress.',
					'vaqtyar'
				) }
				value={ draft.language }
				options={ [
					{
						value: 'auto',
						label: __( 'Same as the site', 'vaqtyar' ),
					},
					{ value: 'fa', label: 'فارسی' },
					{ value: 'en', label: 'English' },
				] }
				onChange={ ( language ) =>
					setDraft( {
						...draft,
						language: language as DisplaySettings[ 'language' ],
					} )
				}
			/>
			<SelectControl
				{ ...SIZE }
				label={ __( 'Calendar', 'vaqtyar' ) }
				help={ __(
					'Dates are shown and typed in this calendar. Bookings are stored the same either way.',
					'vaqtyar'
				) }
				value={ draft.calendar }
				options={ [
					{ value: 'jalali', label: __( 'Jalali', 'vaqtyar' ) },
					{
						value: 'gregorian',
						label: __( 'Gregorian', 'vaqtyar' ),
					},
				] }
				onChange={ ( calendar ) =>
					setDraft( {
						...draft,
						calendar: calendar as DisplaySettings[ 'calendar' ],
					} )
				}
			/>
			<SelectControl
				{ ...SIZE }
				label={ __( 'Digits', 'vaqtyar' ) }
				value={ draft.digits }
				options={ [
					{ value: 'persian', label: '۰۱۲۳۴۵۶۷۸۹' },
					{ value: 'latin', label: '0123456789' },
				] }
				onChange={ ( digits ) =>
					setDraft( {
						...draft,
						digits: digits as DisplaySettings[ 'digits' ],
					} )
				}
			/>
			{ reload && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'Reload the page to use the new language.',
						'vaqtyar'
					) }{ ' ' }
					<Button
						variant="link"
						onClick={ () => window.location.reload() }
					>
						{ __( 'Reload', 'vaqtyar' ) }
					</Button>
				</Notice>
			) }
			<div className="vqy-admin__buttons">
				<Button
					variant="primary"
					type="submit"
					isBusy={ save.isPending }
					disabled={ save.isPending }
				>
					{ __( 'Save language and calendar', 'vaqtyar' ) }
				</Button>
			</div>
		</form>
	);
}
