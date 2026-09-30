/**
 * Translation pipeline, with no WP-CLI (T6.3):
 *
 *     node tools/i18n/build.mjs           write languages/vaqtyar.pot
 *     node tools/i18n/build.mjs --build   also write the .mo and the script JSON
 *     node tools/i18n/build.mjs --check   exit 1 when a locale misses a string
 *
 * PHP strings come from tools/i18n/extract.php, JS strings from the packages
 * and assets/blocks.js (only string-literal calls to __, _x, _n and _nx).
 * languages/vaqtyar-<locale>.po holds the translations, written by hand.
 * The script JSON is named <domain>-<locale>-<handle>.json, which
 * wp_set_script_translations() finds by handle, so no file hash is needed.
 */
import { execFileSync } from 'node:child_process';
import { readdirSync, readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { dirname, join, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { parse } from '@babel/parser';
import gettext from 'gettext-parser';

const root = resolve( dirname( fileURLToPath( import.meta.url ) ), '../..' );
const DOMAIN = 'vaqtyar';
const LOCALES = [ 'fa_IR' ];
/** Script handle => the source folders whose strings it needs. */
const HANDLES = {
	'vaqtyar-admin': [ 'packages/admin', 'packages/shared' ],
	'vaqtyar-widget': [ 'packages/widget', 'packages/shared' ],
	'vaqtyar-blocks': [ 'assets/blocks.js' ],
};
const CALLS = {
	__: { id: 0 },
	_x: { id: 0, ctx: 1 },
	_n: { id: 0, plural: 1 },
	_nx: { id: 0, plural: 1, ctx: 3 },
};
const HEADER_DESCRIPTION = 'Appointment booking for WordPress.';

function* walk( dir ) {
	for ( const entry of readdirSync( dir, { withFileTypes: true } ) ) {
		if ( entry.name === 'node_modules' || entry.name === 'build' ) {
			continue;
		}
		const path = join( dir, entry.name );
		if ( entry.isDirectory() ) {
			yield* walk( path );
		} else {
			yield path;
		}
	}
}

/** Every node of a Babel AST. */
function* nodes( node ) {
	if ( Array.isArray( node ) ) {
		for ( const child of node ) {
			yield* nodes( child );
		}
	} else if ( node && typeof node.type === 'string' ) {
		yield node;
		for ( const [ key, value ] of Object.entries( node ) ) {
			if ( key !== 'loc' && value && typeof value === 'object' ) {
				yield* nodes( value );
			}
		}
	}
}

const text = ( node ) =>
	node && node.type === 'StringLiteral' ? node.value : null;

function jsStrings() {
	const found = [];
	const files = [];
	for ( const folders of Object.values( HANDLES ) ) {
		for ( const folder of folders ) {
			const path = join( root, folder );
			files.push( ...( path.endsWith( '.js' ) ? [ path ] : [ ...walk( path ) ] ) );
		}
	}
	for ( const file of [ ...new Set( files ) ].sort() ) {
		if ( ! /\.(tsx?|js)$/.test( file ) || /\.test\.tsx?$/.test( file ) ) {
			continue;
		}
		const ast = parse( readFileSync( file, 'utf8' ), {
			sourceType: 'module',
			plugins: [ 'typescript', 'jsx' ],
		} );
		const ref = relative( root, file ).split( sep ).join( '/' );
		for ( const node of nodes( ast.program ) ) {
			if ( node.type !== 'CallExpression' || node.callee.type !== 'Identifier' ) {
				continue;
			}
			const spec = CALLS[ node.callee.name ];
			if ( ! spec ) {
				continue;
			}
			const id = text( node.arguments[ spec.id ] );
			if ( id === null ) {
				continue;
			}
			found.push( {
				msgid: id,
				msgctxt: spec.ctx === undefined ? null : text( node.arguments[ spec.ctx ] ),
				plural: spec.plural === undefined ? null : text( node.arguments[ spec.plural ] ),
				ref: `${ ref }:${ node.loc.start.line }`,
			} );
		}
	}
	return found;
}

function phpStrings() {
	const json = execFileSync( 'php', [ join( root, 'tools/i18n/extract.php' ) ], {
		encoding: 'utf8',
		maxBuffer: 1 << 26,
	} );
	return JSON.parse( json );
}

/** Merges duplicates: one entry per context and msgid, with every reference. */
function collect() {
	const map = new Map();
	const all = [
		{ msgid: HEADER_DESCRIPTION, msgctxt: null, plural: null, ref: 'vaqtyar.php:5' },
		...phpStrings(),
		...jsStrings(),
	];
	for ( const item of all ) {
		const key = `${ item.msgctxt ?? '' }\u0004${ item.msgid }`;
		const entry = map.get( key ) ?? { ...item, refs: [] };
		entry.plural ||= item.plural;
		entry.refs.push( item.ref );
		map.set( key, entry );
	}
	return [ ...map.values() ].sort( ( a, b ) =>
		( a.msgctxt ?? '' ).localeCompare( b.msgctxt ?? '' ) || a.msgid.localeCompare( b.msgid )
	);
}

function pot( entries ) {
	const translations = { '': {} };
	for ( const e of entries ) {
		const ctx = e.msgctxt ?? '';
		translations[ ctx ] ??= {};
		translations[ ctx ][ e.msgid ] = {
			msgctxt: e.msgctxt ?? undefined,
			msgid: e.msgid,
			msgid_plural: e.plural ?? undefined,
			msgstr: e.plural ? [ '', '' ] : [ '' ],
			comments: { reference: [ ...new Set( e.refs ) ].slice( 0, 5 ).join( '\n' ) },
		};
	}
	translations[ '' ][ '' ] = {
		msgid: '',
		msgstr: [
			'Project-Id-Version: Vaqtyar\nContent-Type: text/plain; charset=UTF-8\n'
				+ 'Content-Transfer-Encoding: 8bit\nMIME-Version: 1.0\nX-Domain: ' + DOMAIN + '\n',
		],
	};
	return gettext.po.compile( { charset: 'utf-8', headers: {}, translations } );
}

function loadPo( locale ) {
	const path = join( root, 'languages', `${ DOMAIN }-${ locale }.po` );
	return existsSync( path ) ? gettext.po.parse( readFileSync( path ), 'utf-8' ) : null;
}

function translated( po, entry ) {
	const found = po?.translations?.[ entry.msgctxt ?? '' ]?.[ entry.msgid ];
	const strings = found?.msgstr ?? [];
	return strings.length > 0 && strings.every( ( s ) => s !== '' ) ? found : null;
}

/** The printf placeholders of a string, sorted: a translation must keep exactly these. */
const placeholders = ( string ) =>
	( string.match( /%(?:\d+\$)?[sd]/g ) ?? [] ).sort().join( ' ' );

function jed( locale, po, entries, folders ) {
	const wanted = entries.filter( ( e ) =>
		e.refs.some( ( ref ) => folders.some( ( f ) => ref.startsWith( f ) ) )
		&& e.refs.some( ( ref ) => /\.(tsx?|js):/.test( ref ) )
	);
	const data = {
		'': {
			domain: DOMAIN,
			lang: locale,
			'plural-forms': po.headers?.[ 'Plural-Forms' ] ?? 'nplurals=2; plural=n != 1;',
		},
	};
	for ( const e of wanted ) {
		const found = translated( po, e );
		if ( found ) {
			data[ ( e.msgctxt ? e.msgctxt + '\u0004' : '' ) + e.msgid ] = found.msgstr;
		}
	}
	return JSON.stringify( {
		'translation-revision-date': 'YYYY-MM-DD HH:MM+0000',
		generator: 'tools/i18n/build.mjs',
		domain: 'messages',
		locale_data: { messages: data },
	} );
}

const args = new Set( process.argv.slice( 2 ) );
const entries = collect();
mkdirSync( join( root, 'languages' ), { recursive: true } );
writeFileSync( join( root, 'languages', `${ DOMAIN }.pot` ), pot( entries ) );
console.log( `${ DOMAIN }.pot: ${ entries.length } strings` );

let missing = 0;
for ( const locale of LOCALES ) {
	const po = loadPo( locale );
	if ( ! po ) {
		console.log( `${ locale }: no .po file` );
		missing += entries.length;
		continue;
	}
	const untranslated = entries.filter( ( e ) => ! translated( po, e ) );
	for ( const e of entries ) {
		const found = translated( po, e );
		if ( found && placeholders( found.msgstr[ 0 ] ) !== placeholders( e.msgid ) ) {
			console.log( `  placeholder mismatch: ${ e.msgid }` );
			missing++;
		}
	}
	missing += untranslated.length;
	console.log( `${ locale }: ${ untranslated.length } of ${ entries.length } strings untranslated` );
	for ( const e of untranslated.slice( 0, 20 ) ) {
		console.log( `  - ${ e.msgid }` );
	}
	if ( args.has( '--build' ) ) {
		writeFileSync(
			join( root, 'languages', `${ DOMAIN }-${ locale }.mo` ),
			gettext.mo.compile( po )
		);
		for ( const [ handle, folders ] of Object.entries( HANDLES ) ) {
			writeFileSync(
				join( root, 'languages', `${ DOMAIN }-${ locale }-${ handle }.json` ),
				jed( locale, po, entries, folders )
			);
		}
		console.log( `${ locale }: wrote .mo and ${ Object.keys( HANDLES ).length } script files` );
	}
}
/*
 * English needs no translations, but it needs a file: Localization loads the
 * plugin's own language, and without an English .mo WordPress would fall back
 * to the site's (Persian) one when the two differ.
 */
if ( args.has( '--build' ) ) {
	writeFileSync(
		join( root, 'languages', `${ DOMAIN }-en_US.mo` ),
		gettext.mo.compile( {
			charset: 'utf-8',
			headers: {
				Language: 'en_US',
				'Plural-Forms': 'nplurals=2; plural=n != 1;',
			},
			translations: { '': { '': { msgid: '', msgstr: [ '' ] } } },
		} )
	);
}
if ( args.has( '--check' ) && missing > 0 ) {
	process.exit( 1 );
}
