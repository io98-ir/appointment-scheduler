import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
	formatDate,
	type AppointmentListItem,
	type Location,
	type Service,
	type Staff,
} from '@vaqtyar/shared';
import {
	Button,
	ButtonGroup,
	Modal,
	Notice,
	SelectControl,
	Spinner,
	TextareaControl,
	TextControl,
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';
import type { FormEvent, PointerEvent } from 'react';

import { useApi } from '../api';
import { useAll } from '../catalog/crud';
import { SIZE } from '../catalog/fields';
import { weekdayNames } from '../catalog/WeeklySchedule';
import { errorMessage } from '../query';
import { useRoute } from '../router';
import { reschedule, type MoveRequest } from './move';
import { QuickBook, type Slot } from './QuickBook';
import {
	addDays,
	DAY,
	isoAt,
	lanes,
	offsetOf,
	snap,
	todayIn,
	wallClock,
	weekOf,
} from './time';

type View = 'day' | 'week';

/** Minutes a click or a drop snaps to. */
const STEP = 15;

/** Hours the grid shows at least; it grows to fit what is booked outside. */
const FIRST = 7 * 60;
const LAST = 22 * 60;

/**
 * The route of a view: "/calendar/day/2026-10-03", so a reload or a shared
 * link opens the same day.
 *
 * @param route From useRoute().
 */
export function viewOf( route: string ): { view: View; date: string | null } {
	const [ , , view, date ] = route.split( '/' );

	return {
		view: view === 'week' ? 'week' : 'day',
		date: date && /^\d{4}-\d{2}-\d{2}$/.test( date ) ? date : null,
	};
}

function itemClass( status: AppointmentListItem[ 'status' ] ): string {
	if ( status === 'pending_approval' || status === 'pending_payment' ) {
		return 'vqy-calendar__item vqy-calendar__item--pending';
	}
	if ( status === 'completed' || status === 'no_show' ) {
		return 'vqy-calendar__item vqy-calendar__item--done';
	}

	return 'vqy-calendar__item';
}

function go( view: View, date: string ) {
	window.location.hash = `#/calendar/${ view }/${ date }`;
}

interface Drag {
	id: number;
	/** Where the pointer went down. */
	x: number;
	y: number;
	/** How far below the item's top it was grabbed. */
	grab: number;
	moved: boolean;
}

interface Column {
	key: string;
	date: string;
	staffId: number;
	label: string;
}

/**
 * The admin calendar (T3.3): a day with a column per staff member, or a
 * staff member's week. An appointment is dragged to move it, or clicked to
 * move it with the keyboard; an empty time is clicked to book it.
 */
export function CalendarPage() {
	const route = useRoute();
	const { view, date: routeDate } = viewOf( route );
	const locations = useAll< Location >( '/locations' );
	const staff = useAll< Staff >( '/staff' );
	const services = useAll< Service >( '/services' );
	const [ locationId, setLocationId ] = useState< number | null >( null );
	const [ weekStaff, setWeekStaff ] = useState< number | null >( null );

	if ( locations.isError || staff.isError || services.isError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ errorMessage(
					locations.error ?? staff.error ?? services.error
				) }
			</Notice>
		);
	}
	if ( ! locations.data || ! staff.data || ! services.data ) {
		return <Spinner />;
	}
	const active = locations.data.filter(
		( item ) => item.status === 'active'
	);
	const location =
		active.find( ( item ) => item.id === locationId ) ?? active[ 0 ];
	if ( location === undefined ) {
		return (
			<p>
				{ __(
					'Add a location with staff to see its calendar.',
					'vaqtyar'
				) }{ ' ' }
				<a href="#/locations/new">
					{ __( 'Add location', 'vaqtyar' ) }
				</a>
			</p>
		);
	}
	const people = staff.data.filter(
		( item ) =>
			item.status === 'active' &&
			( item.location_id === null || item.location_id === location.id )
	);
	const date = routeDate ?? todayIn( location.timezone );
	const shown =
		people.find( ( item ) => item.id === weekStaff ) ?? people[ 0 ];
	const days = weekdayNames();
	const dayLabel = ( day: string ) =>
		`${ days[ weekOf( day ).indexOf( day ) ] } ${ formatDate(
			day,
			'jalali',
			'latin'
		) }`;
	const week = shown === undefined ? [] : weekOf( date );
	const columns: Column[] =
		view === 'day'
			? people.map( ( item ) => ( {
					key: String( item.id ),
					date,
					staffId: item.id,
					label: item.name,
				} ) )
			: week.map( ( day ) => ( {
					key: day,
					date: day,
					staffId: shown?.id ?? 0,
					label: dayLabel( day ),
				} ) );
	const step = view === 'day' ? 1 : 7;
	const first = view === 'day' ? date : ( weekOf( date )[ 0 ] ?? date );

	return (
		<div className="vqy-calendar">
			<div className="vqy-admin__toolbar">
				<ButtonGroup>
					<Button
						variant="secondary"
						onClick={ () => go( view, addDays( date, -step ) ) }
					>
						{ __( 'Previous', 'vaqtyar' ) }
					</Button>
					<Button
						variant="secondary"
						onClick={ () =>
							go( view, todayIn( location.timezone ) )
						}
					>
						{ __( 'Today', 'vaqtyar' ) }
					</Button>
					<Button
						variant="secondary"
						onClick={ () => go( view, addDays( date, step ) ) }
					>
						{ __( 'Next', 'vaqtyar' ) }
					</Button>
				</ButtonGroup>
				<strong dir="auto">
					{ view === 'day'
						? dayLabel( date )
						: `${ formatDate(
								first,
								'jalali',
								'latin'
							) } – ${ formatDate(
								addDays( first, 6 ),
								'jalali',
								'latin'
							) }` }
				</strong>
				<ButtonGroup>
					<Button
						variant={ view === 'day' ? 'primary' : 'secondary' }
						isPressed={ view === 'day' }
						onClick={ () => go( 'day', date ) }
					>
						{ __( 'Day', 'vaqtyar' ) }
					</Button>
					<Button
						variant={ view === 'week' ? 'primary' : 'secondary' }
						isPressed={ view === 'week' }
						onClick={ () => go( 'week', date ) }
					>
						{ __( 'Week', 'vaqtyar' ) }
					</Button>
				</ButtonGroup>
				<SelectControl
					{ ...SIZE }
					label={ __( 'Location', 'vaqtyar' ) }
					value={ String( location.id ) }
					options={ active.map( ( item ) => ( {
						value: String( item.id ),
						label: item.name,
					} ) ) }
					onChange={ ( value ) => setLocationId( Number( value ) ) }
				/>
				{ view === 'week' && shown !== undefined && (
					<SelectControl
						{ ...SIZE }
						label={ __( 'Staff', 'vaqtyar' ) }
						value={ String( shown.id ) }
						options={ people.map( ( item ) => ( {
							value: String( item.id ),
							label: item.name,
						} ) ) }
						onChange={ ( value ) =>
							setWeekStaff( Number( value ) )
						}
					/>
				) }
			</div>
			{ columns.length === 0 ? (
				<p>{ __( 'This location has no active staff.', 'vaqtyar' ) }</p>
			) : (
				<Grid
					location={ location }
					columns={ columns }
					from={ first }
					to={ addDays( first, step ) }
					staff={ people }
					services={ services.data }
				/>
			) }
		</div>
	);
}

