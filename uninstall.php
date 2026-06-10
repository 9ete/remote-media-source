<?php
/**
 * Uninstall handler — remove every option the plugin ever writes.
 *
 * The consumer connection key is stored raw in wp_options by design (it must
 * be replayable against the source), so deleting the plugin has to scrub it
 * rather than leave a live credential behind.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Uninstall;

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Delete all plugin options on the current site.
 */
function uninstall_site(): void {
	$options = array(
		'rms_role',
		'rms_connection_key_hash',
		'rms_connection_key_prefix',
		'rms_remote_url',
		'rms_remote_key',
		'rms_upload_mode',
		'rms_last_connection',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}
}

/**
 * Run the uninstall across every site that may hold plugin options.
 */
function uninstall(): void {
	if ( ! is_multisite() ) {
		uninstall_site();
		return;
	}

	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		uninstall_site();
		restore_current_blog();
	}
}

uninstall();
