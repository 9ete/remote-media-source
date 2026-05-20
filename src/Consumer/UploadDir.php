<?php
/**
 * Upload directory URL rewriting for consumer environments.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Consumer;

defined( 'ABSPATH' ) || exit;

/**
 * Rewrites upload directory base URLs to the remote source.
 * Skips during active file uploads so new files are tracked with local URLs.
 */
class UploadDir {

	/**
	 * True while a file upload is in progress.
	 *
	 * @var bool
	 */
	private static bool $uploading = false;

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_filter( 'upload_dir', array( self::class, 'filter' ) );
		add_filter( 'wp_handle_upload_prefilter', array( self::class, 'upload_start' ), 1 );
		add_action( 'wp_handle_upload', array( self::class, 'upload_end' ), 999 );
		add_action( 'wp_handle_sideload', array( self::class, 'upload_end' ), 999 );
	}

	/**
	 * Mark that an upload is in progress (set before wp_upload_dir() is called).
	 *
	 * @param array $file Uploaded file descriptor.
	 * @return array Unchanged.
	 */
	public static function upload_start( array $file ): array {
		self::$uploading = true;
		return $file;
	}

	/**
	 * Clear the upload-in-progress flag.
	 */
	public static function upload_end(): void {
		self::$uploading = false;
	}

	/**
	 * Rewrite upload base URLs to the remote source.
	 *
	 * @param array $dirs Current upload directory values.
	 * @return array Modified upload directory values.
	 */
	public static function filter( array $dirs ): array {
		if ( self::$uploading ) {
			return $dirs;
		}

		if ( ! self::is_active() ) {
			return $dirs;
		}

		$base        = rtrim( get_option( 'rms_remote_url', '' ), '/' ) . '/wp-content/uploads';
		$dirs['baseurl'] = $base;
		$dirs['url']     = $base . '/' . gmdate( 'Y/m' );

		return $dirs;
	}

	/**
	 * Check whether URL rewriting should be active.
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
