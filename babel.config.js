/**
 * The WordPress preset for everything, and JSX for Preact in the widget: the
 * front end never loads React (ADR-007). Plugins listed here run before the
 * preset's own JSX transform, so widget files never reach it with JSX left.
 *
 * @param {import('@babel/core').ConfigAPI} api
 */
module.exports = ( api ) => {
	api.cache( true );

	return {
		presets: [ '@wordpress/babel-preset-default' ],
		overrides: [
			{
				test: './packages/widget',
				plugins: [
					[
						'@babel/plugin-transform-react-jsx',
						{ runtime: 'automatic', importSource: 'preact' },
					],
				],
			},
		],
	};
};
