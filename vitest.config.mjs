import { defineConfig } from 'vitest/config';

/**
 * One project per package, since the widget compiles JSX for Preact and the
 * admin for React. Tests that need a DOM ask for jsdom in their first line.
 */
export default defineConfig( {
	test: {
		projects: [
			{ extends: true, test: { name: 'shared', include: [ 'packages/shared/src/**/*.test.ts' ] } },
			{
				extends: true,
				test: { name: 'admin', include: [ 'packages/admin/src/**/*.test.{ts,tsx}' ] },
				oxc: { jsx: { runtime: 'automatic', importSource: 'react' } },
			},
			{
				extends: true,
				test: { name: 'widget', include: [ 'packages/widget/src/**/*.test.{ts,tsx}' ] },
				oxc: { jsx: { runtime: 'automatic', importSource: 'preact' } },
			},
		],
	},
} );
