<?php
/**
 * PHPUnit bootstrap — WP function and class stubs for unit tests.
 *
 * @package RemoteMediaSource
 */

defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );

require_once dirname( __DIR__, 2 ) . '/autoload.php';

// ── Constants ─────────────────────────────────────────────────────────────────
defined( 'RMS_VERSION' )       || define( 'RMS_VERSION', '1.0.0' );
defined( 'RMS_REST_NAMESPACE' ) || define( 'RMS_REST_NAMESPACE', 'rms/v1' );
defined( 'RMS_PLUGIN_FILE' )   || define( 'RMS_PLUGIN_FILE', dirname( __DIR__, 2 ) . '/remote-media-source.php' );
defined( 'RMS_PLUGIN_DIR' )    || define( 'RMS_PLUGIN_DIR', dirname( __DIR__, 2 ) . '/' );
defined( 'RMS_PLUGIN_URL' )    || define( 'RMS_PLUGIN_URL', 'https://test.local/wp-content/plugins/remote-media-source/' );

// ── Options API (backed by a global array) ────────────────────────────────────
$GLOBALS['rms_test_options'] = array();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $key, mixed $default = false ): mixed {
		return $GLOBALS['rms_test_options'][ $key ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $key, mixed $value, bool $autoload = true ): bool {
		$GLOBALS['rms_test_options'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $key ): bool {
		unset( $GLOBALS['rms_test_options'][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'add_option' ) ) {
	function add_option( string $key, mixed $value = '', string $deprecated = '', bool $autoload = true ): bool {
		if ( array_key_exists( $key, $GLOBALS['rms_test_options'] ) ) {
			return false;
		}
		$GLOBALS['rms_test_options'][ $key ] = $value;
		return true;
	}
}

// ── Password / hashing ────────────────────────────────────────────────────────
if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( int $length = 12, bool $special_chars = true, bool $extra_special_chars = false ): string {
		return bin2hex( random_bytes( (int) ceil( $length / 2 ) ) );
	}
}
if ( ! function_exists( 'wp_hash' ) ) {
	function wp_hash( string $data, string $scheme = 'auth' ): string {
		return hash_hmac( 'sha256', $data, 'test-salt-' . $scheme );
	}
}

// ── Hook stubs (recording) ────────────────────────────────────────────────────
// Registrations are recorded so tests can assert which hooks a service wires
// up. Callbacks are never invoked — these are unit tests, not integration.
$GLOBALS['rms_test_hooks'] = array();

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, callable|array|string $callback, int $priority = 10, int $args = 1 ): bool {
		$GLOBALS['rms_test_hooks'][] = array(
			'type'     => 'filter',
			'hook'     => $hook,
			'callback' => $callback,
			'priority' => $priority,
		);
		return true;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, callable|array|string $callback, int $priority = 10, int $args = 1 ): bool {
		$GLOBALS['rms_test_hooks'][] = array(
			'type'     => 'action',
			'hook'     => $hook,
			'callback' => $callback,
			'priority' => $priority,
		);
		return true;
	}
}
if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( string $namespace, string $route, array $args = array(), bool $override = false ): bool {
		$GLOBALS['rms_test_rest_routes'][] = array(
			'namespace' => $namespace,
			'route'     => $route,
			'args'      => $args,
		);
		return true;
	}
}

