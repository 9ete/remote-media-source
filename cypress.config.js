const { defineConfig } = require( 'cypress' );
const { execSync } = require( 'child_process' );
const path = require( 'path' );
const fs = require( 'fs' );

/**
 * Remote Media Source E2E harness.
 *
 * Three Lando WordPress sites under test-envs/ play the plugin's two roles:
 *
 *   source      https://rms-source.lndo.site      role=source
 *   consumer-1  https://rms-consumer-1.lndo.site  role=consumer, mode=local
 *   consumer-2  https://rms-consumer-2.lndo.site  role=consumer, mode=block
 *
 * Specs visit absolute URLs (all three share the lndo.site superdomain, which
 * Cypress treats as one origin group) and drive WP-CLI per site through the
 * wpCli task below. Run `bash setup.sh` in each test-envs/<site>/ first —
 * see test-envs/README.md.
 */

/** Map of site keys to their Lando project directories. */
const SITES = {
	source: path.resolve( __dirname, 'test-envs', 'source' ),
	'consumer-1': path.resolve( __dirname, 'test-envs', 'consumer-1' ),
	'consumer-2': path.resolve( __dirname, 'test-envs', 'consumer-2' ),
};

/** Resolved lando binary — PATH lookup by default, override via LANDO_PATH. */
const LANDO_BIN = process.env.LANDO_PATH || 'lando';

/**
 * Resolve a site key to its Lando project dir, failing loudly on typos.
 *
 * @param {string} site Site key (source | consumer-1 | consumer-2).
 * @returns {string} Absolute path to the Lando project directory.
 */
function siteDir( site ) {
	const dir = SITES[ site ];
	if ( ! dir ) {
		throw new Error( `Unknown test site "${ site }". Expected one of: ${ Object.keys( SITES ).join( ', ' ) }` );
	}
	return dir;
}

/**
 * Run a `lando wp` command against one of the test sites and return stdout.
 *
 * @param {string} site Site key.
 * @param {string} cmd  WP-CLI subcommand (everything after "lando wp").
 * @returns {string} Trimmed stdout.
 */
function wp( site, cmd ) {
	return execSync( `"${ LANDO_BIN }" wp ${ cmd }`, {
		cwd: siteDir( site ),
		stdio: 'pipe',
		encoding: 'utf8',
	} ).trim();
}

/**
 * Host path of a test site's WP debug log (test-envs/<site>/wp-content/debug.log).
 *
 * @param {string} site Site key.
 * @returns {string} Absolute path (may not exist yet).
 */
function debugLogPath( site ) {
	return path.join( siteDir( site ), 'wp-content', 'debug.log' );
}

module.exports = defineConfig( {
	e2e: {
		// Specs visit absolute URLs; baseUrl is just the default origin.
		baseUrl: process.env.CYPRESS_BASE_URL || 'https://rms-source.lndo.site',
		specPattern: 'tests/e2e/cypress/e2e/**/*.cy.js',
		supportFile: 'tests/e2e/cypress/support/e2e.js',
		screenshotsFolder: 'tests/e2e/cypress/screenshots',
		videosFolder: 'tests/e2e/cypress/videos',
		downloadsFolder: 'tests/e2e/cypress/downloads',
		// Specs hop between lndo.site subdomains and the sites use Lando's
		// self-signed certificates — both need relaxed browser security.
		chromeWebSecurity: false,

		setupNodeEvents( on, config ) {
			on( 'task', {
				/**
				 * Run a WP-CLI command on a test site.
				 *
				 * @param {{ site: string, cmd: string }} opts
				 * @returns {string} Trimmed stdout.
				 */
				wpCli( { site, cmd } ) {
					return wp( site, cmd );
				},

				/**
				 * Current byte size of a site's debug.log (0 if absent).
				 * Captured before a spec so new entries can be diffed after.
				 *
				 * @param {{ site: string }} opts
				 * @returns {number}
				 */
				debugLogSize( { site } ) {
					const log = debugLogPath( site );
					return fs.existsSync( log ) ? fs.statSync( log ).size : 0;
				},

				/**
				 * Contents of a site's debug.log from a byte offset onward.
				 *
				 * @param {{ site: string, offset: number }} opts
				 * @returns {string}
				 */
				debugLogFrom( { site, offset } ) {
					const log = debugLogPath( site );
					if ( ! fs.existsSync( log ) ) {
						return '';
					}
					const content = fs.readFileSync( log, 'utf8' );
					return content.slice( offset );
				},
			} );

			return config;
		},
	},
	env: {
		// Lando test-site admin credentials (created by test-envs/*/setup.sh).
		// Override via CYPRESS_WP_USER / CYPRESS_WP_PASS for non-default setups.
		wp_user: 'admin',
		wp_pass: 'admin',
		site_source: 'https://rms-source.lndo.site',
		site_consumer_1: 'https://rms-consumer-1.lndo.site',
		site_consumer_2: 'https://rms-consumer-2.lndo.site',
	},
} );
