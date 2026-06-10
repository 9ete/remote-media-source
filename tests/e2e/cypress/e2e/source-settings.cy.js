/**
 * Source role — settings page and connection key lifecycle.
 */
describe( 'Source settings', () => {
	const SOURCE = Cypress.env( 'site_source' );

	beforeEach( () => {
		// Deterministic starting state: source role, no key yet. delete_option()
		// via eval is idempotent — `wp option delete` exits non-zero when the
		// option is already absent.
		cy.task( 'wpCli', { site: 'source', cmd: 'option update rms_role source' } );
		cy.task( 'wpCli', {
			site: 'source',
			cmd: `eval 'delete_option( "rms_connection_key_hash" ); delete_option( "rms_connection_key_prefix" );'`,
		} );
		cy.wpLogin( SOURCE );
	} );

	it( 'renders the settings page with the source section visible', () => {
		cy.visitRmsSettings( SOURCE );

		cy.get( '#rms_role' ).should( 'have.value', 'source' );
		cy.get( '#rms-source-settings' ).should( 'be.visible' );
		cy.get( '#rms-consumer-settings' ).should( 'not.be.visible' );
		cy.assertNoPhpErrorsOnPage();
	} );

	it( 'generates a connection key and shows it exactly once', () => {
		cy.visitRmsSettings( SOURCE );

		cy.get( '#rms-generate-key' ).click();

		// The one-time modal shows the full 64-char hex key.
		cy.get( '#rms-key-modal' ).should( 'be.visible' );
		cy.get( '#rms-key-modal-value' )
			.invoke( 'val' )
			.should( 'match', /^[a-f0-9]{64}$/ );

		cy.get( '#rms-key-modal-dismiss' ).click();
		cy.get( '#rms-key-modal' ).should( 'not.be.visible' );

		// After reload only the 8-char prefix is shown, never the full key.
		cy.visitRmsSettings( SOURCE );
		cy.get( '#rms-source-settings code' )
			.invoke( 'text' )
			.should( 'match', /^[a-f0-9]{8}•/ );
		cy.assertNoPhpErrorsOnPage();
	} );

	it( 'regenerates the key after a confirmation prompt', () => {
		cy.task( 'wpCli', {
			site: 'source',
			cmd: `eval 'echo \\RemoteMediaSource\\Source\\KeyManager::generate();'`,
		} ).then( ( oldKey ) => {
			const oldPrefix = String( oldKey ).slice( 0, 8 );

			cy.visitRmsSettings( SOURCE );
			cy.on( 'window:confirm', () => true );
			cy.get( '#rms-regenerate-key' ).click();

			cy.get( '#rms-key-modal' ).should( 'be.visible' );
			cy.get( '#rms-key-modal-value' )
				.invoke( 'val' )
				.should( 'match', /^[a-f0-9]{64}$/ )
				.and( 'not.match', new RegExp( `^${ oldPrefix }` ) );
		} );
	} );
} );
