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
	private array $services = array();

	/**
	 * Admin-only services: [hook, callable] pairs.
	 *
	 * @var array<array{0: string, 1: callable}>
	 */
	private array $admin_services = array();

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
		if ( is_admin() ) {
			foreach ( $this->admin_services as $service ) {
				[ $hook, $callback ] = $service;
				add_action( $hook, $callback );
			}
		}

		foreach ( $this->services as $service ) {
			$service::register();
		}
	}
}
