/**
 * Custom Cypress commands for Remote Media Source E2E tests.
 *
 * All commands take a site base URL so the same suite can drive the source
 * and both consumer test sites within a single spec.
 */

/**
 * Log in to a WordPress test site's admin programmatically.
 *
 * Uses cy.request() (no browser form) wrapped in cy.session() keyed by site
 * so each site's auth cookies are established once per run and restored
 * instantly afterwards.
 *
 * @example cy.wpLogin( Cypress.env( 'site_consumer_1' ) );
 *
 * @param {string} siteUrl Base URL of the site (no trailing slash).
 */
Cypress.Commands.add( 'wpLogin', ( siteUrl ) => {
	const user = Cypress.env( 'wp_user' );
	const pass = Cypress.env( 'wp_pass' );

	cy.session(
		[ 'wp-admin', siteUrl, user ],
		() => {
			// GET the login page first — WordPress sets the test cookie here.
			cy.request( `${ siteUrl }/wp-login.php` );

			cy.request( {
				method: 'POST',
				url: `${ siteUrl }/wp-login.php`,
				form: true,
				body: {
					log: user,
					pwd: pass,
					'wp-submit': 'Log In',
					redirect_to: `${ siteUrl }/wp-admin/`,
					testcookie: '1',
				},
				followRedirect: true,
			} ).then( ( response ) => {
				expect( response.status ).to.eq( 200 );
				expect( response.body ).to.not.include( 'id="loginform"' );
			} );
		},
		{
			cacheAcrossSpecs: true,
			validate() {
				cy.request( {
					url: `${ siteUrl }/wp-admin/`,
					failOnStatusCode: false,
				} ).then( ( response ) => {
					expect( response.status ).to.eq( 200 );
					expect( response.body ).to.not.include( 'id="loginform"' );
				} );
			},
		}
	);
} );

/**
 * Visit a site's Remote Media Source settings page (assumes wpLogin ran).
 *
 * @param {string} siteUrl Base URL of the site (no trailing slash).
 */
Cypress.Commands.add( 'visitRmsSettings', ( siteUrl ) => {
	cy.visit( `${ siteUrl }/wp-admin/options-general.php?page=remote-media-source` );
	cy.get( 'h1' ).should( 'contain.text', 'Remote Media Source' );
} );

/**
 * Assert the current page contains no rendered PHP error output.
 * Catches notices/warnings/fatals that WP_DEBUG_DISPLAY would surface
 * and the raw strings that leak even when display is off but output
 * happens before headers.
 */
Cypress.Commands.add( 'assertNoPhpErrorsOnPage', () => {
	cy.get( 'body' ).then( ( $body ) => {
		const text = $body.text();
		expect( text ).to.not.match( /(Fatal error|Parse error|Uncaught (Error|Exception)|Warning: |Notice: |Deprecated: )/ );
	} );
} );
