<?php
/**
 * KeyManager unit tests.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Tests\Unit\Source;

use PHPUnit\Framework\TestCase;
use RemoteMediaSource\Source\KeyManager;

/**
 * @covers RemoteMediaSource\Source\KeyManager
 */
class KeyManagerTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['rms_test_options'] = array();
	}

	public function test_generate_returns_64_char_hex_string(): void {
		$key = KeyManager::generate();
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $key );
	}

	public function test_generate_stores_hash_in_options(): void {
		KeyManager::generate();
		$this->assertNotEmpty( $GLOBALS['rms_test_options']['rms_connection_key_hash'] );
	}

	public function test_generate_stores_8_char_prefix(): void {
		KeyManager::generate();
		$this->assertSame( 8, strlen( $GLOBALS['rms_test_options']['rms_connection_key_prefix'] ) );
	}

	public function test_generate_prefix_matches_start_of_key(): void {
		$key = KeyManager::generate();
		$this->assertSame( substr( $key, 0, 8 ), $GLOBALS['rms_test_options']['rms_connection_key_prefix'] );
	}

	public function test_verify_returns_true_for_valid_key(): void {
		$key = KeyManager::generate();
		$this->assertTrue( KeyManager::verify( $key ) );
	}

	public function test_verify_returns_false_for_wrong_key(): void {
		KeyManager::generate();
		$this->assertFalse( KeyManager::verify( str_repeat( 'a', 64 ) ) );
	}

	public function test_verify_returns_false_when_no_hash_stored(): void {
		$this->assertFalse( KeyManager::verify( str_repeat( 'a', 64 ) ) );
	}

	public function test_has_key_returns_false_when_no_key_generated(): void {
		$this->assertFalse( KeyManager::has_key() );
	}

	public function test_has_key_returns_true_after_generate(): void {
		KeyManager::generate();
		$this->assertTrue( KeyManager::has_key() );
	}

	public function test_get_prefix_returns_empty_string_before_generate(): void {
		$this->assertSame( '', KeyManager::get_prefix() );
	}

	public function test_get_prefix_returns_stored_prefix_after_generate(): void {
		KeyManager::generate();
		$this->assertSame( $GLOBALS['rms_test_options']['rms_connection_key_prefix'], KeyManager::get_prefix() );
	}

	public function test_rotate_invalidates_old_key(): void {
		$old = KeyManager::generate();
		KeyManager::rotate();
		$this->assertFalse( KeyManager::verify( $old ) );
	}

	public function test_rotate_new_key_verifies(): void {
		KeyManager::generate();
		$new = KeyManager::rotate();
		$this->assertTrue( KeyManager::verify( $new ) );
	}
}
