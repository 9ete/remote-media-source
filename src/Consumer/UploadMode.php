<?php
/**
 * Upload mode enforcement for the Consumer role.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Consumer;

defined( 'ABSPATH' ) || exit;

/**
 * Enforces the configured upload policy when this site is a consumer.
 *
 * Modes:
 *   local — uploads land in the local filesystem (default, no extra hooks).
 *   block — uploads are rejected at the file handler level with an admin notice.
 */
class UploadMode {

	/**
	 * Register upload enforcement hooks.
	 *
	 * No-op when role is not consumer or mode is local.
	 */
	public static function register(): void {
		if ( 'consumer' !== get_option( 'rms_role', '' ) ) {
			return;
		}

		if ( 'block' !== static::get_mode() ) {
			return;
		}

		add_filter( 'upload_mimes', array( static::class, 'filter_mimes_block' ) );
		add_filter( 'wp_handle_upload_prefilter', array( static::class, 'prefilter_block' ) );
		add_action( 'admin_notices', array( static::class, 'block_notice' ) );
	}

	/**
	 * Return the configured upload mode.
	 *
	 * Falls back to 'local' for any unrecognised stored value.
	 *
	 * @return string 'local' or 'block'
	 */
	public static function get_mode(): string {
		$mode = (string) get_option( 'rms_upload_mode', 'local' );
		return in_array( $mode, array( 'local', 'block' ), true ) ? $mode : 'local';
	}

	/**
	 * Block all uploads by returning an empty MIME list.
	 *
	 * @param array $_mimes Allowed MIME types (unused — all types are blocked).
	 * @return array Empty array — no type is permitted.
	 */
	public static function filter_mimes_block( array $_mimes ): array {
		return array();
	}

	/**
	 * Reject the upload at the prefilter stage with an explanatory error.
	 *
	 * @param array $file Uploaded file data.
	 * @return array File data with 'error' key set.
	 */
	public static function prefilter_block( array $file ): array {
		$file['error'] = esc_html__(
			'Uploads are blocked on this environment. Change the upload mode in Settings › Remote Media Source.',
			'remote-media-source'
		);
		return $file;
	}

	/**
	 * Show an admin notice on the media upload screen when uploads are blocked.
	 */
	public static function block_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'upload', 'media' ), true ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' .
			esc_html__( 'File uploads are blocked on this environment by Remote Media Source.', 'remote-media-source' ) .
			'</p></div>';
	}
}
