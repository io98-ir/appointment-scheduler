/**
 * E2E on the wp-env site CI starts (docs/04-engineering/02-dev-environment.md):
 * @wordpress/scripts' config, which logs in as admin once in global setup,
 * pointed at the development site on 8888 and at these specs.
 */
const path = require( 'path' );

process.env.WP_BASE_URL ??= 'http://localhost:8888';
process.env.WP_ARTIFACTS_PATH ??= path.join( __dirname, '../../artifacts' );

const config = require( '@wordpress/scripts/config/playwright.config.js' );

module.exports = {
	...config,
	testDir: path.join( __dirname, 'specs' ),
	// CI starts wp-env itself, with the build and vendor/ in place.
	webServer: undefined,
};
