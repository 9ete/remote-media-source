<?php
/**
 * Activator unit tests.
 *
 * The PHP-version gate inside activate() compares against the live
 * PHP_VERSION constant, so the wp_die branch is unreachable on the
 * PHP >= 8.1 runtimes these tests support — only the happy path is tested.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use RemoteMediaSource\Core\Activator;

#[\PHPUnit\Framework\Attributes\CoversClass( Activator::class )]
class ActivatorTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['rms_test_options'] = array();
	}

	public function test_activate_seeds_default_role(): void {
		Activator::activate();
		$this->assertSame( '', get_option( 'rms_role' ) );
	}

	public function test_activate_seeds_default_upload_mode(): void {
		Activator::activate();
		$this->assertSame( 'local', get_option( 'rms_upload_mode' ) );
	}

	public function test_activate_preserves_existing_role(): void {
		update_option( 'rms_role', 'source' );
		Activator::activate();
		$this->assertSame( 'source', get_option( 'rms_role' ) );
	}

	public function test_activate_preserves_existing_upload_mode(): void {
		update_option( 'rms_upload_mode', 'block' );
		Activator::activate();
		$this->assertSame( 'block', get_option( 'rms_upload_mode' ) );
	}
}
