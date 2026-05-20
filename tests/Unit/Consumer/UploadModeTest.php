<?php
/**
 * UploadMode unit tests.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Tests\Unit\Consumer;

use PHPUnit\Framework\TestCase;
use RemoteMediaSource\Consumer\UploadMode;

#[\PHPUnit\Framework\Attributes\CoversClass( UploadMode::class )]
class UploadModeTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['rms_test_options'] = array();
	}

	public function test_get_mode_returns_local_when_nothing_stored(): void {
		$this->assertSame( 'local', UploadMode::get_mode() );
	}

	public function test_get_mode_returns_local_when_stored(): void {
		$GLOBALS['rms_test_options']['rms_upload_mode'] = 'local';
		$this->assertSame( 'local', UploadMode::get_mode() );
	}

	public function test_get_mode_returns_block_when_stored(): void {
		$GLOBALS['rms_test_options']['rms_upload_mode'] = 'block';
		$this->assertSame( 'block', UploadMode::get_mode() );
	}

	public function test_get_mode_falls_back_to_local_for_unknown_value(): void {
		$GLOBALS['rms_test_options']['rms_upload_mode'] = 'unknown_value';
		$this->assertSame( 'local', UploadMode::get_mode() );
	}

	public function test_filter_mimes_block_returns_empty_array(): void {
		$mimes  = array( 'image/jpeg' => 'jpg|jpeg', 'image/png' => 'png' );
		$result = UploadMode::filter_mimes_block( $mimes );
		$this->assertSame( array(), $result );
	}

	public function test_prefilter_block_adds_error_to_file(): void {
		$file = array(
			'name'     => 'photo.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => '/tmp/phpXXXXXX',
			'error'    => 0,
			'size'     => 1024,
		);

		$result = UploadMode::prefilter_block( $file );

		$this->assertNotEmpty( $result['error'] );
		$this->assertIsString( $result['error'] );
	}

	public function test_prefilter_block_preserves_other_file_fields(): void {
		$file = array(
			'name'     => 'photo.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => '/tmp/phpXXXXXX',
			'error'    => 0,
			'size'     => 1024,
		);

		$result = UploadMode::prefilter_block( $file );

		$this->assertSame( 'photo.jpg', $result['name'] );
		$this->assertSame( 1024, $result['size'] );
	}
}
