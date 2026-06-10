<?php
/**
 * Plugin Name: Remote Media Source
 * Plugin URI:  https://lowermedia.net/plugins/remote-media-source
 * Description: Serve media from a remote WordPress site on local/dev/staging environments.
 * Version:     1.0.0
 * Author:      9ete
 * Author URI:  https://lowermedia.net
 * Text Domain: remote-media-source
 * Requires PHP: 8.1
 * Requires at least: 6.0
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package RemoteMediaSource
 */

defined( 'ABSPATH' ) || exit;

define( 'RMS_VERSION', '1.0.0' );
define( 'RMS_PLUGIN_FILE', __FILE__ );
define( 'RMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RMS_REST_NAMESPACE', 'rms/v1' );

require_once __DIR__ . '/autoload.php';

( new RemoteMediaSource\Core\Plugin() );

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	array( RemoteMediaSource\Admin\PluginLinks::class, 'add_settings_link' )
);

register_activation_hook( RMS_PLUGIN_FILE, array( RemoteMediaSource\Core\Activator::class, 'activate' ) );
