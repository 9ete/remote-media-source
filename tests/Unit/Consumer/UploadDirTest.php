<?php
/**
 * UploadDir unit tests.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Tests\Unit\Consumer;

use PHPUnit\Framework\TestCase;
use RemoteMediaSource\Consumer\UploadDir;

#[\PHPUnit\Framework\Attributes\CoversClass( UploadDir::class )]
class UploadDirTest extends TestCase {

	private array $base_dirs = array(
		'baseurl' => 'http://local.test/wp-content/uploads',
		'url'     => 'http://local.test/wp-content/uploads/2026/05',
		'basedir' => '/var/www/html/wp-content/uploads',
		'path'    => '/var/www/html/wp-content/uploads/2026/05',
	);

	protected function setUp(): void {
		$GLOBALS['rms_test_options'] = array();
	}

	public function test_filter_rewrites_baseurl_when_active(): void {
		$GLOBALS['rms_test_options']['rms_role']            = 'consumer';
		$GLOBALS['rms_test_options']['rms_remote_url']      = 'https://example.com';
		$GLOBALS['rms_test_options']['rms_last_connection'] = array( 'success' => true );

		$result = UploadDir::filter( $this->base_dirs );

		$this->assertSame( 'https://example.com/wp-content/uploads', $result['baseurl'] );
	}

	public function test_filter_rewrites_url_to_remote_with_date_path(): void {
		$GLOBALS['rms_test_options']['rms_role']            = 'consumer';
		$GLOBALS['rms_test_options']['rms_remote_url']      = 'https://example.com';
		$GLOBALS['rms_test_options']['rms_last_connection'] = array( 'success' => true );

		$result = UploadDir::filter( $this->base_dirs );

		$this->assertStringStartsWith( 'https://example.com/wp-content/uploads/', $result['url'] );
	}

	public function test_filter_strips_trailing_slash_from_remote_url(): void {
		$GLOBALS['rms_test_options']['rms_role']            = 'consumer';
		$GLOBALS['rms_test_options']['rms_remote_url']      = 'https://example.com/';
		$GLOBALS['rms_test_options']['rms_last_connection'] = array( 'success' => true );

		$result = UploadDir::filter( $this->base_dirs );

		$this->assertSame( 'https://example.com/wp-content/uploads', $result['baseurl'] );
	}

	public function test_filter_leaves_basedir_unchanged(): void {
		$GLOBALS['rms_test_options']['rms_role']            = 'consumer';
		$GLOBALS['rms_test_options']['rms_remote_url']      = 'https://example.com';
		$GLOBALS['rms_test_options']['rms_last_connection'] = array( 'success' => true );

		$result = UploadDir::filter( $this->base_dirs );

		$this->assertSame( '/var/www/html/wp-content/uploads', $result['basedir'] );
	}

	public function test_filter_is_noop_when_role_is_source(): void {
		$GLOBALS['rms_test_options']['rms_role'] = 'source';

		$result = UploadDir::filter( $this->base_dirs );

		$this->assertSame( $this->base_dirs, $result );
	}

	public function test_filter_is_noop_when_role_is_empty(): void {
		$GLOBALS['rms_test_options']['rms_role'] = '';

		$result = UploadDir::filter( $this->base_dirs );

		$this->assertSame( $this->base_dirs, $result );
	}

	public function test_filter_is_noop_when_remote_url_empty(): void {
		$GLOBALS['rms_test_options']['rms_role']            = 'consumer';
		$GLOBALS['rms_test_options']['rms_remote_url']      = '';
		$GLOBALS['rms_test_options']['rms_last_connection'] = array( 'success' => true );

		$result = UploadDir::filter( $this->base_dirs );

		$this->assertSame( $this->base_dirs, $result );
	}

	public function test_filter_is_noop_without_verified_connection(): void {
		$GLOBALS['rms_test_options']['rms_role']       = 'consumer';
		$GLOBALS['rms_test_options']['rms_remote_url'] = 'https://example.com';
		// rms_last_connection not set

		$result = UploadDir::filter( $this->base_dirs );

		$this->assertSame( $this->base_dirs, $result );
	}

	public function test_filter_is_noop_when_last_connection_failed(): void {
		$GLOBALS['rms_test_options']['rms_role']            = 'consumer';
		$GLOBALS['rms_test_options']['rms_remote_url']      = 'https://example.com';
		$GLOBALS['rms_test_options']['rms_last_connection'] = array( 'success' => false );

		$result = UploadDir::filter( $this->base_dirs );

		$this->assertSame( $this->base_dirs, $result );
	}

	public function test_is_active_returns_true_when_all_conditions_met(): void {
		$GLOBALS['rms_test_options']['rms_role']            = 'consumer';
		$GLOBALS['rms_test_options']['rms_remote_url']      = 'https://example.com';
		$GLOBALS['rms_test_options']['rms_last_connection'] = array( 'success' => true );

		$this->assertTrue( UploadDir::is_active() );
	}

	public function test_is_active_returns_false_for_source_role(): void {
		$GLOBALS['rms_test_options']['rms_role']            = 'source';
		$GLOBALS['rms_test_options']['rms_remote_url']      = 'https://example.com';
		$GLOBALS['rms_test_options']['rms_last_connection'] = array( 'success' => true );

		$this->assertFalse( UploadDir::is_active() );
	}
}
