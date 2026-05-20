<?php
/**
 * Rewrites the upload directory URL to the configured remote source.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Consumer;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks the upload_dir filter when the consumer role is active and a
 * verified connection exists.
 */
class UploadDir {

	/**
	 * Register the upload_dir filter.
	 */
	public static function register(): void {
		add_filter( 'upload_dir', array( static::class, 'filter' ) );
	}

	/**
	 * Rewrite upload URLs to the remote source.
	 *
	 * basedir and path are left unchanged so local-mode uploads still land
	 * in the local filesystem.
	 *
	 * @param array $dirs WP upload dir data.
	 * @return array Modified upload dir data.
	 */
	public static function filter( array $dirs ): array {
		if ( ! static::is_active() ) {
			return $dirs;
		}

		$base            = rtrim( get_option( 'rms_remote_url', '' ), '/' ) . '/wp-content/uploads';
		$dirs['baseurl'] = $base;
		$dirs['url']     = $base . '/' . gmdate( 'Y/m' );

		return $dirs;
	}

	/**
	 * Whether the URL rewrite should be active.
	 *
	 * Requires: consumer role, non-empty remote URL, and at least one
	 * successful test connection on record.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		if ( 'consumer' !== get_option( 'rms_role', '' ) ) {
			return false;
		}

		if ( ! get_option( 'rms_remote_url', '' ) ) {
			return false;
		}

		$last = get_option( 'rms_last_connection', array() );

		return ! empty( $last['success'] );
	}
}
