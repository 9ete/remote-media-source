/**
 * Consumer block mode — uploads are refused and the admin is told why.
 *
 * consumer-2 is provisioned in block mode by its setup script; the state is
 * re-asserted here so the spec stays deterministic when run standalone.
 */
describe( 'Consumer upload block mode', () => {
	const CONSUMER = Cypress.env( 'site_consumer_2' );

	before( () => {
		cy.task( 'wpCli', { site: 'consumer-2', cmd: 'option update rms_role consumer' } );
		cy.task( 'wpCli', { site: 'consumer-2', cmd: 'option update rms_upload_mode block' } );
	} );

	beforeEach( () => {
		cy.wpLogin( CONSUMER );
	} );

	it( 'shows the blocked-uploads notice on the media upload screen', () => {
		cy.visit( `${ CONSUMER }/wp-admin/media-new.php` );
		cy.get( '.notice-warning' ).should(
			'contain.text',
			'File uploads are blocked on this environment by Remote Media Source.'
		);
		cy.assertNoPhpErrorsOnPage();
	} );

	it( 'empties the allowed MIME list so no file type passes validation', () => {
		cy.task( 'wpCli', {
			site: 'consumer-2',
			cmd: `eval 'echo count( get_allowed_mime_types() );'`,
		} ).then( ( count ) => {
			expect( String( count ) ).to.eq( '0' );
		} );
	} );

	it( 'rejects uploads at the prefilter with an explanatory error', () => {
		cy.task( 'wpCli', {
			site: 'consumer-2',
			cmd: `eval '$r = apply_filters( "wp_handle_upload_prefilter", array( "name" => "x.jpg" ) ); echo $r["error"];'`,
		} ).then( ( error ) => {
			expect( String( error ) ).to.include( 'Uploads are blocked on this environment' );
		} );
	} );

	it( 'leaves uploads alone in local mode', () => {
		cy.task( 'wpCli', { site: 'consumer-2', cmd: 'option update rms_upload_mode local' } );

		cy.task( 'wpCli', {
			site: 'consumer-2',
			cmd: `eval 'echo count( get_allowed_mime_types() );'`,
		} ).then( ( count ) => {
			expect( Number( count ) ).to.be.greaterThan( 0 );

			// Restore block mode for any later spec or manual poking.
			cy.task( 'wpCli', { site: 'consumer-2', cmd: 'option update rms_upload_mode block' } );
		} );
	} );
} );
