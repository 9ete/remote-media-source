<?php
/**
 * Assets unit tests.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use RemoteMediaSource\Core\Assets;

#[\PHPUnit\Framework\Attributes\CoversClass( Assets::class )]
class AssetsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['rms_test_hooks']             = array();
		$GLOBALS['rms_test_enqueued_scripts']  = array();
		$GLOBALS['rms_test_localized_scripts'] = array();
	}

	public function test_register_hooks_admin_enqueue_scripts(): void {
		Assets::register();
		$hooks = array_column( $GLOBALS['rms_test_hooks'], 'hook' );
		$this->assertContains( 'admin_enqueue_scripts', $hooks );
	}

	public function test_enqueue_skips_other_admin_pages(): void {
		Assets::enqueue( 'edit.php' );
		$this->assertArrayNotHasKey( 'rms-admin', $GLOBALS['rms_test_enqueued_scripts'] );
	}

	public function test_enqueue_loads_script_on_settings_page(): void {
		Assets::enqueue( 'settings_page_remote-media-source' );
		$this->assertArrayHasKey( 'rms-admin', $GLOBALS['rms_test_enqueued_scripts'] );
	}

	public function test_enqueue_script_depends_on_jquery(): void {
		Assets::enqueue( 'settings_page_remote-media-source' );
		$this->assertContains( 'jquery', $GLOBALS['rms_test_enqueued_scripts']['rms-admin']['deps'] );
	}

	public function test_enqueue_versions_script_with_plugin_version(): void {
		Assets::enqueue( 'settings_page_remote-media-source' );
		$this->assertSame( RMS_VERSION, $GLOBALS['rms_test_enqueued_scripts']['rms-admin']['ver'] );
	}

	public function test_enqueue_localizes_ajax_config(): void {
		Assets::enqueue( 'settings_page_remote-media-source' );
		$localized = $GLOBALS['rms_test_localized_scripts']['rms-admin'];

		$this->assertSame( 'rmsAdmin', $localized['object'] );
		$this->assertArrayHasKey( 'ajaxUrl', $localized['data'] );
		$this->assertArrayHasKey( 'generateKey', $localized['data']['nonces'] );
		$this->assertArrayHasKey( 'testConnection', $localized['data']['nonces'] );
	}
}