function Grid( {
	location,
	columns,
	from,
	to,
	staff,
	services,
}: {
	location: Location;
	columns: Column[];
	from: string;
	to: string;
	staff: Staff[];
	services: Service[];
} ) {
	const api = useApi();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const offset = offsetOf( location.timezone, from );
	const range = {
		from: isoAt( from, 0, offset ),
		to: isoAt( to, 0, offset ),
		location: location.id,
	};
	const key = [ '/calendar', range ];
	const items = useQuery( {
		queryKey: key,
		queryFn: () => api.get< AppointmentListItem[] >( '/calendar', range ),
	} );
	const [ refused, setRefused ] = useState< {
		request: MoveRequest;
		message: string;
	} | null >( null );
	const [ moving, setMoving ] = useState< AppointmentListItem | null >(
		null
	);
	const [ booking, setBooking ] = useState< Slot | null >( null );
	const grid = useRef< HTMLDivElement >( null );
	/** The pointer drag in progress, and whether it ended as a drag. */
	const drag = useRef< Drag | null >( null );
	const dragged = useRef( false );
	const [ offsetBy, setOffsetBy ] = useState< {
		id: number;
		x: number;
		y: number;
	} | null >( null );
	const move = useMutation( {
		mutationFn: ( request: MoveRequest ) => reschedule( api, request ),
		onSuccess: ( result, request ) => {
			if ( ! result.moved ) {
				setRefused( { request, message: result.refusal } );

				return;
			}
			setRefused( null );
			setMoving( null );
			void client.invalidateQueries( { queryKey: [ '/calendar' ] } );
			void createSuccessNotice( __( 'Appointment moved.', 'vaqtyar' ), {
				type: 'snackbar',
			} );
		},
	} );

	const list = items.data ?? [];
	const minutes = list.flatMap( ( item ) => {
		const start = wallClock( item.start );
		const end = wallClock( item.end );

		return [ start.minutes, end.date === start.date ? end.minutes : DAY ];
	} );
	const top = Math.floor( Math.min( FIRST, ...minutes ) / 60 ) * 60;
	const bottom = Math.ceil( Math.max( LAST, ...minutes ) / 60 ) * 60;
	const at = ( clientY: number, element: Element ) =>
		snap( top + clientY - element.getBoundingClientRect().top, STEP );
	const drop = ( event: PointerEvent, state: Drag ) => {
		// The column under the pointer, by its horizontal span.
		const target = Array.from(
			grid.current?.querySelectorAll( '[data-column-key]' ) ?? []
		).find( ( element ) => {
			const rect = element.getBoundingClientRect();

			return event.clientX >= rect.left && event.clientX < rect.right;
		} );
		const column = columns.find(
			( item ) => item.key === target?.getAttribute( 'data-column-key' )
		);
		if ( target && column ) {
			move.mutate( {
				id: state.id,
				start: isoAt(
					column.date,
					at( event.clientY - state.grab, target ),
					offset
				),
				staff: column.staffId,
			} );
		}
	};
	const hours = Array.from(
		{ length: ( bottom - top ) / 60 },
		( _, i ) => top + i * 60
	);
	const color = ( id: number ) =>
		staff.find( ( item ) => item.id === id )?.color ?? '#888888';

	return (
		<>
			{ items.isError && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( items.error ) }
				</Notice>
			) }
			<div className="vqy-admin__toolbar">
				<Button
					variant="primary"
					onClick={ () =>
						setBooking( {
							date: columns[ 0 ]?.date ?? from,
							minutes: 9 * 60,
							staffId: columns[ 0 ]?.staffId ?? 0,
						} )
					}
				>
					{ __( 'New appointment', 'vaqtyar' ) }
				</Button>
				{ ( items.isFetching || move.isPending ) && <Spinner /> }
			</div>
			<div
				className="vqy-calendar__grid"
				style={ {
					gridTemplateColumns: `4em repeat(${ columns.length }, minmax(8em, 1fr))`,
				} }
			>
				<div className="vqy-calendar__corner" />
				{ columns.map( ( column ) => (
					<div
						key={ column.key }
						className="vqy-calendar__head"
						dir="auto"
					>
						{ column.label }
					</div>
				) ) }
				<div
					className="vqy-calendar__hours"
					style={ { blockSize: bottom - top } }
				>
					{ hours.map( ( hour ) => (
						<span
							key={ hour }
							dir="ltr"
							style={ { insetBlockStart: hour - top } }
						>
							{ isoAt( from, hour, offset ).slice( 11, 16 ) }
						</span>
					) ) }
				</div>
				{ columns.map( ( column ) => {
					const own = list.filter(
						( item ) =>
							item.staff_id === column.staffId &&
							wallClock( item.start ).date === column.date
					);
					const spans = own.map( ( item ) => {
						const start = wallClock( item.start ).minutes;
						const end = wallClock( item.end );

						return {
							id: item.id,
							from: start,
							to: end.date === column.date ? end.minutes : DAY,
						};
					} );
					const placed = lanes( spans );

					return (
						// Clicks book; the keyboard has "New appointment".
						// eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions
						<div
							key={ column.key }
							className="vqy-calendar__column"
							data-column={ column.label }
							data-column-key={ column.key }
							style={ {
								blockSize: bottom - top,
								backgroundSize: `100% 60px`,
							} }
							onClick={ ( event ) => {
								if ( event.target === event.currentTarget ) {
									setBooking( {
										date: column.date,
										minutes: at(
											event.clientY,
											event.currentTarget
										),
										staffId: column.staffId,
									} );
								}
							} }
						>
							{ own.map( ( item ) => {
								const span = spans.find(
									( s ) => s.id === item.id
								);
								const lane = placed.get( item.id );
								if ( ! span || ! lane ) {
									return null;
								}

								return (
									<button
										key={ item.id }
										type="button"
										className={ itemClass( item.status ) }
										style={ {
											insetBlockStart: span.from - top,
											blockSize: Math.max(
												span.to - span.from,
												15
											),
											insetInlineStart: `${
												( lane.lane * 100 ) / lane.of
											}%`,
											inlineSize: `${ 100 / lane.of }%`,
											borderInlineStartColor: color(
												item.staff_id
											),
											...( offsetBy?.id === item.id && {
												transform: `translate(${ offsetBy.x }px, ${ offsetBy.y }px)`,
												zIndex: 3,
											} ),
										} }
										onPointerDown={ ( event ) => {
											if ( event.button !== 0 ) {
												return;
											}
											event.currentTarget.setPointerCapture(
												event.pointerId
											);
											drag.current = {
												id: item.id,
												x: event.clientX,
												y: event.clientY,
												grab:
													event.clientY -
													event.currentTarget.getBoundingClientRect()
														.top,
												moved: false,
											};
										} }
										onPointerMove={ ( event ) => {
											const state = drag.current;
											if (
												! state ||
												state.id !== item.id
											) {
												return;
											}
											const x = event.clientX - state.x;
											const y = event.clientY - state.y;
											if (
												! state.moved &&
												Math.hypot( x, y ) < 5
											) {
												return;
											}
											state.moved = true;
											setOffsetBy( {
												id: item.id,
												x,
												y,
											} );
										} }
										onPointerUp={ ( event ) => {
											const state = drag.current;
											drag.current = null;
											setOffsetBy( null );
											if ( state?.moved ) {
												dragged.current = true;
												drop( event, state );
											}
										} }
										onPointerCancel={ () => {
											drag.current = null;
											setOffsetBy( null );
										} }
										onClick={ () => {
											// The click that ends a drag opens nothing.
											if ( dragged.current ) {
												dragged.current = false;

												return;
											}
											setMoving( item );
										} }
									>
										<span dir="ltr">
											{ item.start.slice( 11, 16 ) }–
											{ item.end.slice( 11, 16 ) }
										</span>{ ' ' }
										<span dir="auto">
											{ item.customer?.name ?? '—' }
										</span>
									</button>
								);
							} ) }
						</div>
					);
				} ) }
			</div>
			{ moving && (
				<MoveDialog
					item={ moving }
					staff={ staff }
					busy={ move.isPending }
					onMove={ ( start, staffId ) =>
						move.mutate( {
							id: moving.id,
							start: isoAt(
								start.date,
								start.minutes,
								offsetOf( location.timezone, start.date )
							),
							staff: staffId,
						} )
					}
					onClose={ () => setMoving( null ) }
				/>
			) }
			{ refused && (
				<OverrideDialog
					message={ refused.message }
					busy={ move.isPending }
					onConfirm={ ( reason ) =>
						move.mutate( {
							...refused.request,
							override: true,
							reason,
						} )
					}
					onClose={ () => setRefused( null ) }
				/>
			) }
			{ booking && (
				<QuickBook
					slot={ booking }
					location={ location }
					staff={ staff }
					services={ services }
					onClose={ () => setBooking( null ) }
				/>
			) }
		</>
	);
}

