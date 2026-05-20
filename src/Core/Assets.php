<?php
/**
 * Admin asset enqueuing.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues admin JS on the Remote Media Source settings page only.
 */
class Assets {

	/**
	 * Register the admin_enqueue_scripts hook.
	 */
	public static function register(): void {
		add_action( 'admin_enqueue_scripts', array( static::class, 'enqueue' ) );
	}

	/**
	 * Enqueue scripts if on the plugin settings page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public static function enqueue( string $hook_suffix ): void {
		if ( 'settings_page_remote-media-source' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'rms-admin',
			RMS_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery' ),
			RMS_VERSION,
			true
		);

		wp_localize_script(
			'rms-admin',
			'rmsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonces'  => array(
					'generateKey'    => wp_create_nonce( 'rms_generate_key' ),
					'testConnection' => wp_create_nonce( 'rms_test_connection' ),
				),
				'i18n'    => array(
					'copySuccess'       => __( 'Copied!', 'remote-media-source' ),
					'copyFail'          => __( 'Copy failed — please select and copy manually.', 'remote-media-source' ),
					'regenerateConfirm' => __( 'Regenerating will invalidate the current key and disconnect all consumers. Continue?', 'remote-media-source' ),
					'testing'           => __( 'Testing…', 'remote-media-source' ),
					'testConnection'    => __( 'Test Connection', 'remote-media-source' ),
					'hide'              => __( 'Hide', 'remote-media-source' ),
					'reveal'            => __( 'Reveal', 'remote-media-source' ),
					'copyKey'           => __( 'Copy Key', 'remote-media-source' ),
					'requestFailed'     => __( 'Request failed.', 'remote-media-source' ),
				),
			)
		);
	}
}
