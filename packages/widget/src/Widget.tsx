import {
	ApiClient,
	cursorOf,
	formatAmount,
	formatDate,
	formatDigits,
	monthGrid,
	monthName,
	shiftMonth,
	weekdayNames,
	type AvailabilityDay,
	type AvailabilityFirst,
	type AvailabilityMonth,
	type AvailabilitySlot,
	type Calendar,
	type Digits,
	type MenuService,
	type MenuStaff,
	type MonthCursor,
	type PublicMenu,
} from '@vaqtyar/shared';
import { __, sprintf } from '@wordpress/i18n';
import { useId, useMemo, useState } from 'preact/hooks';

import { BookingFlow } from './BookingFlow';
import { useFetch } from './useFetch';

/**
 * What the shortcode or block passes in (T4.5). `restUrl` is the plugin's
 * REST base; the rest preselects a step or picks how things are shown.
 */
export interface WidgetConfig {
	restUrl?: string;
	service?: number;
	variant?: number;
	location?: number;
	staff?: number;
	calendar?: Calendar;
	digits?: Digits;
	/** An address of the site to go to after a booking, with the code added. */
	thanks?: string;
	/** The query parameter a payment gateway's return page carries the outcome in. */
	paymentParam?: string;
	[ key: string ]: unknown;
}

/** What the customer chose: the start the later steps (T4.2) hold. */
export interface SlotChoice {
	service: number;
	variant: number;
	location: number;
	/** Null lets the plugin assign a staff member. */
	staff: number | null;
	slot: AvailabilitySlot;
}

/** Fired on the widget's element when a slot is chosen. */
export const SLOT_EVENT = 'vqy:slot';

export function isNumber( value: unknown ): value is number {
	return typeof value === 'number' && Number.isInteger( value ) && value > 0;
}

function time( iso: string ): string {
	return iso.slice( 11, 16 );
}

function staffFor(
	service: MenuService,
	variantId: number | null
): MenuStaff[] {
	const seen = new Set< number >();

	return service.staff.filter( ( assignment ) => {
		const serves =
			assignment.variant_id === null ||
			assignment.variant_id === variantId;
		if ( ! serves || seen.has( assignment.staff_id ) ) {
			return false;
		}
		seen.add( assignment.staff_id );

		return true;
	} );
}

/**
 * What the payment gateway's return page says happened, from the address bar.
 *
 * @param param The query parameter the server put the outcome in.
 */
export function paymentOutcome(
	param: string | undefined
): 'succeeded' | 'failed' | 'pending' | null {
	if ( param === undefined || param === '' ) {
		return null;
	}
	const value = new URLSearchParams( window.location.search ).get( param );

	return value === 'succeeded' || value === 'failed' || value === 'pending'
		? value
		: null;
}

/**
 * The booking widget's first steps: the service, its variant, staff and
 * location, then a month calendar with the free days coloured and the free
 * starts of the chosen day. Choosing a start fires SLOT_EVENT; the later
 * steps (hold, form, payment) are T4.2.
 *
 * @param props
 * @param props.config
 * @param props.api
 */