// ── WP utility stubs ──────────────────────────────────────────────────────────
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( string $show = '', string $filter = 'raw' ): string {
		$map = array( 'name' => 'Test Site', 'version' => '6.0' );
		return $map[ $show ] ?? '';
	}
}
if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir( ?string $time = null, bool $create_dir = true, bool $refresh_cache = false ): array {
		return array(
			'baseurl' => 'https://example.com/wp-content/uploads',
			'basedir' => '/var/www/html/wp-content/uploads',
		);
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return $text;
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url, array $protocols = array() ): string {
		return $url;
	}
}
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( string $file ): string {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

// ── Control-flow exceptions ───────────────────────────────────────────────────
// Stubs for functions that terminate the request (wp_die, wp_send_json_*,
// redirects followed by exit) throw instead, so tests can assert the outcome
// without killing the PHPUnit process.
class RMS_Test_Redirect extends \Exception {
	public string $location;

	public function __construct( string $location ) {
		parent::__construct( 'redirect: ' . $location );
		$this->location = $location;
	}
}
class RMS_Test_Wp_Die extends \Exception {}
class RMS_Test_Json_Response extends \Exception {
	public bool $success;
	public mixed $payload;

	public function __construct( bool $success, mixed $payload ) {
		parent::__construct( $success ? 'json success' : 'json error' );
		$this->success = $success;
		$this->payload = $payload;
	}
}

// ── Request lifecycle stubs ───────────────────────────────────────────────────
if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( string $location, int $status = 302 ): never {
		throw new RMS_Test_Redirect( $location );
	}
}
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( mixed $message = '', mixed $title = '', mixed $args = array() ): never {
		throw new RMS_Test_Wp_Die( is_string( $message ) ? $message : 'wp_die' );
	}
}
if ( ! function_exists( 'wp_send_json_success' ) ) {
	function wp_send_json_success( mixed $data = null ): never {
		throw new RMS_Test_Json_Response( true, $data );
	}
}
if ( ! function_exists( 'wp_send_json_error' ) ) {
	function wp_send_json_error( mixed $data = null ): never {
		throw new RMS_Test_Json_Response( false, $data );
	}
}

// ── Auth / nonce stubs ────────────────────────────────────────────────────────
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return $GLOBALS['rms_test_user_can'] ?? true;
	}
}
if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( string $action = '-1', string $query_arg = '_wpnonce' ): bool {
		$GLOBALS['rms_test_nonce_checks'][] = $action;
		return true;
	}
}
if ( ! function_exists( 'check_ajax_referer' ) ) {
	function check_ajax_referer( string $action = '-1', string|false $query_arg = false, bool $stop = true ): bool {
		$GLOBALS['rms_test_nonce_checks'][] = $action;
		return true;
	}
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( string $action = '-1' ): string {
		return 'nonce-' . $action;
	}
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( string $action = '-1', string $name = '_wpnonce', bool $referer = true, bool $display = true ): string {
		$field = '<input type="hidden" name="' . $name . '" value="nonce-' . $action . '">';
		if ( $display ) {
			echo $field;
		}
		return $field;
	}
}

// ── HTTP API stubs ────────────────────────────────────────────────────────────
// Tests queue a response (array or WP_Error) in $GLOBALS['rms_test_http_response'].
if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( string $url, array $args = array() ): mixed {
		$GLOBALS['rms_test_http_requests'][] = array(
			'url'  => $url,
			'args' => $args,
		);
		return $GLOBALS['rms_test_http_response'] ?? new WP_Error( 'no_response', 'No test response queued.' );
	}
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( mixed $response ): string {
		if ( is_array( $response ) && isset( $response['body'] ) ) {
			return (string) $response['body'];
		}
		return '';
	}
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( mixed $response ): int|string {
		if ( is_array( $response ) && isset( $response['response']['code'] ) ) {
			return (int) $response['response']['code'];
		}
		return '';
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( mixed $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

// ── Asset stubs (recording) ───────────────────────────────────────────────────
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( string $handle, string $src = '', array $deps = array(), string|bool|null $ver = false, array|bool $args = array() ): void {
		$GLOBALS['rms_test_enqueued_scripts'][ $handle ] = array(
			'src'  => $src,
			'deps' => $deps,
			'ver'  => $ver,
		);
	}
}
if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( string $handle, string $object_name, array $l10n ): bool {
		$GLOBALS['rms_test_localized_scripts'][ $handle ] = array(
			'object' => $object_name,
			'data'   => $l10n,
		);
		return true;
	}
}

