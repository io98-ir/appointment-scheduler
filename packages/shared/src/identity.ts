/**
 * The plugin's technical identity (ADR-000), read from the same identity.json
 * the PHP side and tools/rename.php use, so a rename needs no JS edit.
 */
import identity from '../../../identity.json';

/** Prefix of the DOM ids and data attributes the PHP side renders. */
export const SLUG: string = identity.slug;
