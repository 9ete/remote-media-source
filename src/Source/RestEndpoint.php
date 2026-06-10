<?php
/**
 * Source REST endpoint for connection verification.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Source;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles GET /wp-json/rms/v1/verify.
 *
 * Only active when this site's role is "source".
 */
class RestEndpoint {

	/**
	 * Register the REST route (called on rest_api_init).
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( static::class, 'register_route' ) );
	}

	/**
	 * Register the verify route.
	 */
	public static function register_route(): void {
		if ( 'source' !== get_option( 'rms_role', '' ) ) {
			return;
		}

		register_rest_route(
			RMS_REST_NAMESPACE,
			'/verify',
			array(
				'methods'             => 'GET',
				'callback'            => array( static::class, 'handle_verify' ),
				'permission_callback' => array( static::class, 'check_permission' ),
			)
		);
	}

	/**
	 * Authenticate the request using the RMS connection key.
	 *
	 * @param \WP_REST_Request $request Incoming REST request.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public static function check_permission( \WP_REST_Request $request ): bool|\WP_Error {
		$auth   = $request->get_header( 'authorization' );
		$prefix = 'RMS ';

		if ( ! $auth || ! str_starts_with( $auth, $prefix ) ) {
			return new \WP_Error(
				'rms_unauthorized',
				esc_html__( 'Missing or invalid Authorization header. Expected: Authorization: RMS <key>', 'remote-media-source' ),
				array( 'status' => 401 )
			);
		}

		$key = substr( $auth, strlen( $prefix ) );

		if ( ! KeyManager::verify( $key ) ) {
			return new \WP_Error(
				'rms_unauthorized',
				esc_html__( 'Invalid connection key.', 'remote-media-source' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Handle the verify request and return site metadata.
	 *
	 * The request object is not needed — authentication already happened in
	 * the permission callback and the response carries no request-derived data.
	 *
	 * @return \WP_REST_Response
	 */
	public static function handle_verify(): \WP_REST_Response {
		$upload_dir = wp_upload_dir();

		return new \WP_REST_Response(
			array(
				'verified'        => true,
				'site_name'       => get_bloginfo( 'name' ),
				'wp_version'      => get_bloginfo( 'version' ),
				'plugin_version'  => RMS_VERSION,
				'uploads_baseurl' => esc_url_raw( $upload_dir['baseurl'] ),
			),
			200
		);
	}
}
