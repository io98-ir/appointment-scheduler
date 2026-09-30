/**
 * The WordPress rules, plus principles §5: logical properties only (so RTL
 * needs no separate rules) and no hard-coded colors (colors come from the
 * --vqy-* tokens). The one exception is the admin's tokens file, where the
 * palette itself is defined (ADR-020).
 */
module.exports = {
	extends: [ '@wordpress/stylelint-config' ],
	plugins: [ 'stylelint-use-logical' ],
	rules: {
		'csstools/use-logical': 'always',
		'color-no-hex': true,
		'color-named': 'never',
		'function-disallowed-list': [
			'rgb',
			'rgba',
			'hsl',
			'hsla',
			'hwb',
			'lab',
			'lch',
			'oklab',
			'oklch',
		],
		// BEM: block, block__element, block--modifier.
		'selector-class-pattern': [
			'^[a-z0-9]+(-[a-z0-9]+)*(__[a-z0-9]+(-[a-z0-9]+)*)?(--[a-z0-9]+(-[a-z0-9]+)*)?$',
			{ message: 'Use BEM in kebab case: block__element--modifier' },
		],
	},
	overrides: [
		{
			files: [ '**/tokens.css' ],
			rules: { 'color-no-hex': null, 'color-named': null },
		},
	],
};
