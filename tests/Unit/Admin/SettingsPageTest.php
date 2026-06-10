<?php
/**
 * SettingsPage unit tests — save handler, AJAX endpoints, render gating.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use RemoteMediaSource\Admin\SettingsPage;
use RemoteMediaSource\Source\KeyManager;

#[\PHPUnit\Framework\Attributes\CoversClass( SettingsPage::class )]
class SettingsPageTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['rms_test_options']       = array();
		$GLOBALS['rms_test_hooks']         = array();
		$GLOBALS['rms_test_nonce_checks']  = array();
		$GLOBALS['rms_test_http_requests'] = array();
		$GLOBALS['rms_test_user_can']      = true;
		unset( $GLOBALS['rms_test_http_response'] );
		unset( $GLOBALS['rms_test_filter_overrides'] );
		unset( $GLOBALS['rms_test_host_is_external'] );
		$_POST = array();
	}

	protected function tearDown(): void {
		$_POST = array();
	}

	/**
	 * Run handle_save() and capture the redirect that ends it.
	 */
	private function run_save(): string {
		try {
			SettingsPage::handle_save();
			$this->fail( 'handle_save() must end in a redirect.' );
		} catch ( \RMS_Test_Redirect $redirect ) {
			return $redirect->location;
		}
	}

	/**
	 * Run an AJAX handler and capture the JSON response it sends.
	 *
	 * @param callable $handler AJAX handler to invoke.
	 */
	private function run_ajax( callable $handler ): \RMS_Test_Json_Response {
		try {
			$handler();
			$this->fail( 'AJAX handler must send a JSON response.' );
		} catch ( \RMS_Test_Json_Response $response ) {
			return $response;
		}
	}

	// ── register() ───────────────────────────────────────────────────────────

	public function test_register_wires_save_and_ajax_hooks(): void {
		SettingsPage::register();
		$hooks = array_column( $GLOBALS['rms_test_hooks'], 'hook' );

		$this->assertContains( 'admin_menu', $hooks );
		$this->assertContains( 'admin_post_rms_save_settings', $hooks );
		$this->assertContains( 'wp_ajax_rms_generate_key', $hooks );
		$this->assertContains( 'wp_ajax_rms_test_connection', $hooks );
	}

	// ── handle_save() ────────────────────────────────────────────────────────

	public function test_save_verifies_nonce(): void {
		$_POST['rms_role'] = '';
		$this->run_save();
		$this->assertContains( 'rms_save_settings', $GLOBALS['rms_test_nonce_checks'] );
	}

	public function test_save_rejects_unauthorized_user(): void {
		$GLOBALS['rms_test_user_can'] = false;
		$_POST['rms_role']            = 'source';

		$this->expectException( \RMS_Test_Wp_Die::class );
		SettingsPage::handle_save();
	}

	public function test_save_stores_valid_role(): void {
		$_POST['rms_role'] = 'source';
		$this->run_save();
		$this->assertSame( 'source', get_option( 'rms_role' ) );
	}

	public function test_save_falls_back_to_disabled_for_invalid_role(): void {
		$_POST['rms_role'] = 'superuser';
		$this->run_save();
		$this->assertSame( '', get_option( 'rms_role' ) );
	}

	public function test_save_redirects_back_to_settings_page(): void {
		$_POST['rms_role'] = '';
		$location          = $this->run_save();
		$this->assertStringContainsString( 'page=remote-media-source&saved=1', $location );
	}

	public function test_switching_away_from_source_clears_key_options(): void {
		update_option( 'rms_role', 'source' );
		KeyManager::generate();

		$_POST['rms_role'] = '';
		$this->run_save();

		$this->assertFalse( get_option( 'rms_connection_key_hash' ) );
		$this->assertFalse( get_option( 'rms_connection_key_prefix' ) );
	}

	public function test_switching_away_from_consumer_clears_consumer_options(): void {
		update_option( 'rms_role', 'consumer' );
		update_option( 'rms_remote_url', 'https://prod.example.com' );
		update_option( 'rms_remote_key', 'secret' );
		update_option( 'rms_upload_mode', 'block' );
		update_option( 'rms_last_connection', array( 'success' => true ) );

		$_POST['rms_role'] = 'source';
		$this->run_save();

		$this->assertFalse( get_option( 'rms_remote_url' ) );
		$this->assertFalse( get_option( 'rms_remote_key' ) );
		$this->assertFalse( get_option( 'rms_upload_mode' ) );
		$this->assertFalse( get_option( 'rms_last_connection' ) );
	}

	public function test_save_consumer_stores_url_key_and_mode(): void {
		$_POST['rms_role']        = 'consumer';
		$_POST['rms_remote_url']  = 'https://prod.example.com';
		$_POST['rms_remote_key']  = str_repeat( 'a', 64 );
		$_POST['rms_upload_mode'] = 'block';
		$this->run_save();

		$this->assertSame( 'https://prod.example.com', get_option( 'rms_remote_url' ) );
		$this->assertSame( str_repeat( 'a', 64 ), get_option( 'rms_remote_key' ) );
		$this->assertSame( 'block', get_option( 'rms_upload_mode' ) );
	}

	public function test_save_keeps_existing_key_when_field_left_blank(): void {
		update_option( 'rms_role', 'consumer' );
		update_option( 'rms_remote_key', 'existing-secret' );

		$_POST['rms_role']       = 'consumer';
		$_POST['rms_remote_url'] = 'https://prod.example.com';
		$_POST['rms_remote_key'] = '';
		$this->run_save();

		$this->assertSame( 'existing-secret', get_option( 'rms_remote_key' ) );
	}

	public function test_save_invalid_upload_mode_falls_back_to_local(): void {
		$_POST['rms_role']        = 'consumer';
		$_POST['rms_remote_url']  = 'https://prod.example.com';
		$_POST['rms_upload_mode'] = 'proxy';
		$this->run_save();

		$this->assertSame( 'local', get_option( 'rms_upload_mode' ) );
	}

	public function test_changing_remote_url_resets_verification(): void {
		update_option( 'rms_role', 'consumer' );
		update_option( 'rms_remote_url', 'https://old.example.com' );
		update_option( 'rms_last_connection', array( 'success' => true ) );

		$_POST['rms_role']       = 'consumer';
		$_POST['rms_remote_url'] = 'https://new.example.com';
		$this->run_save();

		$this->assertFalse( get_option( 'rms_last_connection' ) );
	}

	public function test_keeping_same_remote_url_preserves_verification(): void {
		update_option( 'rms_role', 'consumer' );
		update_option( 'rms_remote_url', 'https://prod.example.com' );
		update_option( 'rms_last_connection', array( 'success' => true ) );

		$_POST['rms_role']       = 'consumer';
		$_POST['rms_remote_url'] = 'https://prod.example.com';
		$this->run_save();

		$this->assertNotFalse( get_option( 'rms_last_connection' ) );
	}

	// ── remote URL validation (SSRF surface) ─────────────────────────────────

	public function test_save_rejects_http_url_by_default(): void {
		$_POST['rms_role']       = 'consumer';
		$_POST['rms_remote_url'] = 'http://prod.example.com';
		$this->run_save();

		$this->assertSame( '', get_option( 'rms_remote_url' ) );
	}

	public function test_save_allows_http_when_scheme_filter_permits(): void {
		$GLOBALS['rms_test_filter_overrides']['remote_media_source_allowed_url_schemes'] = array( 'http', 'https' );

		$_POST['rms_role']       = 'consumer';
		$_POST['rms_remote_url'] = 'http://dev-source.example.test';
		$this->run_save();

		$this->assertSame( 'http://dev-source.example.test', get_option( 'rms_remote_url' ) );
	}

	public function test_save_rejects_loopback_ip_url(): void {
		$_POST['rms_role']       = 'consumer';
		$_POST['rms_remote_url'] = 'https://127.0.0.1';
		$this->run_save();

		$this->assertSame( '', get_option( 'rms_remote_url' ) );
	}

	public function test_save_strips_trailing_slash_from_remote_url(): void {
		$_POST['rms_role']       = 'consumer';
		$_POST['rms_remote_url'] = 'https://prod.example.com/';
		$this->run_save();

		$this->assertSame( 'https://prod.example.com', get_option( 'rms_remote_url' ) );
	}

	public function test_save_ignores_malformed_connection_key(): void {
		update_option( 'rms_role', 'consumer' );
		update_option( 'rms_remote_key', str_repeat( 'a', 64 ) );

		$_POST['rms_role']       = 'consumer';
		$_POST['rms_remote_url'] = 'https://prod.example.com';
		$_POST['rms_remote_key'] = 'not-a-key';
		$this->run_save();

		$this->assertSame( str_repeat( 'a', 64 ), get_option( 'rms_remote_key' ) );
	}

	public function test_connection_refuses_invalid_stored_url_without_any_request(): void {
		update_option( 'rms_remote_url', 'http://internal-service.local' );

		$response = $this->run_ajax( array( SettingsPage::class, 'ajax_test_connection' ) );

		$this->assertFalse( $response->success );
		$this->assertCount( 0, $GLOBALS['rms_test_http_requests'] );
	}

	public function test_connection_request_refuses_redirects_and_unsafe_urls(): void {
		update_option( 'rms_remote_url', 'https://prod.example.com' );
		update_option( 'rms_remote_key', str_repeat( 'a', 64 ) );
		$GLOBALS['rms_test_http_response'] = array( 'body' => '{"verified":true,"site_name":"Prod"}' );

		$this->run_ajax( array( SettingsPage::class, 'ajax_test_connection' ) );

		$args = $GLOBALS['rms_test_http_requests'][0]['args'];
		$this->assertSame( 0, $args['redirection'] );
		$this->assertTrue( $args['reject_unsafe_urls'] );
	}

	public function test_connection_treats_redirect_response_as_failure(): void {
		update_option( 'rms_remote_url', 'https://prod.example.com' );
		$GLOBALS['rms_test_http_response'] = array(
			'body'     => '',
			'response' => array( 'code' => 301 ),
		);

		$response = $this->run_ajax( array( SettingsPage::class, 'ajax_test_connection' ) );

		$this->assertFalse( $response->success );
		$this->assertStringContainsString( 'redirected', $response->payload['message'] );
	}

	// ── ajax_generate_key() ──────────────────────────────────────────────────

	public function test_generate_key_rejects_unauthorized_user(): void {
		$GLOBALS['rms_test_user_can'] = false;

		$response = $this->run_ajax( array( SettingsPage::class, 'ajax_generate_key' ) );
		$this->assertFalse( $response->success );
	}

	public function test_generate_key_rejects_non_source_site(): void {
		update_option( 'rms_role', 'consumer' );

		$response = $this->run_ajax( array( SettingsPage::class, 'ajax_generate_key' ) );
		$this->assertFalse( $response->success );
	}

	public function test_generate_key_returns_key_and_prefix(): void {
		update_option( 'rms_role', 'source' );

		$response = $this->run_ajax( array( SettingsPage::class, 'ajax_generate_key' ) );

		$this->assertTrue( $response->success );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $response->payload['key'] );
		$this->assertSame( substr( $response->payload['key'], 0, 8 ), $response->payload['prefix'] );
	}

	public function test_generated_key_verifies_against_stored_hash(): void {
		update_option( 'rms_role', 'source' );

		$response = $this->run_ajax( array( SettingsPage::class, 'ajax_generate_key' ) );
		$this->assertTrue( KeyManager::verify( $response->payload['key'] ) );
	}

	// ── ajax_test_connection() ───────────────────────────────────────────────

	public function test_connection_rejects_unauthorized_user(): void {
		$GLOBALS['rms_test_user_can'] = false;

		$response = $this->run_ajax( array( SettingsPage::class, 'ajax_test_connection' ) );
		$this->assertFalse( $response->success );
	}

	public function test_connection_fails_without_remote_url(): void {
		$response = $this->run_ajax( array( SettingsPage::class, 'ajax_test_connection' ) );

		$this->assertFalse( $response->success );
		$this->assertStringContainsString( 'not configured', $response->payload['message'] );
	}

	public function test_connection_sends_key_in_auth_header_to_verify_endpoint(): void {
		update_option( 'rms_remote_url', 'https://prod.example.com' );
		update_option( 'rms_remote_key', 'secret-key' );
		$GLOBALS['rms_test_http_response'] = array( 'body' => '{"verified":true,"site_name":"Prod"}' );

		$this->run_ajax( array( SettingsPage::class, 'ajax_test_connection' ) );

		$request = $GLOBALS['rms_test_http_requests'][0];
		$this->assertSame( 'https://prod.example.com/wp-json/rms/v1/verify', $request['url'] );
		$this->assertSame( 'RMS secret-key', $request['args']['headers']['Authorization'] );
	}

	public function test_connection_reports_transport_errors(): void {
		update_option( 'rms_remote_url', 'https://unreachable.example.com' );
		$GLOBALS['rms_test_http_response'] = new \WP_Error( 'http_request_failed', 'Could not resolve host' );

		$response = $this->run_ajax( array( SettingsPage::class, 'ajax_test_connection' ) );

		$this->assertFalse( $response->success );
		$this->assertStringContainsString( 'Could not resolve host', $response->payload['message'] );
	}

	public function test_connection_fails_when_remote_does_not_verify(): void {
		update_option( 'rms_remote_url', 'https://prod.example.com' );
		$GLOBALS['rms_test_http_response'] = array( 'body' => '{"code":"rms_unauthorized"}' );

		$response = $this->run_ajax( array( SettingsPage::class, 'ajax_test_connection' ) );
		$this->assertFalse( $response->success );
	}

	public function test_connection_failure_does_not_record_verification(): void {
		update_option( 'rms_remote_url', 'https://prod.example.com' );
		$GLOBALS['rms_test_http_response'] = array( 'body' => '{"code":"rms_unauthorized"}' );

		$this->run_ajax( array( SettingsPage::class, 'ajax_test_connection' ) );
		$this->assertFalse( get_option( 'rms_last_connection' ) );
	}

	public function test_successful_connection_records_verification(): void {
		update_option( 'rms_remote_url', 'https://prod.example.com' );
		update_option( 'rms_remote_key', 'secret-key' );
		$GLOBALS['rms_test_http_response'] = array( 'body' => '{"verified":true,"site_name":"Prod"}' );

		$response = $this->run_ajax( array( SettingsPage::class, 'ajax_test_connection' ) );

		$this->assertTrue( $response->success );
		$last = get_option( 'rms_last_connection' );
		$this->assertTrue( $last['success'] );
		$this->assertArrayHasKey( 'timestamp', $last );
		$this->assertStringContainsString( 'Prod', $last['message'] );
	}

	// ── render() ─────────────────────────────────────────────────────────────

	/**
	 * Render the settings page for a given stored role and return the HTML.
	 */
	private function render_for_role( string $role ): string {
		update_option( 'rms_role', $role );
		ob_start();
		SettingsPage::render();
		return (string) ob_get_clean();
	}

	public function test_render_outputs_nothing_for_unauthorized_user(): void {
		$GLOBALS['rms_test_user_can'] = false;
		$this->assertSame( '', $this->render_for_role( 'source' ) );
	}

	public function test_render_shows_source_section_for_source_role(): void {
		$html = $this->render_for_role( 'source' );

		$this->assertStringContainsString( 'id="rms-source-settings"', $html );
		$this->assertStringNotContainsString( 'id="rms-source-settings" style="display:none"', $html );
		$this->assertStringContainsString( 'id="rms-consumer-settings" style="display:none"', $html );
	}

	public function test_render_shows_consumer_section_for_consumer_role(): void {
		$html = $this->render_for_role( 'consumer' );

		$this->assertStringContainsString( 'id="rms-consumer-settings"', $html );
		$this->assertStringNotContainsString( 'id="rms-consumer-settings" style="display:none"', $html );
		$this->assertStringContainsString( 'id="rms-source-settings" style="display:none"', $html );
	}

	public function test_render_never_outputs_stored_remote_key(): void {
		update_option( 'rms_remote_key', 'super-secret-value' );
		$html = $this->render_for_role( 'consumer' );

		$this->assertStringNotContainsString( 'super-secret-value', $html );
	}
}
