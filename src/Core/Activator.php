<?php
/**
 * Plugin activation handler.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin activation: version gate + default options.
 */
class Activator {

	/**
	 * Activate the plugin.
	 */
	public static function activate(): void {
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( plugin_basename( RMS_PLUGIN_FILE ) );
			wp_die(
				esc_html__( 'Remote Media Source requires PHP 8.1 or higher.', 'remote-media-source' ),
				esc_html__( 'Plugin Activation Error', 'remote-media-source' ),
				array( 'back_link' => true )
			);
		}

		add_option( 'rms_role', '' );
		add_option( 'rms_upload_mode', 'local' );
	}
}
