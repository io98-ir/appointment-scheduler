import {
	formatDate,
	formatDigits,
	parseTypedDate,
	type Calendar,
	type Digits,
} from '@vaqtyar/shared';
import { __ } from '@wordpress/i18n';
import { useState } from 'preact/hooks';

/**
 * A date in the visitor's calendar. The value is the Gregorian local date
 * the API takes ("2026-09-25", or "" while unset); a browser has no Jalali
 * date input, so that calendar is a typed field, kept light for the widget.
 *
 * @param props
 * @param props.id       The input's id, for its label.
 * @param props.value    A Gregorian local date or "".
 * @param props.calendar
 * @param props.digits
 * @param props.onChange
 */
export function DateInput( {
	id,
	value,
	calendar,
	digits,
	onChange,
}: {
	id: string;
	value: string;
	calendar: Calendar;
	digits: Digits;
	onChange: ( value: string ) => void;
} ) {
	const [ text, setText ] = useState( '' );

	if ( calendar === 'gregorian' ) {
		return (
			<input
				id={ id }
				type="date"
				value={ value }
				onInput={ ( e ) => onChange( e.currentTarget.value ) }
			/>
		);
	}
	const typed = text !== '' && parseTypedDate( text, calendar ) === null;

	return (
		<>
			<input
				id={ id }
				type="text"
				inputMode="numeric"
				dir="ltr"
				placeholder={ formatDigits( '1405/01/01', digits ) }
				value={ text }
				aria-invalid={ typed }
				onInput={ ( e ) => {
					const next = e.currentTarget.value;
					setText( next );
					onChange( parseTypedDate( next, calendar ) ?? '' );
				} }
			/>
			{ value !== '' && (
				<span className="vqy-widget__hint" dir="ltr">
					{ formatDate( value, calendar, digits ) }
				</span>
			) }
			{ typed && (
				<span role="alert" className="vqy-widget__error">
					{ __( 'Enter a date like 1405/07/03.', 'vaqtyar' ) }
				</span>
			) }
		</>
	);
}
