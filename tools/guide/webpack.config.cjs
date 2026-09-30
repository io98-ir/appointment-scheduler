/**
 * Builds the admin app and the widget for the user guide's screenshots:
 * the real sources, bundled with their own React (no WordPress around), plus
 * the translation data, so the pages look the way a site owner sees them.
 * Not part of the release: `pnpm guide:build`, then `pnpm guide:shoot`.
 */
const path = require( 'node:path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const root = path.resolve( __dirname, '../..' );

module.exports = {
	...defaultConfig,
	mode: 'development',
	devtool: false,
	entry: {
		admin: path.join( __dirname, 'entry-admin.ts' ),
		widget: path.join( __dirname, 'entry-widget.ts' ),
		components: path.join( __dirname, 'entry-components.ts' ),
		'components-rtl': path.join( __dirname, 'entry-components-rtl.ts' ),
	},
	output: {
		path: path.join( root, 'tools/guide/out' ),
		filename: '[name].js',
	},
	plugins: defaultConfig.plugins.filter(
		( plugin ) =>
			! /DependencyExtraction|Copy|Clean|Fix|Eslint|Stylelint|Phpcs/i.test(
				plugin.constructor.name
			)
	),
	// React is bundled here, not taken from a WordPress page.
	externals: {},
};
