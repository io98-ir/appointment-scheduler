import {
	Button,
	Modal,
	Notice,
	SearchControl,
	Spinner,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { ReactNode } from 'react';

import { errorMessage } from '../query';
import { useAll, useRemove } from './crud';

/** A column after the name, which every list has first. */
export interface Column< T > {
	id: string;
	label: string;
	render: ( item: T ) => ReactNode;
}

interface Props< T > {
	/** e.g. "/staff"; the list's route and REST base alike. */
	base: string;
	columns: Column< T >[];
	/** Label of the button that opens an empty form. */
	addLabel: string;
	/** Shown next to that button, e.g. a link to a related screen. */
	extra?: ReactNode;
}

/**
 * Lowercase, Arabic ي and ك as Persian ی and ک, so a search finds the name
 * however it was typed (as the server's search does, docs/api.md).
 *
 * @param text
 */
export function normalize( text: string ): string {
	return text.replace( /ي/g, 'ی' ).replace( /ك/g, 'ک' ).toLowerCase().trim();
}

/**
 * The list of a catalog resource, in the API's order (sort, then id), with
 * a search on the name, a link to edit each item and a delete that asks
 * first. A catalog is small, so the whole of it is shown (crud.ts); no
 * DataViews (ADR-019).
 * @param root0
 * @param root0.base
 * @param root0.columns
 * @param root0.addLabel
 * @param root0.extra
 */
export function CatalogList< T extends { id: number; name: string } >( {
	base,
	columns,
	addLabel,
	extra,
}: Props< T > ) {
	const items = useAll< T >( base );
	const [ search, setSearch ] = useState( '' );
	const [ deleting, setDeleting ] = useState< T | null >( null );
	const shown = ( items.data ?? [] ).filter( ( item ) =>
		normalize( item.name ).includes( normalize( search ) )
	);

	return (
		<>
			<div className="vqy-admin__toolbar">
				<Button variant="primary" href={ `#${ base }/new` }>
					{ addLabel }
				</Button>
				{ extra }
				<SearchControl
					__nextHasNoMarginBottom
					label={ __( 'Search by name', 'vaqtyar' ) }
					value={ search }
					onChange={ setSearch }
				/>
			</div>
			{ items.isError && (
				<Notice status="error" isDismissible={ false }>
					{ errorMessage( items.error ) }
				</Notice>
			) }
			{ items.isPending && <Spinner /> }
			{ items.data && (
				<table className="widefat striped vqy-admin__table">
					<thead>
						<tr>
							<th scope="col">{ __( 'Name', 'vaqtyar' ) }</th>
							{ columns.map( ( column ) => (
								<th scope="col" key={ column.id }>
									{ column.label }
								</th>
							) ) }
							<th scope="col">
								<span className="screen-reader-text">
									{ __( 'Actions', 'vaqtyar' ) }
								</span>
							</th>
						</tr>
					</thead>
					<tbody>
						{ shown.length === 0 && (
							<tr>
								<td colSpan={ columns.length + 2 }>
									{ search === ''
										? __( 'Nothing here yet.', 'vaqtyar' )
										: __( 'No match.', 'vaqtyar' ) }
								</td>
							</tr>
						) }
						{ shown.map( ( item ) => (
							<tr key={ item.id }>
								<td>
									<a href={ `#${ base }/${ item.id }` }>
										<strong>{ item.name }</strong>
									</a>
								</td>
								{ columns.map( ( column ) => (
									<td key={ column.id }>
										{ column.render( item ) }
									</td>
								) ) }
								<td className="vqy-admin__actions">
									<Button
										variant="link"
										isDestructive
										onClick={ () => setDeleting( item ) }
									>
										{ __( 'Delete', 'vaqtyar' ) }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
			{ deleting && (
				<ConfirmDelete
					base={ base }
					item={ deleting }
					onClose={ () => setDeleting( null ) }
				/>
			) }
		</>
	);
}

function ConfirmDelete( {
	base,
	item,
	onClose,
}: {
	base: string;
	item: { id: number; name: string };
	onClose: () => void;
} ) {
	const remove = useRemove( base );

	return (
		<Modal
			title={ __( 'Delete this item?', 'vaqtyar' ) }
			onRequestClose={ onClose }
			size="small"
		>
			<p>{ item.name }</p>
			<div className="vqy-admin__buttons">
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'vaqtyar' ) }
				</Button>
				<Button
					variant="primary"
					isDestructive
					isBusy={ remove.isPending }
					disabled={ remove.isPending }
					onClick={ () =>
						remove.mutate( item.id, { onSettled: onClose } )
					}
				>
					{ __( 'Delete', 'vaqtyar' ) }
				</Button>
			</div>
		</Modal>
	);
}
