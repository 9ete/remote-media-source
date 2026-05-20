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

// ── Hook stubs (no-ops) ───────────────────────────────────────────────────────
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, callable|array|string $callback, int $priority = 10, int $args = 1 ): bool {
		return true;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, callable|array|string $callback, int $priority = 10, int $args = 1 ): bool {
		return true;
	}
}
if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( string $namespace, string $route, array $args = array(), bool $override = false ): bool {
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
