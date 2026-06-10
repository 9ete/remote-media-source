<?php
/**
 * Plugin service-registry unit tests.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use RemoteMediaSource\Core\Plugin;

#[\PHPUnit\Framework\Attributes\CoversClass( Plugin::class )]
class PluginTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['rms_test_options']     = array();
		$GLOBALS['rms_test_hooks']       = array();
		$GLOBALS['rms_test_textdomains'] = array();
		$GLOBALS['rms_test_is_admin']    = false;
	}

	/**
	 * Hook names registered by the recorded add_action/add_filter calls.
	 *
	 * @return array<string>
	 */
	private function registered_hooks(): array {
		return array_column( $GLOBALS['rms_test_hooks'], 'hook' );
	}

	public function test_constructor_defers_init_to_plugins_loaded(): void {
		new Plugin();
		$this->assertContains( 'plugins_loaded', $this->registered_hooks() );
	}

	public function test_init_registers_frontend_services(): void {
		( new Plugin() )->init();
		$hooks = $this->registered_hooks();

		// RestEndpoint, UploadDir register on every request.
		$this->assertContains( 'rest_api_init', $hooks );
		$this->assertContains( 'upload_dir', $hooks );
	}

	public function test_init_skips_admin_services_on_frontend(): void {
		( new Plugin() )->init();
		$this->assertNotContains( 'admin_menu', $this->registered_hooks() );
	}

	public function test_init_registers_admin_services_in_admin(): void {
		$GLOBALS['rms_test_is_admin'] = true;
		( new Plugin() )->init();
		$hooks = $this->registered_hooks();

		// SettingsPage + Assets register only when is_admin().
		$this->assertContains( 'admin_menu', $hooks );
		$this->assertContains( 'admin_enqueue_scripts', $hooks );
	}
}
