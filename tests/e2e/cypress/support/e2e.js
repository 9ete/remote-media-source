/**
 * Cypress support file — loaded before every E2E spec.
 *
 * Guards the whole suite against PHP-level regressions: the byte offset of
 * each test site's debug.log is captured before a spec runs, and after the
 * spec every line appended during the run is checked for PHP fatals,
 * warnings, notices, and deprecations.
 */
import './commands';

const SITES = [ 'source', 'consumer-1', 'consumer-2' ];
const PHP_ERROR_PATTERN = /PHP (Fatal error|Parse error|Warning|Notice|Deprecated)/;

// Only lines implicating this plugin fail the suite — the shared test envs
// carry unrelated plugins whose own notices must not gate RMS releases.
// Errors raised from our code always reference the plugin path or the
// remote-media-source text domain.
const RMS_PATTERN = /remote-media-source/;

const logOffsets = {};

before( () => {
	SITES.forEach( ( site ) => {
		cy.task( 'debugLogSize', { site } ).then( ( size ) => {
			logOffsets[ site ] = size;
		} );
	} );
} );

after( () => {
	SITES.forEach( ( site ) => {
		cy.task( 'debugLogFrom', { site, offset: logOffsets[ site ] || 0 } ).then( ( appended ) => {
			const offending = String( appended )
				.split( '\n' )
				.filter( ( line ) => PHP_ERROR_PATTERN.test( line ) && RMS_PATTERN.test( line ) );

			expect(
				offending,
				`PHP errors implicating remote-media-source appended to ${ site } debug.log during this spec:\n${ offending.join( '\n' ) }`
			).to.have.length( 0 );
		} );
	} );
} );