/**
 * Moves an appointment without a mouse, and shows what it is.
 *
 * @param props
 * @param props.item
 * @param props.staff
 * @param props.busy
 * @param props.onMove
 * @param props.onClose
 */
function MoveDialog( {
	item,
	staff,
	busy,
	onMove,
	onClose,
}: {
	item: AppointmentListItem;
	staff: Staff[];
	busy: boolean;
	onMove: (
		start: { date: string; minutes: number },
		staffId: number
	) => void;
	onClose: () => void;
} ) {
	const start = wallClock( item.start );
	const [ date, setDate ] = useState( start.date );
	const [ time, setTime ] = useState( item.start.slice( 11, 16 ) );
	const [ staffId, setStaffId ] = useState( item.staff_id );
	const submit = ( event: FormEvent ) => {
		event.preventDefault();
		onMove(
			{ date, minutes: wallClock( `${ date }T${ time }` ).minutes },
			staffId
		);
	};

	return (
		<Modal
			title={ `${ item.customer?.name ?? '—' } · ${ item.code }` }
			onRequestClose={ onClose }
		>
			<form className="vqy-admin__form" onSubmit={ submit }>
				<TextControl
					{ ...SIZE }
					type="date"
					label={ __( 'Date', 'vaqtyar' ) }
					help={ formatDate( date, 'jalali', 'latin' ) }
					value={ date }
					required
					onChange={ setDate }
				/>
				<TextControl
					{ ...SIZE }
					type="time"
					step={ STEP * 60 }
					label={ __( 'Start', 'vaqtyar' ) }
					value={ time }
					required
					onChange={ setTime }
				/>
				<SelectControl
					{ ...SIZE }
					label={ __( 'Staff', 'vaqtyar' ) }
					value={ String( staffId ) }
					options={ staff.map( ( person ) => ( {
						value: String( person.id ),
						label: person.name,
					} ) ) }
					onChange={ ( value ) => setStaffId( Number( value ) ) }
				/>
				<div className="vqy-admin__buttons">
					<Button
						variant="primary"
						type="submit"
						isBusy={ busy }
						disabled={ busy || item.status !== 'confirmed' }
					>
						{ __( 'Move', 'vaqtyar' ) }
					</Button>
					<Button variant="tertiary" onClick={ onClose }>
						{ __( 'Cancel', 'vaqtyar' ) }
					</Button>
				</div>
			</form>
		</Modal>
	);
}