export function Widget( {
	config,
	api,
}: {
	config: WidgetConfig;
	api?: ApiClient | undefined;
} ) {
	const uid = useId();
	const client = useMemo(
		() => api ?? new ApiClient( { baseUrl: config.restUrl ?? '' } ),
		[ api, config.restUrl ]
	);
	// A booking call sends a fresh nonce, never the page's own (it may be cached).
	const clientFor = ( nonce?: string ): ApiClient =>
		api ??
		new ApiClient( {
			baseUrl: config.restUrl ?? '',
			...( nonce === undefined ? {} : { nonce } ),
		} );
	const calendar: Calendar = config.calendar ?? 'jalali';
	const digits: Digits = config.digits ?? 'latin';
	const outcome = paymentOutcome( config.paymentParam );
	const menu = useFetch< PublicMenu >(
		config.restUrl !== undefined || api !== undefined ? 'menu' : null,
		() => client.get< PublicMenu >( '/catalog' )
	);
	const [ serviceId, setServiceId ] = useState< number | null >(
		isNumber( config.service ) ? config.service : null
	);
	const [ variantChoice, setVariantChoice ] = useState< number | null >(
		isNumber( config.variant ) ? config.variant : null
	);
	const [ locationChoice, setLocationChoice ] = useState< number | null >(
		isNumber( config.location ) ? config.location : null
	);
	const [ staffId, setStaffId ] = useState< number | null >(
		isNumber( config.staff ) ? config.staff : null
	);
	const [ cursor, setCursor ] = useState< MonthCursor | null >( null );
	const [ date, setDate ] = useState< string | null >( null );
	const [ chosen, setChosen ] = useState< AvailabilitySlot | null >( null );
	const [ coupon, setCoupon ] = useState( '' );
	const [ flow, setFlow ] = useState( false );
	// Bumped when a hold failed, so the free days and starts are read again.
	const [ refresh, setRefresh ] = useState( 0 );

	const services = menu.data?.services ?? [];
	const service =
		services.find( ( item ) => item.id === serviceId ) ??
		( services.length === 1 && serviceId === null
			? services[ 0 ]
			: undefined );
	const variant =
		service?.variants.find( ( item ) => item.id === variantChoice ) ??
		service?.variants.find( ( item ) => item.is_default ) ??
		service?.variants[ 0 ];
	const staff = service ? staffFor( service, variant?.id ?? null ) : [];
	const locations = ( menu.data?.locations ?? [] ).filter( ( location ) =>
		staff.some(
			( member ) =>
				member.location_id === null ||
				member.location_id === location.id
		)
	);
	const location =
		locations.find( ( item ) => item.id === locationChoice ) ??
		( locations.length === 1 ? locations[ 0 ] : undefined );
	const staffHere = staff.filter(
		( member ) =>
			member.location_id === null || member.location_id === location?.id
	);
	const staffPick = staffHere.some(
		( member ) => member.staff_id === staffId
	)
		? staffId
		: null;

	const ready = service && variant && location;
	const shown = cursor ?? cursorOf( calendar, todayOf() );
	const grid = monthGrid( calendar, shown );
	const monthTitle = `${ monthName( calendar, shown.month ) } ${ formatDigits(
		String( shown.year ),
		digits
	) }`;
	const base = ready
		? `variant=${ variant.id }&location=${ location.id }&staff=${ staffPick ?? '' }`
		: null;
	const query = {
		variant: variant?.id,
		location: location?.id,
		staff: staffPick ?? undefined,
	};
	const month = useFetch< AvailabilityMonth >(
		base === null ? null : `${ base }&month=${ grid.start }&r=${ refresh }`,
		() =>
			client.get< AvailabilityMonth >( '/availability', {
				...query,
				view: 'month',
				date: grid.start,
				days: grid.days,
			} )
	);
	const day = useFetch< AvailabilityDay >(
		base === null || date === null
			? null
			: `${ base }&day=${ date }&r=${ refresh }`,
		() =>
			client.get< AvailabilityDay >( '/availability', {
				...query,
				view: 'day',
				date: date ?? '',
			} )
	);
	const [ findError, setFindError ] = useState< string | null >( null );
	const reset = () => {
		setFlow( false );
		setDate( null );
		setChosen( null );
	};
	const findFirst = async () => {
		if ( ! ready ) {
			return;
		}
		setFindError( null );
		try {
			const found = await client.get< AvailabilityFirst >(
				'/availability',
				{
					...query,
					view: 'first',
					days: 62,
				}
			);
			if ( found.date === null ) {
				setFindError(
					__( 'No free time in the next two months.', 'vaqtyar' )
				);

				return;
			}
			setCursor( cursorOf( calendar, found.date ) );
			setDate( found.date );
			setChosen( null );
		} catch ( error ) {
			setFindError(
				error instanceof Error ? error.message : String( error )
			);
		}
	};
	const choose = ( slot: AvailabilitySlot, element: HTMLElement ) => {
		if ( ! ready ) {
			return;
		}
		setChosen( slot );
		const detail: SlotChoice = {
			service: service.id,
			variant: variant.id,
			location: location.id,
			staff: staffPick,
			slot,
		};
		element.dispatchEvent(
			new CustomEvent( SLOT_EVENT, { detail, bubbles: true } )
		);
	};

	return (
		<section
			className="vqy-widget"
			aria-label={ __( 'Book an appointment', 'vaqtyar' ) }
		>
			<p className="vqy-widget__title">
				{ __( 'Book an appointment', 'vaqtyar' ) }
			</p>
			{ outcome === 'succeeded' && (
				<p role="status" className="vqy-widget__done">
					{ __(
						'Your payment was received and your appointment is booked.',
						'vaqtyar'
					) }
				</p>
			) }
			{ outcome === 'pending' && (
				<p role="status" className="vqy-widget__done">
					{ __(
						'We are still confirming your payment. Your appointment will be booked as soon as it arrives.',
						'vaqtyar'
					) }
				</p>
			) }
			{ outcome === 'failed' && (
				<p role="alert" className="vqy-widget__error">
					{ __(
						'The payment was not completed, so the appointment was not booked. You can try again.',
						'vaqtyar'
					) }
				</p>
			) }
			{ menu.error && (
				<p role="alert" className="vqy-widget__error">
					{ menu.error }
				</p>
			) }
			{ config.restUrl === undefined && api === undefined && (
				<p role="alert" className="vqy-widget__error">
					{ __( 'The booking form is not configured.', 'vaqtyar' ) }
				</p>
			) }
			{ menu.loading && <p>{ __( 'Loading…', 'vaqtyar' ) }</p> }
			{ menu.data && services.length === 0 && (
				<p>{ __( 'Nothing can be booked right now.', 'vaqtyar' ) }</p>
			) }
			{ menu.data && services.length > 0 && (
				<div className="vqy-widget__choices">
					<label htmlFor={ `${ uid }-service` }>
						{ __( 'Service', 'vaqtyar' ) }
						<select
							id={ `${ uid }-service` }
							value={ service?.id ?? '' }
							onChange={ ( event ) => {
								setServiceId(
									Number( event.currentTarget.value ) || null
								);
								setVariantChoice( null );
								setLocationChoice( null );
								setStaffId( null );
								reset();
							} }
						>
							{ service === undefined && (
								<option value="">
									{ __( 'Choose a service', 'vaqtyar' ) }
								</option>
							) }
							{ services.map( ( item ) => (
								<option key={ item.id } value={ item.id }>
									{ item.name }
								</option>
							) ) }
						</select>
					</label>
					{ service && service.variants.length > 1 && (
						<label htmlFor={ `${ uid }-variant` }>
							{ __( 'Duration', 'vaqtyar' ) }
							<select
								id={ `${ uid }-variant` }
								value={ variant?.id }
								onChange={ ( event ) => {
									setVariantChoice(
										Number( event.currentTarget.value )
									);
									reset();
								} }
							>
								{ service.variants.map( ( item ) => (
									<option key={ item.id } value={ item.id }>
										{ sprintf(
											/* translators: 1: variant label, 2: minutes, 3: price. */
											__(
												'%1$s — %2$d min — %3$s IRR',
												'vaqtyar'
											),
											item.label,
											item.duration_min,
											formatAmount( item.price, digits )
										) }
									</option>
								) ) }
							</select>
						</label>
					) }
					{ service && locations.length > 1 && (
						<label htmlFor={ `${ uid }-location` }>
							{ __( 'Location', 'vaqtyar' ) }
							<select
								id={ `${ uid }-location` }
								value={ location?.id ?? '' }
								onChange={ ( event ) => {
									setLocationChoice(
										Number( event.currentTarget.value ) ||
											null
									);
									setStaffId( null );
									reset();
								} }
							>
								{ location === undefined && (
									<option value="">
										{ __( 'Choose a location', 'vaqtyar' ) }
									</option>
								) }
								{ locations.map( ( item ) => (
									<option key={ item.id } value={ item.id }>
										{ item.name }
									</option>
								) ) }
							</select>
						</label>
					) }
					{ ready && staffHere.length > 1 && (
						<label htmlFor={ `${ uid }-staff` }>
							{ __( 'With', 'vaqtyar' ) }
							<select
								id={ `${ uid }-staff` }
								value={ staffPick ?? '' }
								onChange={ ( event ) => {
									setStaffId(
										Number( event.currentTarget.value ) ||
											null
									);
									reset();
								} }
							>
								<option value="">
									{ __( 'Any available', 'vaqtyar' ) }
								</option>
								{ staffHere.map( ( member ) => (
									<option
										key={ member.staff_id }
										value={ member.staff_id }
									>
										{ member.name }
									</option>
								) ) }
							</select>
						</label>
					) }
				</div>
			) }
			{ ready && flow && chosen && (
				<BookingFlow
					choice={ {
						service: service.id,
						variant: variant.id,
						location: location.id,
						staff: staffPick,
						slot: chosen,
					} }
					serviceId={ service.id }
					coupon={ coupon.trim() }
					title={ service.name }
					thanksUrl={
						typeof config.thanks === 'string' ? config.thanks : ''
					}
					calendar={ calendar }
					digits={ digits }
					clientFor={ clientFor }
					onBack={ () => {
						setFlow( false );
						setChosen( null );
						setRefresh( refresh + 1 );
					} }
				/>
			) }
			{ ready && ! flow && (
				<div className="vqy-widget__calendar">
					<div className="vqy-widget__month">
						<button
							type="button"
							onClick={ () =>
								setCursor( shiftMonth( shown, -1 ) )
							}
							aria-label={ __( 'Previous month', 'vaqtyar' ) }
						>
							‹
						</button>
						<strong aria-live="polite">{ monthTitle }</strong>
						<button
							type="button"
							onClick={ () =>
								setCursor( shiftMonth( shown, 1 ) )
							}
							aria-label={ __( 'Next month', 'vaqtyar' ) }
						>
							›
						</button>
						<button
							type="button"
							onClick={ () => void findFirst() }
						>
							{ __( 'First available', 'vaqtyar' ) }
						</button>
					</div>
					{ month.error && (
						<p role="alert" className="vqy-widget__error">
							{ month.error }
						</p>
					) }
					{ findError && (
						<p role="alert" className="vqy-widget__error">
							{ findError }
						</p>
					) }
					<div
						className="vqy-widget__grid"
						role="group"
						aria-label={ monthTitle }
					>
						{ weekdayNames().map( ( name ) => (
							<span key={ name } className="vqy-widget__weekday">
								{ name }
							</span>
						) ) }
						{ Array.from( { length: grid.blanks }, ( _, index ) => (
							<span key={ `blank-${ index }` } />
						) ) }
						{ grid.dates.map( ( isoDate, index ) => {
							const status = month.data?.days.find(
								( item ) => item.date === isoDate
							)?.status;
							const label = formatDate(
								isoDate,
								calendar,
								digits
							);

							return (
								<button
									key={ isoDate }
									type="button"
									className={ `vqy-widget__day vqy-widget__day--${
										status ?? 'unknown'
									}` }
									disabled={ status !== 'available' }
									aria-pressed={ isoDate === date }
									aria-label={ label }
									onClick={ () => {
										setDate( isoDate );
										setChosen( null );
									} }
								>
									{ formatDigits(
										String( index + 1 ),
										digits
									) }
								</button>
							);
						} ) }
					</div>
					{ date && (
						<div className="vqy-widget__slots">
							{ day.loading && (
								<p>{ __( 'Loading…', 'vaqtyar' ) }</p>
							) }
							{ day.error && (
								<p role="alert" className="vqy-widget__error">
									{ day.error }
								</p>
							) }
							{ day.data?.slots.length === 0 && (
								<p>
									{ __(
										'No free time on this day.',
										'vaqtyar'
									) }
								</p>
							) }
							<ul className="vqy-widget__slot-list">
								{ day.data?.slots.map( ( slot ) => (
									<li key={ slot.start }>
										<button
											type="button"
											aria-pressed={
												chosen?.start === slot.start
											}
											onClick={ ( event ) =>
												choose(
													slot,
													event.currentTarget
												)
											}
										>
											{ formatDigits(
												time( slot.start ),
												digits
											) }
										</button>
									</li>
								) ) }
							</ul>
						</div>
					) }
					{ chosen && (
						<p className="vqy-widget__chosen" aria-live="polite">
							{ sprintf(
								/* translators: 1: a date, 2: a time. */
								__( 'Selected: %1$s at %2$s', 'vaqtyar' ),
								formatDate(
									chosen.start.slice( 0, 10 ),
									calendar,
									digits
								),
								formatDigits( time( chosen.start ), digits )
							) }
						</p>
					) }
					{ chosen && (
						<div className="vqy-widget__continue">
							<label htmlFor={ `${ uid }-coupon` }>
								{ __( 'Coupon code (optional)', 'vaqtyar' ) }
								<input
									id={ `${ uid }-coupon` }
									dir="ltr"
									value={ coupon }
									maxLength={ 64 }
									onInput={ ( event ) =>
										setCoupon( event.currentTarget.value )
									}
								/>
							</label>
							<button
								type="button"
								onClick={ () => setFlow( true ) }
							>
								{ __( 'Continue', 'vaqtyar' ) }
							</button>
						</div>
					) }
				</div>
			) }
		</section>
	);
}

function todayOf(): string {
	const now = new Date();

	return [
		now.getFullYear(),
		String( now.getMonth() + 1 ).padStart( 2, '0' ),
		String( now.getDate() ).padStart( 2, '0' ),
	].join( '-' );
}
