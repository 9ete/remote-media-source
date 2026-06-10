<?php
/**
 * Adds a Settings action link to the plugin row.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Prepends a Settings link to the plugin action links in the plugins list.
 */
class PluginLinks {

	/**
	 * Add the Settings link.
	 *
	 * @param array<string, string> $links Existing action links.
	 * @return array<string, string>
	 */
	public static function add_settings_link( array $links ): array {
		$url  = admin_url( 'options-general.php?page=remote-media-source' );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'remote-media-source' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}
}
