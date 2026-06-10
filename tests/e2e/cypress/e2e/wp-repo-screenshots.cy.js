/**
 * E2E screenshot capture for the WordPress.org plugin page.
 *
 * Captures the plugin's key admin screens so the set shipped under
 * `== Screenshots ==` in readme.txt is reproducible and stays in sync with
 * the current UI. Output lands in
 * `tests/e2e/cypress/screenshots/wp-repo-screenshots.cy.js/` and is promoted
 * to `screenshot-{N}.png` at plugin root by `bin/sync-wp-repo-screenshots.sh`.
 *
 * Each `cy.screenshot()` call is numbered with a two-digit prefix so the file
 * sort order matches the numbered entries in readme.txt.
 *
 * The spec provisions its own state through WP-CLI tasks, so it can run
 * standalone:
 *   npm run wp-repo-screenshots
 */
describe( 'Remote Media Source — WP.org screenshots', () => {
	const SOURCE = Cypress.env( 'site_source' );
	const CONSUMER = Cypress.env( 'site_consumer_1' );
	const BLOCKED = Cypress.env( 'site_consumer_2' );

	before( () => {
		// Source: role set with a generated key so the prefix renders.
		cy.task( 'wpCli', { site: 'source', cmd: 'option update rms_role source' } );
		cy.task( 'wpCli', {
			site: 'source',
			cmd: `eval 'echo \\RemoteMediaSource\\Source\\KeyManager::generate();'`,
		} ).then( ( key ) => {
			// Consumer-1: fully connected against the fresh key.
			cy.task( 'wpCli', { site: 'consumer-1', cmd: 'option update rms_role consumer' } );
			cy.task( 'wpCli', { site: 'consumer-1', cmd: `option update rms_remote_url ${ SOURCE }` } );
			cy.task( 'wpCli', { site: 'consumer-1', cmd: `option update rms_remote_key ${ String( key ) }` } );
			cy.task( 'wpCli', {
				site: 'consumer-1',
				cmd: `eval 'update_option( "rms_last_connection", array( "timestamp" => time(), "success" => true, "message" => "Connected to RMS Source (Production)" ) );'`,
			} );
		} );

		// Consumer-2: block mode for the uploads-blocked capture.
		cy.task( 'wpCli', { site: 'consumer-2', cmd: 'option update rms_role consumer' } );
		cy.task( 'wpCli', { site: 'consumer-2', cmd: 'option update rms_upload_mode block' } );
	} );

	beforeEach( () => {
		// WP.org renders screenshots at ~1544px wide; capture at that width so
		// the promoted PNGs display without upscale blur.
		cy.viewport( 1544, 1000 );
	} );

	it( 'captures the source settings with a generated key (screenshot-1)', () => {
		cy.wpLogin( SOURCE );
		cy.visitRmsSettings( SOURCE );
		cy.get( '#rms-source-settings' ).should( 'be.visible' );
		cy.get( '#rms-source-settings code' ).should( 'exist' );
		cy.screenshot( '01-source-settings', { capture: 'fullPage', overwrite: true } );
	} );

	it( 'captures a connected consumer (screenshot-2)', () => {
		cy.wpLogin( CONSUMER );
		cy.visitRmsSettings( CONSUMER );
		cy.get( '#rms-consumer-settings .notice-success' ).should( 'contain.text', 'Consumer active.' );
		cy.screenshot( '02-consumer-connected', { capture: 'fullPage', overwrite: true } );
	} );

	it( 'captures the blocked-uploads notice (screenshot-3)', () => {
		cy.wpLogin( BLOCKED );
		cy.visit( `${ BLOCKED }/wp-admin/media-new.php` );
		cy.get( '.notice-warning' ).should( 'contain.text', 'File uploads are blocked' );
		cy.screenshot( '03-uploads-blocked', { capture: 'fullPage', overwrite: true } );
	} );

	it( 'captures the plugins list Settings action link (screenshot-4)', () => {
		cy.wpLogin( SOURCE );
		cy.visit( `${ SOURCE }/wp-admin/plugins.php` );
		cy.get( 'tr[data-slug="remote-media-source"] .row-actions, [data-plugin="remote-media-source/remote-media-source.php"] .row-actions' )
			.find( 'a' )
			.contains( 'Settings' )
			.should( 'be.visible' );
		cy.screenshot( '04-plugins-settings-link', { capture: 'fullPage', overwrite: true } );
	} );
} );
