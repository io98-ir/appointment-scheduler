import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type {
	ScheduleOwnerType,
	ScheduleRule,
	WeeklySchedule as Weekly,
} from '@vaqtyar/shared';
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

import { useApi } from '../api';
import { errorMessage } from '../query';
import { SIZE } from './fields';

/** 0 = Saturday … 6 = Friday, as the API counts (docs/api.md). */
export function weekdayNames(): string[] {
	return [
		__( 'Saturday', 'vaqtyar' ),
		__( 'Sunday', 'vaqtyar' ),
		__( 'Monday', 'vaqtyar' ),
		__( 'Tuesday', 'vaqtyar' ),
		__( 'Wednesday', 'vaqtyar' ),
		__( 'Thursday', 'vaqtyar' ),
		__( 'Friday', 'vaqtyar' ),
	];
}

/**
 * The rules of one weekday copied to every other day, replacing theirs.
 *
 * @param rules   The whole week.
 * @param weekday The day to copy.
 */
export function copyToEveryDay(
	rules: ScheduleRule[],
	weekday: number
): ScheduleRule[] {
	const day = rules.filter( ( rule ) => rule.weekday === weekday );

	return [ 0, 1, 2, 3, 4, 5, 6 ].flatMap( ( other ) =>
		day.map( ( rule ) => ( { ...rule, weekday: other } ) )
	);
}

/**
 * An owner's hours of each weekday, work and breaks, saved as a whole week.
 *
 * @param props
 * @param props.ownerType
 * @param props.ownerId
 */
export function WeeklySchedule( {
	ownerType,
	ownerId,
}: {
	ownerType: ScheduleOwnerType;
	ownerId: number;
} ) {
	const api = useApi();
	const path = `/schedules/${ ownerType }/${ ownerId }`;
	const stored = useQuery( {
		queryKey: [ path ],
		queryFn: () => api.get< Weekly >( path ),
	} );

	return (
		<section className="vqy-admin__panel">
			<h2>{ __( 'Weekly hours', 'vaqtyar' ) }</h2>
			{ stored.isError && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( stored.error ) }
				</Notice>
			) }
			{ stored.isPending && <Spinner /> }
			{ stored.data && (
				<Editor path={ path } initial={ stored.data.rules } />
			) }
		</section>
	);
}

function Editor( {
	path,
	initial,
}: {
	path: string;
	initial: ScheduleRule[];
} ) {
	const api = useApi();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const [ rules, setRules ] = useState( initial );
	const save = useMutation( {
		mutationFn: () => api.put< Weekly >( path, { rules } ),
		onSuccess: ( saved ) => {
			setRules( saved.rules );
			client.setQueryData( [ path ], saved );
			void createSuccessNotice( __( 'Hours saved.', 'vaqtyar' ), {
				type: 'snackbar',
			} );
		},
	} );
	const change = ( index: number, patch: Partial< ScheduleRule > ) =>
		setRules(
			rules.map( ( rule, i ) =>
				i === index ? { ...rule, ...patch } : rule
			)
		);

	return (
		<>
			<table className="vqy-admin__week">
				<tbody>
					{ weekdayNames().map( ( name, weekday ) => (
						<tr key={ weekday }>
							<th scope="row">{ name }</th>
							<td>
								{ rules.every(
									( rule ) => rule.weekday !== weekday
								) && (
									<span className="vqy-admin__muted">
										{ __( 'Closed', 'vaqtyar' ) }
									</span>
								) }
								{ rules.map(
									( rule, index ) =>
										rule.weekday === weekday && (
											<div
												className="vqy-admin__range"
												key={ index }
											>
												<SelectControl
													{ ...SIZE }
													label={ __(
														'Kind',
														'vaqtyar'
													) }
													hideLabelFromVision
													value={ rule.kind }
													options={ [
														{
															value: 'work',
															label: __(
																'Work',
																'vaqtyar'
															),
														},
														{
															value: 'break',
															label: __(
																'Break',
																'vaqtyar'
															),
														},
													] }
													onChange={ ( kind ) =>
														change( index, {
															kind: kind as ScheduleRule[ 'kind' ],
														} )
													}
												/>
												<TextControl
													{ ...SIZE }
													type="time"
													label={ __(
														'From',
														'vaqtyar'
													) }
													hideLabelFromVision
													value={ rule.start }
													onChange={ ( start ) =>
														change( index, {
															start,
														} )
													}
												/>
												<TextControl
													{ ...SIZE }
													type="time"
													label={ __(
														'To',
														'vaqtyar'
													) }
													hideLabelFromVision
													value={ rule.end }
													onChange={ ( end ) =>
														change( index, { end } )
													}
												/>
												<Button
													variant="tertiary"
													isDestructive
													onClick={ () =>
														setRules(
															rules.filter(
																( _, i ) =>
																	i !== index
															)
														)
													}
												>
													{ __(
														'Remove',
														'vaqtyar'
													) }
												</Button>
											</div>
										)
								) }
								<div className="vqy-admin__buttons">
									<Button
										variant="secondary"
										size="compact"
										onClick={ () =>
											setRules( [
												...rules,
												{
													weekday,
													start: '09:00',
													end: '17:00',
													kind: 'work',
												},
											] )
										}
									>
										{ __( 'Add hours', 'vaqtyar' ) }
									</Button>
									<Button
										variant="tertiary"
										size="compact"
										onClick={ () =>
											setRules(
												copyToEveryDay( rules, weekday )
											)
										}
									>
										{ __( 'Copy to every day', 'vaqtyar' ) }
									</Button>
								</div>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
			<Button
				variant="primary"
				isBusy={ save.isPending }
				disabled={ save.isPending }
				onClick={ () => save.mutate() }
			>
				{ __( 'Save hours', 'vaqtyar' ) }
			</Button>
		</>
	);
}
