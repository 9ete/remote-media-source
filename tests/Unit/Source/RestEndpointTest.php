<?php
/**
 * RestEndpoint unit tests.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Tests\Unit\Source;

use PHPUnit\Framework\TestCase;
use RemoteMediaSource\Source\RestEndpoint;
use RemoteMediaSource\Source\KeyManager;

#[\PHPUnit\Framework\Attributes\CoversClass( RestEndpoint::class )]
class RestEndpointTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['rms_test_options'] = array();
	}

	public function test_check_permission_returns_wp_error_when_no_auth_header(): void {
		KeyManager::generate();
		$request = new \WP_REST_Request( 'GET', '/rms/v1/verify' );

		$result = RestEndpoint::check_permission( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rms_unauthorized', $result->get_error_code() );
	}

	public function test_check_permission_returns_wp_error_for_wrong_scheme(): void {
		$key     = KeyManager::generate();
		$request = new \WP_REST_Request( 'GET', '/rms/v1/verify' );
		$request->set_header( 'authorization', 'Bearer ' . $key );

		$result = RestEndpoint::check_permission( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rms_unauthorized', $result->get_error_code() );
	}

	public function test_check_permission_returns_wp_error_for_invalid_key(): void {
		KeyManager::generate();
		$request = new \WP_REST_Request( 'GET', '/rms/v1/verify' );
		$request->set_header( 'authorization', 'RMS ' . str_repeat( 'b', 64 ) );

		$result = RestEndpoint::check_permission( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rms_unauthorized', $result->get_error_code() );
	}

	public function test_check_permission_returns_true_for_valid_key(): void {
		$key     = KeyManager::generate();
		$request = new \WP_REST_Request( 'GET', '/rms/v1/verify' );
		$request->set_header( 'authorization', 'RMS ' . $key );

		$result = RestEndpoint::check_permission( $request );

		$this->assertTrue( $result );
	}

	public function test_handle_verify_returns_rest_response_with_verified_true(): void {
		$key     = KeyManager::generate();
		$request = new \WP_REST_Request( 'GET', '/rms/v1/verify' );
		$request->set_header( 'authorization', 'RMS ' . $key );

		$response = RestEndpoint::handle_verify( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertTrue( $response->data['verified'] );
		$this->assertSame( 200, $response->status );
	}

	public function test_handle_verify_response_includes_required_fields(): void {
		$key     = KeyManager::generate();
		$request = new \WP_REST_Request( 'GET', '/rms/v1/verify' );
		$request->set_header( 'authorization', 'RMS ' . $key );

		$response = RestEndpoint::handle_verify( $request );

		$this->assertArrayHasKey( 'site_name', $response->data );
		$this->assertArrayHasKey( 'wp_version', $response->data );
		$this->assertArrayHasKey( 'plugin_version', $response->data );
		$this->assertArrayHasKey( 'uploads_baseurl', $response->data );
	}
}
