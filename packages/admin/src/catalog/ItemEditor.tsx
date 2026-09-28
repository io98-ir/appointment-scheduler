import { Button, Notice, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { FormEvent, ReactNode } from 'react';

import { errorMessage } from '../query';
import { useItem, useSave } from './crud';

type Draft< T > = Omit< T, 'id' >;

interface Props< T extends { id: number } > {
	/** e.g. "/staff". */
	base: string;
	id: 'new' | number;
	/** A new item's fields. */
	empty: Draft< T >;
	/** The form's fields, on the draft. */
	children: (
		draft: Draft< T >,
		change: ( patch: Partial< Draft< T > > ) => void
	) => ReactNode;
	/** Below the form, for a stored item only: e.g. its schedule. */
	after?: ( item: T ) => ReactNode;
}

/**
 * Loads an item (or starts from `empty`), edits a draft of it and saves it
 * whole: the API's PUT replaces every field (docs/api.md), so the draft
 * keeps the fields the form does not show. A new item opens as the stored
 * one after its first save, for the parts that need an id.
 * @param root0
 * @param root0.base
 * @param root0.id
 * @param root0.empty
 * @param root0.children
 * @param root0.after
 */
export function ItemEditor< T extends { id: number } >( {
	base,
	id,
	empty,
	children,
	after,
}: Props< T > ) {
	const item = useItem< T >( base, id === 'new' ? null : id );

	if ( id !== 'new' && item.isError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ errorMessage( item.error ) }
			</Notice>
		);
	}
	if ( id !== 'new' && ! item.data ) {
		return <Spinner />;
	}

	const stored = id === 'new' ? null : ( item.data as T );

	return (
		<>
			<p>
				<a href={ `#${ base }` }>
					{ __( '← Back to the list', 'vaqtyar' ) }
				</a>
			</p>
			<Form< T >
				// A fresh draft for each item.
				key={ stored?.id ?? 'new' }
				base={ base }
				stored={ stored }
				empty={ empty }
			>
				{ children }
			</Form>
			{ stored && after?.( stored ) }
		</>
	);
}

function Form< T extends { id: number } >( {
	base,
	stored,
	empty,
	children,
}: {
	base: string;
	stored: T | null;
	empty: Draft< T >;
	children: Props< T >[ 'children' ];
} ) {
	const [ draft, setDraft ] = useState< Draft< T > >( () => {
		if ( stored === null ) {
			return empty;
		}
		const { id: _id, ...fields } = stored;

		return fields;
	} );
	const save = useSave< T >( base );

	const submit = ( event: FormEvent ) => {
		event.preventDefault();
		save.mutate(
			{ id: stored?.id ?? null, item: draft },
			{
				onSuccess: ( saved ) => {
					if ( stored === null ) {
						window.location.hash = `#${ base }/${ saved.id }`;
					}
				},
			}
		);
	};

	return (
		<form className="vqy-admin__form" onSubmit={ submit }>
			{ children( draft, ( patch ) =>
				setDraft( ( current ) => ( { ...current, ...patch } ) )
			) }
			<div className="vqy-admin__buttons">
				<Button
					variant="primary"
					type="submit"
					isBusy={ save.isPending }
					disabled={ save.isPending }
				>
					{ __( 'Save', 'vaqtyar' ) }
				</Button>
			</div>
		</form>
	);
}
