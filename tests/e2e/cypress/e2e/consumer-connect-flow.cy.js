/**
 * Primary user flow — connect a consumer to the source and verify the
 * upload URL rewrite activates.
 *
 * source:     generate a connection key (WP-CLI, same code path as the UI).
 * consumer-1: enter URL + key in the settings UI, save, run Test Connection,
 *             then confirm wp_upload_dir() URLs point at the source.
 */
describe( 'Consumer connect flow', () => {
	const SOURCE = Cypress.env( 'site_source' );
	const CONSUMER = Cypress.env( 'site_consumer_1' );

	before( () => {
		// Source must be in source role with a fresh key.
		cy.task( 'wpCli', { site: 'source', cmd: 'option update rms_role source' } );

		// Consumer starts unverified so the flow proves activation end-to-end.
		cy.task( 'wpCli', { site: 'consumer-1', cmd: 'option update rms_role consumer' } );
		cy.task( 'wpCli', { site: 'consumer-1', cmd: `eval 'delete_option( "rms_last_connection" );'` } );
	} );

	it( 'connects consumer-1 to the source and activates URL rewriting', () => {
		// Generate the key on the source (same KeyManager call the UI uses).
		cy.task( 'wpCli', {
			site: 'source',
			cmd: `eval 'echo \\RemoteMediaSource\\Source\\KeyManager::generate();'`,
		} ).then( ( key ) => {
			expect( String( key ) ).to.match( /^[a-f0-9]{64}$/ );

			cy.wpLogin( CONSUMER );
			cy.visitRmsSettings( CONSUMER );

			// Pre-verification warning is shown.
			cy.get( '#rms-consumer-settings .notice-warning' ).should( 'contain.text', 'not yet verified' );

			// Fill in the connection details and save.
			cy.get( '#rms_remote_url' ).clear();
			cy.get( '#rms_remote_url' ).type( SOURCE );
			cy.get( '#rms_remote_key' ).clear();
			cy.get( '#rms_remote_key' ).type( String( key ), { log: false } );
			cy.get( 'form input[type="submit"]' ).click();

			cy.get( '.notice-success' ).should( 'contain.text', 'Settings saved.' );

			// Run the connection test against the live source container.
			cy.get( '#rms-test-connection' ).click();
			cy.get( '#rms-test-result', { timeout: 20000 } ).should( 'contain.text', 'Connected to' );

			// Reload: the consumer now reports active.
			cy.visitRmsSettings( CONSUMER );
			cy.get( '#rms-consumer-settings .notice-success' ).should( 'contain.text', 'Consumer active.' );
			cy.assertNoPhpErrorsOnPage();

			// The upload_dir filter now rewrites URLs to the source...
			cy.task( 'wpCli', {
				site: 'consumer-1',
				cmd: `eval '$d = wp_upload_dir(); echo $d["baseurl"] . "\\n" . $d["url"];'`,
			} ).then( ( output ) => {
				const [ baseurl, url ] = String( output ).split( '\n' );
				expect( baseurl ).to.eq( `${ SOURCE }/wp-content/uploads` );
				expect( url ).to.match( new RegExp( `^${ SOURCE }/wp-content/uploads/\\d{4}/\\d{2}$` ) );
			} );

			// ...while local paths stay local so uploads still land on disk.
			cy.task( 'wpCli', {
				site: 'consumer-1',
				cmd: `eval '$d = wp_upload_dir(); echo $d["basedir"];'`,
			} ).then( ( basedir ) => {
				expect( String( basedir ) ).to.include( '/app/wp-content/uploads' );
			} );
		} );
	} );

	it( 'rejects a bad connection key', () => {
		cy.wpLogin( CONSUMER );
		cy.visitRmsSettings( CONSUMER );

		cy.get( '#rms_remote_key' ).clear();
		cy.get( '#rms_remote_key' ).type( 'f'.repeat( 64 ), { log: false } );
		cy.get( 'form input[type="submit"]' ).click();
		cy.get( '.notice-success' ).should( 'contain.text', 'Settings saved.' );

		cy.get( '#rms-test-connection' ).click();
		cy.get( '#rms-test-result', { timeout: 20000 } ).should( 'contain.text', 'Check your connection key' );
	} );
} );
