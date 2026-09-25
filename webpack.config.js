/**
 * Two entry points instead of the default src/ scan: build/admin.js for
 * wp-admin (React from WordPress core, as an external) and build/widget.js
 * for the front end (Preact, bundled). Each gets its .asset.php with the
 * script dependencies and a content hash for the version.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		admin: './packages/admin/src/index.tsx',
		widget: './packages/widget/src/index.tsx',
	},
};
