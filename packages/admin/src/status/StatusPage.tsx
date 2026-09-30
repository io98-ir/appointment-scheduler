import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { ModuleSwitch, SystemStatus } from '@vaqtyar/shared';
import { Notice, Spinner, ToggleControl } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

import { useApi } from '../api';
import { errorMessage } from '../query';

const STATUS_LABEL: Record<
	SystemStatus[ 'checks' ][ number ][ 'status' ],
	() => string
> = {
	good: () => __( 'Good', 'vaqtyar' ),
	recommended: () => __( 'Should be improved', 'vaqtyar' ),
	critical: () => __( 'Needs attention', 'vaqtyar' ),
};

/** The optional modules' names, for the toggles. */
const MODULE_NAME: Record< string, () => string > = {
	notifications: () => __( 'Notifications (email and SMS)', 'vaqtyar' ),
	widget: () => __( 'Booking widget, shortcode and block', 'vaqtyar' ),
};

/**
 * Turns an optional module on or off. The change applies from the next page
 * load, when the plugin picks the modules it starts.
 *
 * @param props
 * @param props.modules The modules from GET /status.
 */
function Modules( { modules }: { modules: ModuleSwitch[] } ) {
	const api = useApi();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const toggle = useMutation( {
		mutationFn: ( { id, enabled }: { id: string; enabled: boolean } ) =>
			api.put< { modules: ModuleSwitch[] } >( '/modules/' + id, {
				enabled,
			} ),
		onSuccess: ( saved ) => {
			client.setQueryData< SystemStatus >( [ '/status' ], ( current ) =>
				current ? { ...current, modules: saved.modules } : current
			);
			void createSuccessNotice(
				__( 'Saved. It applies from the next page load.', 'vaqtyar' ),
				{ type: 'snackbar' }
			);
		},
	} );

	return (
		<div className="vqy-admin__form">
			{ toggle.isError && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( toggle.error ) }
				</Notice>
			) }
			{ modules
				.filter( ( module ) => module.switchable )
				.map( ( module ) => (
					<ToggleControl
						key={ module.id }
						__nextHasNoMarginBottom
						label={ (
							MODULE_NAME[ module.id ] ?? ( () => module.id )
						)() }
						checked={ module.enabled }
						disabled={ toggle.isPending }
						onChange={ ( enabled ) =>
							toggle.mutate( { id: module.id, enabled } )
						}
					/>
				) ) }
		</div>
	);
}

/**
 * Whether deleting the plugin from WordPress also deletes every booking,
 * customer and setting (T6.5). Off until the owner turns it on.
 *
 * @param props
 * @param props.enabled The choice from GET /status.
 */
function DeleteData( { enabled }: { enabled: boolean } ) {
	const api = useApi();
	const client = useQueryClient();
	const { createSuccessNotice } = useDispatch( noticesStore );
	const save = useMutation( {
		mutationFn: ( deleteOnUninstall: boolean ) =>
			api.put< { delete_on_uninstall: boolean } >( '/data', {
				delete_on_uninstall: deleteOnUninstall,
			} ),
		onSuccess: ( saved ) => {
			client.setQueryData< SystemStatus >( [ '/status' ], ( current ) =>
				current
					? {
							...current,
							delete_on_uninstall: saved.delete_on_uninstall,
						}
					: current
			);
			void createSuccessNotice( __( 'Saved.', 'vaqtyar' ), {
				type: 'snackbar',
			} );
		},
	} );

	return (
		<div className="vqy-admin__form">
			{ save.isError && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( save.error ) }
				</Notice>
			) }
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __(
					'Delete all data when the plugin is deleted',
					'vaqtyar'
				) }
				help={ __(
					'Deleting the plugin from the Plugins screen then removes every booking, customer, payment and setting for good. Off keeps them, so a reinstall finds everything as it was.',
					'vaqtyar'
				) }
				checked={ enabled }
				disabled={ save.isPending }
				onChange={ ( value ) => save.mutate( value ) }
			/>
		</div>
	);
}

