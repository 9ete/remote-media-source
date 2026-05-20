<?php
/**
 * Plugin action links.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a Settings link to the plugin action links on the Plugins screen.
 */
class PluginLinks {

	/**
	 * Prepend a Settings link to the plugin's action links list.
	 *
	 * @param array<string, string> $links Existing action links.
	 * @return array<string, string>
	 */
	public static function add_settings_link( array $links ): array {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=remote-media-source' ) ),
			esc_html__( 'Settings', 'remote-media-source' )
		);
		array_unshift( $links, $settings );
		return $links;
	}
}
