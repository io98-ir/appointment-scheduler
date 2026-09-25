/**
 * The WordPress defaults of wp-scripts, plus: tests import their tools
 * (vitest, react-dom) from the workspace root's devDependencies, not from the
 * package they test.
 */
const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...defaultConfig,
	{
		files: [ 'packages/*/src/**/*.test.{ts,tsx}' ],
		rules: {
			'import/no-extraneous-dependencies': [
				'error',
				{ devDependencies: true, packageDir: [ __dirname ] },
			],
		},
	},
];