/**
 * System status (T6.2): what the server offers the plugin, the state of the
 * job queue, recent errors and the optional modules. WordPress Site Health
 * carries the same checks under Tools.
 */
export function StatusPage() {
	const api = useApi();
	const status = useQuery( {
		queryKey: [ '/status' ],
		queryFn: () => api.get< SystemStatus >( '/status' ),
	} );

	if ( status.isPending ) {
		return <Spinner />;
	}
	if ( status.isError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ errorMessage( status.error ) }
			</Notice>
		);
	}
	const { versions, checks, queue, modules, errors } = status.data;
	const deleteOnUninstall = status.data.delete_on_uninstall;

	return (
		<>
			<h2>{ __( 'Health', 'vaqtyar' ) }</h2>
			<ul className="vqy-admin__checks">
				{ checks.map( ( check ) => (
					<li
						key={ check.id }
						className={ `vqy-admin__check vqy-admin__check--${ check.status }` }
					>
						<strong>{ check.label }</strong>{ ' ' }
						<span className="vqy-admin__badge">
							{ STATUS_LABEL[ check.status ]() }
						</span>
						<p className="vqy-admin__muted">
							{ check.description }
						</p>
					</li>
				) ) }
			</ul>

			<h2>{ __( 'Job queue', 'vaqtyar' ) }</h2>
			<div className="vqy-admin__stats">
				<div className="vqy-admin__stat">
					<span className="vqy-admin__muted">
						{ __( 'Waiting', 'vaqtyar' ) }
					</span>
					<strong>{ queue.pending }</strong>
				</div>
				<div className="vqy-admin__stat">
					<span className="vqy-admin__muted">
						{ __( 'Overdue', 'vaqtyar' ) }
					</span>
					<strong>{ queue.late }</strong>
				</div>
				<div className="vqy-admin__stat">
					<span className="vqy-admin__muted">
						{ __( 'Failed this week', 'vaqtyar' ) }
					</span>
					<strong>{ queue.failed }</strong>
				</div>
			</div>

			<h2>{ __( 'Optional modules', 'vaqtyar' ) }</h2>
			<Modules modules={ modules } />

			<h2>{ __( 'Data', 'vaqtyar' ) }</h2>
			<DeleteData enabled={ deleteOnUninstall } />

			<h2>{ __( 'Recent errors', 'vaqtyar' ) }</h2>
			{ errors.length === 0 ? (
				<p className="vqy-admin__muted">
					{ __( 'No errors were logged.', 'vaqtyar' ) }
				</p>
			) : (
				<table className="widefat striped vqy-admin__table">
					<thead>
						<tr>
							<th>{ __( 'Time (UTC)', 'vaqtyar' ) }</th>
							<th>{ __( 'Area', 'vaqtyar' ) }</th>
							<th>{ __( 'Message', 'vaqtyar' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ errors.map( ( error, index ) => (
							<tr key={ index }>
								<td>{ error.at }</td>
								<td>{ error.channel }</td>
								<td>{ error.message }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }

			<h2>{ __( 'Versions', 'vaqtyar' ) }</h2>
			<table className="widefat striped vqy-admin__table">
				<tbody>
					<tr>
						<th>{ __( 'Plugin', 'vaqtyar' ) }</th>
						<td>{ versions.plugin }</td>
					</tr>
					<tr>
						<th>WordPress</th>
						<td>{ versions.wordpress }</td>
					</tr>
					<tr>
						<th>PHP</th>
						<td>{ versions.php }</td>
					</tr>
					<tr>
						<th>{ __( 'Database', 'vaqtyar' ) }</th>
						<td>{ versions.database }</td>
					</tr>
					<tr>
						<th>{ __( 'Schema', 'vaqtyar' ) }</th>
						<td>
							{ Object.entries( status.data.schema )
								.map( ( [ owner, count ] ) =>
									sprintf(
										/* translators: 1: module id, 2: number of migrations run */
										__( '%1$s: %2$d', 'vaqtyar' ),
										owner,
										count
									)
								)
								.join( ', ' ) }
						</td>
					</tr>
				</tbody>
			</table>
		</>
	);
}