/**
 * The policy refused the move; staff with the right may make it anyway,
 * with a reason for the appointment's history.
 *
 * @param props
 * @param props.message
 * @param props.busy
 * @param props.onConfirm
 * @param props.onClose
 */
function OverrideDialog( {
	message,
	busy,
	onConfirm,
	onClose,
}: {
	message: string;
	busy: boolean;
	onConfirm: ( reason: string ) => void;
	onClose: () => void;
} ) {
	const [ reason, setReason ] = useState( '' );
	const submit = ( event: FormEvent ) => {
		event.preventDefault();
		onConfirm( reason );
	};

	return (
		<Modal
			title={ __( 'The policy does not allow this move', 'vaqtyar' ) }
			onRequestClose={ onClose }
		>
			<form className="vqy-admin__form" onSubmit={ submit }>
				<p>{ message }</p>
				<TextareaControl
					__nextHasNoMarginBottom
					label={ __( 'Reason', 'vaqtyar' ) }
					value={ reason }
					required
					onChange={ setReason }
				/>
				<div className="vqy-admin__buttons">
					<Button
						variant="primary"
						isDestructive
						type="submit"
						isBusy={ busy }
						disabled={ busy || reason.trim() === '' }
					>
						{ __( 'Move anyway', 'vaqtyar' ) }
					</Button>
					<Button variant="tertiary" onClick={ onClose }>
						{ __( 'Cancel', 'vaqtyar' ) }
					</Button>
				</div>
			</form>
		</Modal>
	);
}
