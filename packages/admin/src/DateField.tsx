import {
	cursorOf,
	formatDate,
	formatDigits,
	monthGrid,
	monthName,
	parseTypedDate,
	shiftMonth,
	weekdayNames,
	type MonthCursor,
} from '@vaqtyar/shared';
import { Button, TextControl } from '@wordpress/components';
import { useEffect, useId, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import type { ReactNode } from 'react';

import { SIZE } from './catalog/fields';
import { useDisplay } from './display';

interface Props {
	label: string;
	/** A Gregorian local date ("2026-09-25"), or "" for none. */
	value: string;
	onChange: ( value: string ) => void;
	/** The earliest date that can be chosen. */
	min?: string | undefined;
	help?: ReactNode;
	disabled?: boolean;
}

/**
 * A date in the calendar the owner chose (Settings). The value is always a
 * Gregorian local date, the same as the API takes; only what is shown and
 * typed follows the calendar. Gregorian is the browser's own date input; the
 * Jalali calendar, which browsers do not offer, is a typed field with a
 * month grid beside it.
 *
 * @param props
 */
export function DateField( props: Props ) {
	const { calendar } = useDisplay();

	return calendar === 'gregorian' ? (
		<TextControl
			{ ...SIZE }
			type="date"
			label={ props.label }
			help={ props.help }
			value={ props.value }
			min={ props.min }
			disabled={ props.disabled }
			onChange={ props.onChange }
		/>
	) : (
		<JalaliDateField { ...props } />
	);
}

/**
 * A weekday name that fits a narrow calendar cell: two Latin letters, or the
 * first letter of a Persian name (ش for شنبه).
 *
 * @param name
 */
function shortDay( name: string ): string {
	return /^[A-Za-z]/.test( name )
		? name.slice( 0, 2 )
		: ( [ ...name ][ 0 ] ?? name );
}

function todayLocal(): string {
	const now = new Date();

	return [
		now.getFullYear(),
		String( now.getMonth() + 1 ).padStart( 2, '0' ),
		String( now.getDate() ).padStart( 2, '0' ),
	].join( '-' );
}

function JalaliDateField( {
	label,
	value,
	onChange,
	min,
	help,
	disabled,
}: Props ) {
	const { digits } = useDisplay();
	const shown = value === '' ? '' : formatDate( value, 'jalali', digits );
	const [ text, setText ] = useState( shown );
	const [ open, setOpen ] = useState( false );
	const [ cursor, setCursor ] = useState< MonthCursor >( () =>
		cursorOf( 'jalali', value === '' ? todayLocal() : value )
	);
	const popupId = useId();
	const typing = useRef( false );

	// A change from outside (the form reset, other digits) replaces the text,
	// but what the person is typing is left alone while it already means the value.
	useEffect( () => {
		if ( ! typing.current || parseTypedDate( text, 'jalali' ) !== value ) {
			setText( shown );
		}
		// Only a new value or new digits should overwrite the text.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ value, shown ] );

	const allowed = ( iso: string ) => min === undefined || iso >= min;
	const type = ( next: string ) => {
		setText( next );
		if ( next.trim() === '' ) {
			onChange( '' );

			return;
		}
		const iso = parseTypedDate( next, 'jalali' );
		if ( iso !== null && allowed( iso ) ) {
			onChange( iso );
		}
	};
	const grid = monthGrid( 'jalali', cursor );

	return (
		// Escape closes the popup from anywhere inside it.
		// eslint-disable-next-line jsx-a11y/no-static-element-interactions
		<div
			className="vqy-date"
			onKeyDown={ ( event ) => {
				if ( event.key === 'Escape' && open ) {
					event.stopPropagation();
					setOpen( false );
				}
			} }
		>
			<div className="vqy-date__row">
				<TextControl
					{ ...SIZE }
					label={ label }
					help={ help }
					value={ text }
					disabled={ disabled }
					inputMode="numeric"
					placeholder={ formatDigits( '1405/01/01', digits ) }
					onChange={ type }
					onFocus={ () => {
						typing.current = true;
					} }
					onBlur={ () => {
						typing.current = false;
						setText( shown );
					} }
				/>
				<Button
					variant="secondary"
					className="vqy-date__toggle"
					aria-expanded={ open }
					aria-controls={ popupId }
					disabled={ disabled ?? false }
					onClick={ () => {
						setCursor(
							cursorOf(
								'jalali',
								value === '' ? todayLocal() : value
							)
						);
						setOpen( ! open );
					} }
				>
					{ __( 'Calendar', 'vaqtyar' ) }
				</Button>
			</div>
			{ open && (
				<div
					id={ popupId }
					className="vqy-date__popup"
					role="group"
					aria-label={ label }
				>
					<div className="vqy-date__nav">
						<Button
							variant="tertiary"
							onClick={ () =>
								setCursor( shiftMonth( cursor, -1 ) )
							}
						>
							{ __( 'Previous month', 'vaqtyar' ) }
						</Button>
						<strong aria-live="polite">
							{ monthName( 'jalali', cursor.month ) }{ ' ' }
							{ formatDigits( String( cursor.year ), digits ) }
						</strong>
						<Button
							variant="tertiary"
							onClick={ () =>
								setCursor( shiftMonth( cursor, 1 ) )
							}
						>
							{ __( 'Next month', 'vaqtyar' ) }
						</Button>
					</div>
					<div className="vqy-date__grid">
						{ weekdayNames().map( ( name ) => (
							<span
								key={ name }
								className="vqy-date__weekday"
								title={ name }
							>
								{ shortDay( name ) }
							</span>
						) ) }
						{ Array.from( { length: grid.blanks }, ( _, index ) => (
							<span key={ `blank-${ index }` } />
						) ) }
						{ grid.dates.map( ( iso, index ) => (
							<button
								key={ iso }
								type="button"
								className="vqy-date__day"
								aria-pressed={ iso === value }
								disabled={ ! allowed( iso ) }
								onClick={ () => {
									onChange( iso );
									setOpen( false );
								} }
							>
								{ formatDigits( String( index + 1 ), digits ) }
							</button>
						) ) }
					</div>
				</div>
			) }
		</div>
	);
}

/**
 * A date and a time of day, as the local "2026-09-25T10:00" the API takes;
 * "" while either is empty.
 *
 * @param props
 * @param props.label
 * @param props.value
 * @param props.onChange
 */
export function DateTimeField( {
	label,
	value,
	onChange,
}: {
	label: string;
	value: string;
	onChange: ( value: string ) => void;
} ) {
	const [ date = '', time = '' ] = value.split( 'T' );

	return (
		<div className="vqy-date__pair">
			<DateField
				label={ label }
				value={ date }
				onChange={ ( next ) =>
					onChange(
						next === '' ? '' : `${ next }T${ time || '00:00' }`
					)
				}
			/>
			<TextControl
				{ ...SIZE }
				type="time"
				label={ sprintf(
					/* translators: %s: the name of a date field, e.g. "Valid from". */
					__( '%s (time)', 'vaqtyar' ),
					label
				) }
				value={ time }
				disabled={ date === '' }
				onChange={ ( next ) =>
					onChange( date === '' ? '' : `${ date }T${ next }` )
				}
			/>
		</div>
	);
}