// ── Admin / environment stubs ─────────────────────────────────────────────────
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool {
		return $GLOBALS['rms_test_is_admin'] ?? false;
	}
}
if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( string $domain, string|false $deprecated = false, string|false $plugin_rel_path = false ): bool {
		$GLOBALS['rms_test_textdomains'][] = $domain;
		return true;
	}
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string {
		return 'https://test.local/wp-admin/' . ltrim( $path, '/' );
	}
}
if ( ! function_exists( 'deactivate_plugins' ) ) {
	function deactivate_plugins( string|array $plugins ): void {
		$GLOBALS['rms_test_deactivated'][] = $plugins;
	}
}
if ( ! function_exists( 'get_current_screen' ) ) {
	function get_current_screen(): ?object {
		return $GLOBALS['rms_test_current_screen'] ?? null;
	}
}
if ( ! function_exists( 'human_time_diff' ) ) {
	function human_time_diff( int $from, int $to = 0 ): string {
		return '5 mins';
	}
}

// ── Filter application / URL validation stubs ────────────────────────────────
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
		$override = $GLOBALS['rms_test_filter_overrides'][ $hook ] ?? null;
		return null !== $override ? $override : $value;
	}
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( string $url, int $component = -1 ): mixed {
		return parse_url( $url, $component );
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( string $value ): string {
		return rtrim( $value, '/' );
	}
}
if ( ! function_exists( 'wp_http_validate_url' ) ) {
	/**
	 * Approximation of core's SSRF guard: http(s) scheme + host required;
	 * loopback/private/reserved IP-literal hosts are rejected unless a test
	 * sets $GLOBALS['rms_test_host_is_external'] = true.
	 */
	function wp_http_validate_url( string $url ): string|false {
		$parsed = parse_url( $url );
		if ( empty( $parsed['host'] ) || ! in_array( $parsed['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
			return false;
		}
		if ( filter_var( $parsed['host'], FILTER_VALIDATE_IP ) ) {
			$is_public = filter_var(
				$parsed['host'],
				FILTER_VALIDATE_IP,
				FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
			);
			if ( ! $is_public && empty( $GLOBALS['rms_test_host_is_external'] ) ) {
				return false;
			}
		}
		return $url;
	}
}

// ── Sanitization / escaping / i18n stubs ──────────────────────────────────────
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( $str ) ) );
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( string|array $value ): string|array {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}
		return stripslashes( $value );
	}
}
if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string {
		return $url;
	}
}
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( string $text, string $domain = 'default' ): void {
		echo esc_html( $text );
	}
}
if ( ! function_exists( 'selected' ) ) {
	function selected( mixed $selected, mixed $current = true, bool $display = true ): string {
		$result = (string) $selected === (string) $current ? ' selected="selected"' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
	}
}
if ( ! function_exists( 'checked' ) ) {
	function checked( mixed $checked, mixed $current = true, bool $display = true ): string {
		$result = (string) $checked === (string) $current ? ' checked="checked"' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
	}
}
if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( ?string $text = null ): void {
		echo '<input type="submit" value="' . esc_attr( $text ?? 'Save Changes' ) . '">';
	}
}
if ( ! function_exists( 'add_options_page' ) ) {
	function add_options_page( string $page_title, string $menu_title, string $capability, string $menu_slug, callable|string $callback = '' ): string {
		$GLOBALS['rms_test_options_pages'][] = array(
			'page_title' => $page_title,
			'capability' => $capability,
			'menu_slug'  => $menu_slug,
		);
		return 'settings_page_' . $menu_slug;
	}
}

// ── WP class stubs ────────────────────────────────────────────────────────────
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public string $code;
		public string $message;
		public mixed $data;

		public function __construct( string $code = '', string $message = '', mixed $data = array() ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}
if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		public mixed $data;
		public int $status;

		public function __construct( mixed $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}
	}
}
if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private array $headers = array();

		public function __construct( string $method = 'GET', string $route = '' ) {}

		public function get_header( string $header ): ?string {
			return $this->headers[ strtolower( $header ) ] ?? null;
		}

		public function set_header( string $header, string $value ): void {
			$this->headers[ strtolower( $header ) ] = $value;
		}
	}
}
