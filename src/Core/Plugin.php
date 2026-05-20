<?php
/**
 * Plugin service registry.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstrap and register all plugin services.
 */
class Plugin {

	/**
	 * Services registered on every request.
	 *
	 * Each class must expose a static register() method.
	 *
	 * @var array<class-string>
	 */
	private array $services = array(
		\RemoteMediaSource\Source\RestEndpoint::class,
		\RemoteMediaSource\Consumer\UploadDir::class,
		\RemoteMediaSource\Consumer\UploadMode::class,
	);

	/**
	 * Admin-only services registered when is_admin() is true.
	 *
	 * Each class must expose a static register() method.
	 *
	 * @var array<class-string>
	 */
	private array $admin_services = array(
		\RemoteMediaSource\Admin\SettingsPage::class,
		\RemoteMediaSource\Core\Assets::class,
	);

	/**
	 * Hook into WordPress lifecycle.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Register all services.
	 */
	public function init(): void {
		load_plugin_textdomain( 'remote-media-source', false, dirname( plugin_basename( RMS_PLUGIN_FILE ) ) . '/languages' );

		foreach ( $this->services as $service ) {
			$service::register();
		}

		if ( is_admin() ) {
			foreach ( $this->admin_services as $service ) {
				$service::register();
			}
		}
	}
}
