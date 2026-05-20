# Remote Media Source v1.0 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a WP.org-ready WordPress plugin that allows non-production WordPress environments to serve media from a designated remote source, with a settings UI, connection key authentication, and configurable upload policies.

**Architecture:** Two-role model (Source / Consumer). Source generates a hashed connection key and exposes `GET /wp-json/rms/v1/verify`. Consumer rewrites `upload_dir` URLs to the remote, enforces upload mode (local or block), and verifies the remote via an AJAX test-connection flow before activating the URL rewrite. Custom PSR-4 autoloader; no Composer autoload.

**Tech Stack:** PHP 8.1+, WordPress 6.0+, Composer, PHPCS/WPCS 3.x, PHPUnit 10.x, WP-CLI bundle, jQuery (WP bundled).

---

## File Map

```
remote-media-source/
├── remote-media-source.php        # Plugin header, constants, bootstrap
├── autoload.php                   # Namespace → src/ resolver
├── README.md                      # GitHub README
├── readme.txt                     # WP.org listing (all required sections)
├── gpl-2.0.txt
├── composer.json
├── phpcs.xml.dist
├── phpunit.xml.dist
├── .distignore                    # (update existing)
├── .editorconfig
├── .githooks/
│   ├── pre-commit                 # composer lint:fix
│   └── commit-msg                 # CC validation + lint + unit tests
├── bin/
│   └── build-zip.sh
├── src/
│   ├── Core/
│   │   ├── Plugin.php             # Service registry
│   │   ├── Assets.php             # Admin JS enqueue (settings page only)
│   │   └── Activator.php         # Activation: version check + default options
│   ├── Admin/
│   │   ├── SettingsPage.php      # Role selector, source/consumer tabs, AJAX handlers
│   │   └── PluginLinks.php       # "Settings" action link
│   ├── Source/
│   │   ├── KeyManager.php        # Generate, hash, verify, rotate connection key
│   │   └── RestEndpoint.php      # GET /wp-json/rms/v1/verify
│   └── Consumer/
│       ├── UploadDir.php          # upload_dir filter
│       └── UploadMode.php         # local-only + block upload enforcement
├── assets/
│   └── admin.js                   # Role toggle, key modal, test connection, reveal key
└── tests/
    ├── bootstrap.php              # Require Unit/bootstrap.php
    └── Unit/
        ├── bootstrap.php          # WP function stubs, WP class stubs, constants
        ├── Source/
        │   ├── KeyManagerTest.php
        │   └── RestEndpointTest.php
        └── Consumer/
            ├── UploadDirTest.php
            └── UploadModeTest.php
```

---

## Task 1: Tooling Scaffold

**Files:**
- Create: `composer.json`
- Create: `phpcs.xml.dist`
- Create: `phpunit.xml.dist`
- Create: `.editorconfig`
- Update: `.distignore`
- Create: `.githooks/pre-commit`
- Create: `.githooks/commit-msg`
- Create: `bin/build-zip.sh`
- Create: `autoload.php`
- Create: `tests/bootstrap.php`
- Create: `tests/Unit/bootstrap.php`
- Create: `gpl-2.0.txt`

- [ ] **Step 1: Create `composer.json`**

```json
{
	"require-dev": {
		"squizlabs/php_codesniffer": "^3.13",
		"wp-coding-standards/wpcs": "^3.3",
		"phpcompatibility/phpcompatibility-wp": "^2.1",
		"dealerdirect/phpcodesniffer-composer-installer": "^1.2",
		"wp-cli/wp-cli-bundle": "^2.12",
		"phpunit/phpunit": "^10.5"
	},
	"config": {
		"allow-plugins": {
			"dealerdirect/phpcodesniffer-composer-installer": true
		}
	},
	"scripts": {
		"install-hooks": "git config core.hooksPath .githooks",
		"lint": "phpcs",
		"lint:fix": "phpcbf",
		"test": "phpunit",
		"test:unit:php": "./vendor/bin/phpunit",
		"build-zip": "./bin/build-zip.sh",
		"plugin-check": "./bin/build-zip.sh -q && unzip -q remote-media-source.zip && wp plugin check remote-media-source && rm -f remote-media-source.zip && rm -rf remote-media-source"
	}
}
```

- [ ] **Step 2: Create `phpcs.xml.dist`**

```xml
<?xml version="1.0"?>
<ruleset name="RemoteMediaSource">
  <description>WordPress PHPCS Standards.</description>

  <file>./</file>

  <exclude-pattern>*/vendor/*</exclude-pattern>
  <exclude-pattern>*/.git/*</exclude-pattern>
  <exclude-pattern>*/zips/*</exclude-pattern>
  <exclude-pattern>*/.claude/*</exclude-pattern>
  <exclude-pattern>*/tests/*</exclude-pattern>
  <exclude-pattern>*/docs/*</exclude-pattern>
  <exclude-pattern>*/brain/*</exclude-pattern>

  <rule ref="WordPress" />
  <rule ref="WordPress-Extra" />
  <rule ref="WordPress-Docs" />

  <rule ref="WordPress">
    <exclude name="WordPress.Files.FileName.NotHyphenatedLowercase"/>
    <exclude name="WordPress.Files.FileName.InvalidClassFileName"/>
  </rule>
  <rule ref="WordPress-Extra">
    <exclude name="WordPress.Files.FileName.NotHyphenatedLowercase"/>
    <exclude name="WordPress.Files.FileName.InvalidClassFileName"/>
  </rule>

  <config name="testVersion" value="8.1-8.4"/>
  <rule ref="PHPCompatibilityWP" />

  <config name="minimum_supported_wp_version" value="6.0"/>
  <arg name="basepath" value="."/>
  <arg name="colors"/>
  <arg name="extensions" value="php"/>
</ruleset>
```

- [ ] **Step 3: Create `phpunit.xml.dist`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
	xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
	xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.5/phpunit.xsd"
	bootstrap="tests/Unit/bootstrap.php"
	colors="true"
>
	<testsuites>
		<testsuite name="Unit">
			<directory>tests/Unit</directory>
		</testsuite>
	</testsuites>
</phpunit>
```

- [ ] **Step 4: Create `.editorconfig`**

```ini
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
trim_trailing_whitespace = true

[*.php]
indent_style = tab

[*.{json,yml,yaml}]
indent_style = space
indent_size = 2

[*.{js,css}]
indent_style = tab

[*.md]
trim_trailing_whitespace = false
```

- [ ] **Step 5: Update `.distignore`** (replace the existing file content)

```
# Build / VCS / meta
.distignore
.editorconfig
.gitattributes
.git
.githooks
.gitignore
.github

# AI / IDE
.claude
.vscode
.idea
*.swp
.DS_Store

# Dev dependencies
vendor
composer.json
composer.lock
node_modules

# JS tooling
package.json
package-lock.json

# Tests / QA
bin
tests
phpunit.xml
phpunit.xml.dist
phpcs.xml
phpcs.xml.dist
.phpunit.result.cache

# Docs / planning
docs
brain
*.md
*.markdown

# Build output
zips
*.zip
```

- [ ] **Step 6: Create `.githooks/pre-commit`**

```sh
#!/bin/sh
composer lint:fix
```

Make executable: `chmod +x .githooks/pre-commit`

- [ ] **Step 7: Create `.githooks/commit-msg`**

```sh
#!/bin/sh

commit_msg_file="$1"
commit_msg=$(cat "$commit_msg_file")

if ! echo "$commit_msg" | grep -qE '^(Merge|fixup!|squash!) '; then
	pattern='^([A-Z]+-[0-9]+: )?(feat|fix|docs|style|refactor|perf|test|build|ci|chore|revert)(\([a-zA-Z0-9 _/.-]+\))?!?: .+'
	if ! echo "$commit_msg" | head -1 | grep -qE "$pattern"; then
		echo ""
		echo "ERROR: Commit message does not follow Conventional Commits."
		echo ""
		echo "  Expected: [TICKET-NNN: ]<type>[optional scope]: <description>"
		echo "  Allowed types: feat, fix, docs, style, refactor, perf, test, build, ci, chore, revert"
		echo ""
		echo "  Examples:"
		echo "    feat(source): add key generation endpoint"
		echo "    fix(consumer): correct upload_dir baseurl trailing slash"
		echo "    chore: update composer.lock"
		echo ""
		echo "  Your message: $(head -1 "$commit_msg_file")"
		echo ""
		exit 1
	fi
fi

composer lint:fix
composer lint
composer test:unit:php
```

Make executable: `chmod +x .githooks/commit-msg`

- [ ] **Step 8: Create `bin/build-zip.sh`**

Copy verbatim from [ai-content-by-parallax](https://github.com/MMDBSolutions/ai-content-by-parallax/blob/main/bin/build-zip.sh), replacing the hardcoded main file check on this line:

```bash
# Replace this line:
if [[ -f "${PLUGIN_ROOT}/ai-content-by-parallax.php" ]]; then
    MAIN_FILE="${PLUGIN_ROOT}/ai-content-by-parallax.php"

# With:
if [[ -f "${PLUGIN_ROOT}/remote-media-source.php" ]]; then
    MAIN_FILE="${PLUGIN_ROOT}/remote-media-source.php"
```

Make executable: `chmod +x bin/build-zip.sh`

- [ ] **Step 9: Create `autoload.php`**

```php
<?php
/**
 * PSR-4 style autoloader for Remote Media Source.
 *
 * @package RemoteMediaSource
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	function ( string $class_name ): void {
		if ( strpos( $class_name, 'RemoteMediaSource\\' ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( 'RemoteMediaSource\\' ) );
		$file      = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);
```

- [ ] **Step 10: Create `tests/bootstrap.php`**

```php
<?php
require_once __DIR__ . '/Unit/bootstrap.php';
```

- [ ] **Step 11: Create `tests/Unit/bootstrap.php`**

```php
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
		public array $data;

		public function __construct( string $code = '', string $message = '', array $data = array() ) {
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
```

- [ ] **Step 12: Install dependencies and wire hooks**

```bash
composer install
composer install-hooks
```

Expected: vendors installed, `.githooks` wired via `git config core.hooksPath`.

- [ ] **Step 13: Commit**

```bash
git add composer.json phpcs.xml.dist phpunit.xml.dist .editorconfig .distignore \
        .githooks/pre-commit .githooks/commit-msg bin/build-zip.sh \
        autoload.php tests/bootstrap.php tests/Unit/bootstrap.php
git commit -m "chore: add tooling scaffold — composer, phpcs, phpunit, hooks, build-zip"
```

---

## Task 2: Plugin Main File + Core Bootstrap

**Files:**
- Create: `remote-media-source.php`
- Create: `gpl-2.0.txt`
- Create: `src/Core/Plugin.php` (empty service lists for now)
- Create: `src/Core/Activator.php`

- [ ] **Step 1: Create `remote-media-source.php`**

```php
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

$rms_data = get_file_data(
	__FILE__,
	array(
		'Version'     => 'Version',
		'Text Domain' => 'Text Domain',
	),
	'plugin'
);

define( 'RMS_VERSION', $rms_data['Version'] );
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
```

- [ ] **Step 2: Download `gpl-2.0.txt`**

```bash
curl -sS https://www.gnu.org/licenses/gpl-2.0.txt -o gpl-2.0.txt
```

- [ ] **Step 3: Create `src/Core/Plugin.php`** (empty service lists — filled in Task 9)

```php
<?php
/**
 * Plugin service registry.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstrap and register all plugin services.
 */
class Plugin {

	/**
	 * Services registered on every request.
	 *
	 * Each class must expose a static register() method.
	 *
	 * @var array<class-string>
	 */
	private array $services = array();

	/**
	 * Admin-only services: [hook, [class, method]] pairs.
	 *
	 * @var array<array{0: string, 1: array{0: class-string, 1: string}}>
	 */
	private array $admin_services = array();

	/**
	 * Hook into WordPress lifecycle.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Register all services.
	 */
	public function init(): void {
		if ( is_admin() ) {
			foreach ( $this->admin_services as $service ) {
				[ $hook, $callback ] = $service;
				add_action( $hook, $callback );
			}
		}

		foreach ( $this->services as $service ) {
			$service::register();
		}
	}
}
```

- [ ] **Step 4: Create `src/Core/Activator.php`**

```php
<?php
/**
 * Plugin activation handler.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin activation: version gate + default options.
 */
class Activator {

	/**
	 * Activate the plugin.
	 */
	public static function activate(): void {
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( plugin_basename( RMS_PLUGIN_FILE ) );
			wp_die(
				esc_html__( 'Remote Media Source requires PHP 8.1 or higher.', 'remote-media-source' ),
				esc_html__( 'Plugin Activation Error', 'remote-media-source' ),
				array( 'back_link' => true )
			);
		}

		add_option( 'rms_role', '' );
		add_option( 'rms_upload_mode', 'local' );
	}
}
```

- [ ] **Step 5: Lint**

```bash
composer lint
```

Expected: 0 errors, 0 warnings.

- [ ] **Step 6: Commit**

```bash
git add remote-media-source.php gpl-2.0.txt src/Core/Plugin.php src/Core/Activator.php
git commit -m "feat: add plugin main file, autoloader, and core bootstrap"
```

---

## Task 3: Source/KeyManager (TDD)

**Files:**
- Create: `tests/Unit/Source/KeyManagerTest.php`
- Create: `src/Source/KeyManager.php`

- [ ] **Step 1: Create `tests/Unit/Source/KeyManagerTest.php`**

```php
<?php
/**
 * KeyManager unit tests.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Tests\Unit\Source;

use PHPUnit\Framework\TestCase;
use RemoteMediaSource\Source\KeyManager;

/**
 * @covers RemoteMediaSource\Source\KeyManager
 */
class KeyManagerTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['rms_test_options'] = array();
	}

	public function test_generate_returns_64_char_hex_string(): void {
		$key = KeyManager::generate();
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $key );
	}

	public function test_generate_stores_hash_in_options(): void {
		KeyManager::generate();
		$this->assertNotEmpty( $GLOBALS['rms_test_options']['rms_connection_key_hash'] );
	}

	public function test_generate_stores_8_char_prefix(): void {
		KeyManager::generate();
		$this->assertSame( 8, strlen( $GLOBALS['rms_test_options']['rms_connection_key_prefix'] ) );
	}

	public function test_generate_prefix_matches_start_of_key(): void {
		$key = KeyManager::generate();
		$this->assertSame( substr( $key, 0, 8 ), $GLOBALS['rms_test_options']['rms_connection_key_prefix'] );
	}

	public function test_verify_returns_true_for_valid_key(): void {
		$key = KeyManager::generate();
		$this->assertTrue( KeyManager::verify( $key ) );
	}

	public function test_verify_returns_false_for_wrong_key(): void {
		KeyManager::generate();
		$this->assertFalse( KeyManager::verify( str_repeat( 'a', 64 ) ) );
	}

	public function test_verify_returns_false_when_no_hash_stored(): void {
		$this->assertFalse( KeyManager::verify( str_repeat( 'a', 64 ) ) );
	}

	public function test_has_key_returns_false_when_no_key_generated(): void {
		$this->assertFalse( KeyManager::has_key() );
	}

	public function test_has_key_returns_true_after_generate(): void {
		KeyManager::generate();
		$this->assertTrue( KeyManager::has_key() );
	}

	public function test_get_prefix_returns_empty_string_before_generate(): void {
		$this->assertSame( '', KeyManager::get_prefix() );
	}

	public function test_get_prefix_returns_stored_prefix_after_generate(): void {
		KeyManager::generate();
		$this->assertSame( $GLOBALS['rms_test_options']['rms_connection_key_prefix'], KeyManager::get_prefix() );
	}

	public function test_rotate_invalidates_old_key(): void {
		$old = KeyManager::generate();
		KeyManager::rotate();
		$this->assertFalse( KeyManager::verify( $old ) );
	}

	public function test_rotate_new_key_verifies(): void {
		KeyManager::generate();
		$new = KeyManager::rotate();
		$this->assertTrue( KeyManager::verify( $new ) );
	}
}
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
composer test:unit:php -- --filter KeyManagerTest
```

Expected: `Error: Class "RemoteMediaSource\Source\KeyManager" not found`

- [ ] **Step 3: Create `src/Source/KeyManager.php`**

```php
<?php
/**
 * Connection key manager for the Source role.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Source;

defined( 'ABSPATH' ) || exit;

/**
 * Generates, stores (hashed), verifies, and rotates the connection key.
 *
 * The raw key is returned once on generate/rotate and never stored.
 * The hash (wp_hash) and prefix (first 8 chars) are stored in wp_options.
 */
class KeyManager {

	private const HASH_OPTION   = 'rms_connection_key_hash';
	private const PREFIX_OPTION = 'rms_connection_key_prefix';

	/**
	 * Generate a new connection key, store hash + prefix, return raw key.
	 *
	 * @return string 64-char hex key (shown once — not stored).
	 */
	public static function generate(): string {
		$key = wp_generate_password( 64, false );
		update_option( static::HASH_OPTION, wp_hash( $key ) );
		update_option( static::PREFIX_OPTION, substr( $key, 0, 8 ) );
		return $key;
	}

	/**
	 * Regenerate the key, invalidating the previous one.
	 *
	 * @return string New 64-char hex key.
	 */
	public static function rotate(): string {
		return static::generate();
	}

	/**
	 * Verify a supplied key against the stored hash.
	 *
	 * @param string $key Raw key to verify.
	 * @return bool True if valid.
	 */
	public static function verify( string $key ): bool {
		$stored = get_option( static::HASH_OPTION, '' );
		if ( ! $stored ) {
			return false;
		}
		return hash_equals( $stored, wp_hash( $key ) );
	}

	/**
	 * Whether a key has been generated for this site.
	 *
	 * @return bool
	 */
	public static function has_key(): bool {
		return (bool) get_option( static::HASH_OPTION, '' );
	}

	/**
	 * Return the stored 8-char prefix (safe to display in UI).
	 *
	 * @return string
	 */
	public static function get_prefix(): string {
		return (string) get_option( static::PREFIX_OPTION, '' );
	}
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
composer test:unit:php -- --filter KeyManagerTest
```

Expected: `OK (13 tests, 13 assertions)`

- [ ] **Step 5: Lint**

```bash
composer lint
```

Expected: 0 errors.

- [ ] **Step 6: Commit**

```bash
git add tests/Unit/Source/KeyManagerTest.php src/Source/KeyManager.php
git commit -m "feat(source): add KeyManager with generate, verify, rotate, TDD"
```

---

## Task 4: Source/RestEndpoint (TDD)

**Files:**
- Create: `tests/Unit/Source/RestEndpointTest.php`
- Create: `src/Source/RestEndpoint.php`

- [ ] **Step 1: Create `tests/Unit/Source/RestEndpointTest.php`**

```php
<?php
/**
 * RestEndpoint unit tests.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Tests\Unit\Source;

use PHPUnit\Framework\TestCase;
use RemoteMediaSource\Source\RestEndpoint;
use RemoteMediaSource\Source\KeyManager;

/**
 * @covers RemoteMediaSource\Source\RestEndpoint
 */
class RestEndpointTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['rms_test_options'] = array();
	}

	public function test_check_permission_returns_wp_error_when_no_auth_header(): void {
		KeyManager::generate();
		$request = new \WP_REST_Request( 'GET', '/rms/v1/verify' );

		$result = RestEndpoint::check_permission( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rms_unauthorized', $result->get_error_code() );
	}

	public function test_check_permission_returns_wp_error_for_wrong_scheme(): void {
		$key     = KeyManager::generate();
		$request = new \WP_REST_Request( 'GET', '/rms/v1/verify' );
		$request->set_header( 'authorization', 'Bearer ' . $key );

		$result = RestEndpoint::check_permission( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rms_unauthorized', $result->get_error_code() );
	}

	public function test_check_permission_returns_wp_error_for_invalid_key(): void {
		KeyManager::generate();
		$request = new \WP_REST_Request( 'GET', '/rms/v1/verify' );
		$request->set_header( 'authorization', 'RMS ' . str_repeat( 'b', 64 ) );

		$result = RestEndpoint::check_permission( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'rms_unauthorized', $result->get_error_code() );
	}

	public function test_check_permission_returns_true_for_valid_key(): void {
		$key     = KeyManager::generate();
		$request = new \WP_REST_Request( 'GET', '/rms/v1/verify' );
		$request->set_header( 'authorization', 'RMS ' . $key );

		$result = RestEndpoint::check_permission( $request );

		$this->assertTrue( $result );
	}

	public function test_handle_verify_returns_rest_response_with_verified_true(): void {
		$key     = KeyManager::generate();
		$request = new \WP_REST_Request( 'GET', '/rms/v1/verify' );
		$request->set_header( 'authorization', 'RMS ' . $key );

		$response = RestEndpoint::handle_verify( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertTrue( $response->data['verified'] );
		$this->assertSame( 200, $response->status );
	}

	public function test_handle_verify_response_includes_required_fields(): void {
		$key     = KeyManager::generate();
		$request = new \WP_REST_Request( 'GET', '/rms/v1/verify' );
		$request->set_header( 'authorization', 'RMS ' . $key );

		$response = RestEndpoint::handle_verify( $request );

		$this->assertArrayHasKey( 'site_name', $response->data );
		$this->assertArrayHasKey( 'wp_version', $response->data );
		$this->assertArrayHasKey( 'plugin_version', $response->data );
		$this->assertArrayHasKey( 'uploads_baseurl', $response->data );
	}
}
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
composer test:unit:php -- --filter RestEndpointTest
```

Expected: `Error: Class "RemoteMediaSource\Source\RestEndpoint" not found`

- [ ] **Step 3: Create `src/Source/RestEndpoint.php`**

```php
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
	 * @param \WP_REST_Request $request Incoming REST request.
	 * @return \WP_REST_Response
	 */
	public static function handle_verify( \WP_REST_Request $request ): \WP_REST_Response {
		$upload_dir = wp_upload_dir();

		return new \WP_REST_Response(
			array(
				'verified'        => true,
				'site_name'       => get_bloginfo( 'name' ),
				'wp_version'      => get_bloginfo( 'version' ),
				'plugin_version'  => RMS_VERSION,
				'uploads_baseurl' => $upload_dir['baseurl'],
			),
			200
		);
	}
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
composer test:unit:php -- --filter RestEndpointTest
```

Expected: `OK (7 tests, 10 assertions)`

- [ ] **Step 5: Lint**

```bash
composer lint
```

Expected: 0 errors.

- [ ] **Step 6: Commit**

```bash
git add tests/Unit/Source/RestEndpointTest.php src/Source/RestEndpoint.php
git commit -m "feat(source): add REST verify endpoint with key auth, TDD"
```

---

## Task 5: Consumer/UploadDir (TDD)

**Files:**
- Create: `tests/Unit/Consumer/UploadDirTest.php`
- Create: `src/Consumer/UploadDir.php`

- [ ] **Step 1: Create `tests/Unit/Consumer/UploadDirTest.php`**

```php
<?php
/**
 * UploadDir unit tests.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Tests\Unit\Consumer;

use PHPUnit\Framework\TestCase;
use RemoteMediaSource\Consumer\UploadDir;

/**
 * @covers RemoteMediaSource\Consumer\UploadDir
 */
class UploadDirTest extends TestCase {

	private array $base_dirs = array(
		'baseurl' => 'http://local.test/wp-content/uploads',
		'url'     => 'http://local.test/wp-content/uploads/2026/05',
		'basedir' => '/var/www/html/wp-content/uploads',
		'path'    => '/var/www/html/wp-content/uploads/2026/05',
	);

	protected function setUp(): void {
		$GLOBALS['rms_test_options'] = array();
	}

	public function test_filter_rewrites_baseurl_when_active(): void {
		$GLOBALS['rms_test_options']['rms_role']            = 'consumer';
		$GLOBALS['rms_test_options']['rms_remote_url']      = 'https://example.com';
		$GLOBALS['rms_test_options']['rms_last_connection'] = array( 'success' => true );

		$result = UploadDir::filter( $this->base_dirs );

		$this->assertSame( 'https://example.com/wp-content/uploads', $result['baseurl'] );
	}

	public function test_filter_rewrites_url_to_remote_with_date_path(): void {
		$GLOBALS['rms_test_options']['rms_role']            = 'consumer';
		$GLOBALS['rms_test_options']['rms_remote_url']      = 'https://example.com';
		$GLOBALS['rms_test_options']['rms_last_connection'] = array( 'success' => true );

		$result = UploadDir::filter( $this->base_dirs );

		$this->assertStringStartsWith( 'https://example.com/wp-content/uploads/', $result['url'] );
	}

	public function test_filter_strips_trailing_slash_from_remote_url(): void {
		$GLOBALS['rms_test_options']['rms_role']            = 'consumer';
		$GLOBALS['rms_test_options']['rms_remote_url']      = 'https://example.com/';
		$GLOBALS['rms_test_options']['rms_last_connection'] = array( 'success' => true );

		$result = UploadDir::filter( $this->base_dirs );

		$this->assertSame( 'https://example.com/wp-content/uploads', $result['baseurl'] );
	}

	public function test_filter_leaves_basedir_unchanged(): void {
		$GLOBALS['rms_test_options']['rms_role']            = 'consumer';
		$GLOBALS['rms_test_options']['rms_remote_url']      = 'https://example.com';
		$GLOBALS['rms_test_options']['rms_last_connection'] = array( 'success' => true );

		$result = UploadDir::filter( $this->base_dirs );

		$this->assertSame( '/var/www/html/wp-content/uploads', $result['basedir'] );
	}

	public function test_filter_is_noop_when_role_is_source(): void {
		$GLOBALS['rms_test_options']['rms_role'] = 'source';

		$result = UploadDir::filter( $this->base_dirs );

		$this->assertSame( $this->base_dirs, $result );
	}

	public function test_filter_is_noop_when_role_is_empty(): void {
		$GLOBALS['rms_test_options']['rms_role'] = '';

		$result = UploadDir::filter( $this->base_dirs );

		$this->assertSame( $this->base_dirs, $result );
	}

	public function test_filter_is_noop_when_remote_url_empty(): void {
		$GLOBALS['rms_test_options']['rms_role']            = 'consumer';
		$GLOBALS['rms_test_options']['rms_remote_url']      = '';
		$GLOBALS['rms_test_options']['rms_last_connection'] = array( 'success' => true );

		$result = UploadDir::filter( $this->base_dirs );

		$this->assertSame( $this->base_dirs, $result );
	}

	public function test_filter_is_noop_without_verified_connection(): void {
		$GLOBALS['rms_test_options']['rms_role']       = 'consumer';
		$GLOBALS['rms_test_options']['rms_remote_url'] = 'https://example.com';
		// rms_last_connection not set

		$result = UploadDir::filter( $this->base_dirs );

		$this->assertSame( $this->base_dirs, $result );
	}

	public function test_filter_is_noop_when_last_connection_failed(): void {
		$GLOBALS['rms_test_options']['rms_role']            = 'consumer';
		$GLOBALS['rms_test_options']['rms_remote_url']      = 'https://example.com';
		$GLOBALS['rms_test_options']['rms_last_connection'] = array( 'success' => false );

		$result = UploadDir::filter( $this->base_dirs );

		$this->assertSame( $this->base_dirs, $result );
	}

	public function test_is_active_returns_true_when_all_conditions_met(): void {
		$GLOBALS['rms_test_options']['rms_role']            = 'consumer';
		$GLOBALS['rms_test_options']['rms_remote_url']      = 'https://example.com';
		$GLOBALS['rms_test_options']['rms_last_connection'] = array( 'success' => true );

		$this->assertTrue( UploadDir::is_active() );
	}

	public function test_is_active_returns_false_for_source_role(): void {
		$GLOBALS['rms_test_options']['rms_role']            = 'source';
		$GLOBALS['rms_test_options']['rms_remote_url']      = 'https://example.com';
		$GLOBALS['rms_test_options']['rms_last_connection'] = array( 'success' => true );

		$this->assertFalse( UploadDir::is_active() );
	}
}
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
composer test:unit:php -- --filter UploadDirTest
```

Expected: `Error: Class "RemoteMediaSource\Consumer\UploadDir" not found`

- [ ] **Step 3: Create `src/Consumer/UploadDir.php`**

```php
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
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
composer test:unit:php -- --filter UploadDirTest
```

Expected: `OK (12 tests, 12 assertions)`

- [ ] **Step 5: Lint + Commit**

```bash
composer lint
git add tests/Unit/Consumer/UploadDirTest.php src/Consumer/UploadDir.php
git commit -m "feat(consumer): add UploadDir upload_dir filter with verified-connection gate, TDD"
```

---

## Task 6: Consumer/UploadMode (TDD)

**Files:**
- Create: `tests/Unit/Consumer/UploadModeTest.php`
- Create: `src/Consumer/UploadMode.php`

- [ ] **Step 1: Create `tests/Unit/Consumer/UploadModeTest.php`**

```php
<?php
/**
 * UploadMode unit tests.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Tests\Unit\Consumer;

use PHPUnit\Framework\TestCase;
use RemoteMediaSource\Consumer\UploadMode;

/**
 * @covers RemoteMediaSource\Consumer\UploadMode
 */
class UploadModeTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['rms_test_options'] = array();
	}

	public function test_get_mode_returns_local_when_nothing_stored(): void {
		$this->assertSame( 'local', UploadMode::get_mode() );
	}

	public function test_get_mode_returns_local_when_stored(): void {
		$GLOBALS['rms_test_options']['rms_upload_mode'] = 'local';
		$this->assertSame( 'local', UploadMode::get_mode() );
	}

	public function test_get_mode_returns_block_when_stored(): void {
		$GLOBALS['rms_test_options']['rms_upload_mode'] = 'block';
		$this->assertSame( 'block', UploadMode::get_mode() );
	}

	public function test_get_mode_falls_back_to_local_for_unknown_value(): void {
		$GLOBALS['rms_test_options']['rms_upload_mode'] = 'unknown_value';
		$this->assertSame( 'local', UploadMode::get_mode() );
	}

	public function test_filter_mimes_block_returns_empty_array(): void {
		$mimes  = array( 'image/jpeg' => 'jpg|jpeg', 'image/png' => 'png' );
		$result = UploadMode::filter_mimes_block( $mimes );
		$this->assertSame( array(), $result );
	}

	public function test_prefilter_block_adds_error_to_file(): void {
		$file = array(
			'name'     => 'photo.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => '/tmp/phpXXXXXX',
			'error'    => 0,
			'size'     => 1024,
		);

		$result = UploadMode::prefilter_block( $file );

		$this->assertNotEmpty( $result['error'] );
		$this->assertIsString( $result['error'] );
	}

	public function test_prefilter_block_preserves_other_file_fields(): void {
		$file = array(
			'name'     => 'photo.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => '/tmp/phpXXXXXX',
			'error'    => 0,
			'size'     => 1024,
		);

		$result = UploadMode::prefilter_block( $file );

		$this->assertSame( 'photo.jpg', $result['name'] );
		$this->assertSame( 1024, $result['size'] );
	}
}
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
composer test:unit:php -- --filter UploadModeTest
```

Expected: `Error: Class "RemoteMediaSource\Consumer\UploadMode" not found`

- [ ] **Step 3: Create `src/Consumer/UploadMode.php`**

```php
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
	 * @param array $mimes Allowed MIME types.
	 * @return array Empty array — no type is permitted.
	 */
	public static function filter_mimes_block( array $mimes ): array {
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
```

- [ ] **Step 4: Run ALL unit tests — expect PASS**

```bash
composer test:unit:php
```

Expected: `OK (32 tests, 37 assertions)` (exact counts may vary slightly)

- [ ] **Step 5: Lint + Commit**

```bash
composer lint
git add tests/Unit/Consumer/UploadModeTest.php src/Consumer/UploadMode.php
git commit -m "feat(consumer): add UploadMode enforcement — local and block modes, TDD"
```

---

## Task 7: Admin/SettingsPage

**Files:**
- Create: `src/Admin/SettingsPage.php`

- [ ] **Step 1: Create `src/Admin/SettingsPage.php`**

```php
<?php
/**
 * Plugin settings page.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Admin;

defined( 'ABSPATH' ) || exit;

use RemoteMediaSource\Source\KeyManager;

/**
 * Registers and renders the Settings > Remote Media Source admin page.
 * Handles form saves and all admin-side AJAX actions.
 */
class SettingsPage {

	/**
	 * Register the options page (callback for admin_menu hook).
	 */
	public static function register(): void {
		add_options_page(
			esc_html__( 'Remote Media Source', 'remote-media-source' ),
			esc_html__( 'Remote Media Source', 'remote-media-source' ),
			'manage_options',
			'remote-media-source',
			array( static::class, 'render' )
		);
	}

	/**
	 * Render the full settings page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$role = get_option( 'rms_role', '' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Remote Media Source', 'remote-media-source' ); ?></h1>

			<?php if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'remote-media-source' ); ?></p>
				</div>
			<?php endif; ?>

			<?php static::render_key_modal(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'rms_save_settings', 'rms_nonce' ); ?>
				<input type="hidden" name="action" value="rms_save_settings">

				<?php static::render_role_section( $role ); ?>
				<?php static::render_source_section( $role ); ?>
				<?php static::render_consumer_section( $role ); ?>

				<?php submit_button( __( 'Save Settings', 'remote-media-source' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the role selector.
	 *
	 * @param string $role Current role value.
	 */
	private static function render_role_section( string $role ): void {
		?>
		<h2><?php esc_html_e( 'Role', 'remote-media-source' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="rms_role"><?php esc_html_e( 'This site is a', 'remote-media-source' ); ?></label>
				</th>
				<td>
					<select name="rms_role" id="rms_role">
						<option value="" <?php selected( $role, '' ); ?>><?php esc_html_e( '— Disabled —', 'remote-media-source' ); ?></option>
						<option value="source" <?php selected( $role, 'source' ); ?>><?php esc_html_e( 'Source (this site is the media truth)', 'remote-media-source' ); ?></option>
						<option value="consumer" <?php selected( $role, 'consumer' ); ?>><?php esc_html_e( 'Consumer (serve media from remote)', 'remote-media-source' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Set to Source on your production site. Set to Consumer on local/dev/staging.', 'remote-media-source' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the Source role configuration section.
	 *
	 * @param string $role Current role value.
	 */
	private static function render_source_section( string $role ): void {
		$display = 'source' === $role ? '' : ' style="display:none"';
		?>
		<div id="rms-source-settings"<?php echo $display; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<h2><?php esc_html_e( 'Source Settings', 'remote-media-source' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Connection Key', 'remote-media-source' ); ?></th>
					<td>
						<?php if ( KeyManager::has_key() ) : ?>
							<code><?php echo esc_html( KeyManager::get_prefix() ); ?>••••••••••••••••••••••••••••••••••••••••••••••••••••••••</code>
							<p>
								<button type="button" class="button" id="rms-regenerate-key">
									<?php esc_html_e( 'Regenerate Key', 'remote-media-source' ); ?>
								</button>
							</p>
							<p class="description">
								<?php esc_html_e( 'Regenerating will invalidate the current key and disconnect all consumers.', 'remote-media-source' ); ?>
							</p>
						<?php else : ?>
							<button type="button" class="button button-primary" id="rms-generate-key">
								<?php esc_html_e( 'Generate Connection Key', 'remote-media-source' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Generate a key to share with consumer environments.', 'remote-media-source' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Render the Consumer role configuration section.
	 *
	 * @param string $role Current role value.
	 */
	private static function render_consumer_section( string $role ): void {
		$display     = 'consumer' === $role ? '' : ' style="display:none"';
		$remote_url  = (string) get_option( 'rms_remote_url', '' );
		$upload_mode = (string) get_option( 'rms_upload_mode', 'local' );
		$last        = get_option( 'rms_last_connection', array() );
		?>
		<div id="rms-consumer-settings"<?php echo $display; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<h2><?php esc_html_e( 'Consumer Settings', 'remote-media-source' ); ?></h2>

			<?php if ( ! empty( $last['success'] ) ) : ?>
				<div class="notice notice-success inline">
					<p>
						<strong><?php esc_html_e( 'Consumer active.', 'remote-media-source' ); ?></strong>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: human-readable time ago */
								__( 'Last verified %s ago.', 'remote-media-source' ),
								human_time_diff( (int) ( $last['timestamp'] ?? 0 ) )
							)
						);
						?>
					</p>
				</div>
			<?php elseif ( 'consumer' === $role ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'Consumer not yet verified. Save settings and click "Test Connection".', 'remote-media-source' ); ?></p>
				</div>
			<?php endif; ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="rms_remote_url"><?php esc_html_e( 'Remote Source URL', 'remote-media-source' ); ?></label>
					</th>
					<td>
						<input type="url" id="rms_remote_url" name="rms_remote_url" class="regular-text"
							value="<?php echo esc_url( $remote_url ); ?>">
						<p class="description">
							<?php esc_html_e( 'Base URL of the source WordPress site (e.g. https://example.com).', 'remote-media-source' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="rms_remote_key"><?php esc_html_e( 'Connection Key', 'remote-media-source' ); ?></label>
					</th>
					<td>
						<input type="password" id="rms_remote_key" name="rms_remote_key" class="regular-text"
							autocomplete="off"
							value="<?php echo esc_attr( (string) get_option( 'rms_remote_key', '' ) ); ?>">
						<button type="button" class="button" id="rms-reveal-key">
							<?php esc_html_e( 'Reveal', 'remote-media-source' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Connection key copied from the source site. Stored in the database; restrict wp-admin access accordingly.', 'remote-media-source' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Upload Mode', 'remote-media-source' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="radio" name="rms_upload_mode" value="local"
									<?php checked( $upload_mode, 'local' ); ?>>
								<?php esc_html_e( 'Local only (default) — uploads land in the local filesystem', 'remote-media-source' ); ?>
							</label><br>
							<label>
								<input type="radio" name="rms_upload_mode" value="block"
									<?php checked( $upload_mode, 'block' ); ?>>
								<?php esc_html_e( 'Block uploads — prevent any file uploads on this environment', 'remote-media-source' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Connection', 'remote-media-source' ); ?></th>
					<td>
						<button type="button" class="button" id="rms-test-connection"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'rms_test_connection' ) ); ?>">
							<?php esc_html_e( 'Test Connection', 'remote-media-source' ); ?>
						</button>
						<span id="rms-test-result" style="margin-left:10px;"></span>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Render the one-time key display modal (hidden until JS shows it).
	 */
	private static function render_key_modal(): void {
		?>
		<div id="rms-key-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:99999;">
			<div style="background:#fff;max-width:600px;margin:80px auto;padding:30px;border-radius:4px;box-shadow:0 4px 20px rgba(0,0,0,.3);">
				<h2><?php esc_html_e( 'Connection Key', 'remote-media-source' ); ?></h2>
				<p>
					<strong style="color:#d63638;">
						<?php esc_html_e( 'Copy this key now. It will not be shown again.', 'remote-media-source' ); ?>
					</strong>
				</p>
				<textarea id="rms-key-modal-value" rows="3"
					style="width:100%;font-family:monospace;font-size:13px;resize:none;"
					readonly></textarea>
				<p style="margin-top:15px;">
					<button type="button" class="button button-primary" id="rms-key-modal-copy">
						<?php esc_html_e( 'Copy Key', 'remote-media-source' ); ?>
					</button>
					<button type="button" class="button" id="rms-key-modal-dismiss" style="margin-left:10px;">
						<?php esc_html_e( "I've copied this key", 'remote-media-source' ); ?>
					</button>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle the settings form save (admin_post_rms_save_settings).
	 */
	public static function handle_save(): void {
		check_admin_referer( 'rms_save_settings', 'rms_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'remote-media-source' ) );
		}

		$new_role = sanitize_text_field( wp_unslash( $_POST['rms_role'] ?? '' ) );
		if ( ! in_array( $new_role, array( '', 'source', 'consumer' ), true ) ) {
			$new_role = '';
		}

		$old_role = get_option( 'rms_role', '' );

		if ( $new_role !== $old_role ) {
			if ( 'source' === $old_role ) {
				delete_option( 'rms_connection_key_hash' );
				delete_option( 'rms_connection_key_prefix' );
			}
			if ( 'consumer' === $old_role ) {
				delete_option( 'rms_remote_url' );
				delete_option( 'rms_remote_key' );
				delete_option( 'rms_upload_mode' );
				delete_option( 'rms_last_connection' );
			}
		}

		update_option( 'rms_role', $new_role );

		if ( 'consumer' === $new_role ) {
			$new_url = esc_url_raw( wp_unslash( $_POST['rms_remote_url'] ?? '' ) );
			$old_url = (string) get_option( 'rms_remote_url', '' );

			if ( $new_url !== $old_url ) {
				delete_option( 'rms_last_connection' );
			}

			update_option( 'rms_remote_url', $new_url );

			$key = sanitize_text_field( wp_unslash( $_POST['rms_remote_key'] ?? '' ) );
			if ( $key ) {
				update_option( 'rms_remote_key', $key );
			}

			$mode = sanitize_text_field( wp_unslash( $_POST['rms_upload_mode'] ?? 'local' ) );
			update_option( 'rms_upload_mode', in_array( $mode, array( 'local', 'block' ), true ) ? $mode : 'local' );
		}

		wp_redirect( admin_url( 'options-general.php?page=remote-media-source&saved=1' ) );
		exit;
	}

	/**
	 * AJAX: generate or regenerate the connection key.
	 *
	 * Returns the raw key once. Never stored after this response.
	 */
	public static function ajax_generate_key(): void {
		check_ajax_referer( 'rms_generate_key', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized.', 'remote-media-source' ) ) );
		}

		$key = KeyManager::generate();

		wp_send_json_success(
			array(
				'key'    => $key,
				'prefix' => substr( $key, 0, 8 ),
			)
		);
	}

	/**
	 * AJAX: test the consumer connection to the remote source.
	 */
	public static function ajax_test_connection(): void {
		check_ajax_referer( 'rms_test_connection', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized.', 'remote-media-source' ) ) );
		}

		$url = (string) get_option( 'rms_remote_url', '' );
		$key = (string) get_option( 'rms_remote_key', '' );

		if ( ! $url ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Remote Source URL is not configured.', 'remote-media-source' ) ) );
		}

		// Step 1: basic reachability.
		$head = wp_remote_head( esc_url_raw( $url ) );
		if ( is_wp_error( $head ) ) {
			wp_send_json_error(
				array( 'message' => sprintf(
					/* translators: %s: error message */
					esc_html__( 'Remote URL unreachable: %s', 'remote-media-source' ),
					$head->get_error_message()
				) )
			);
		}

		$code = wp_remote_retrieve_response_code( $head );
		if ( $code >= 400 ) {
			wp_send_json_error(
				array( 'message' => sprintf(
					/* translators: %d: HTTP status code */
					esc_html__( 'Remote returned HTTP %d.', 'remote-media-source' ),
					(int) $code
				) )
			);
		}

		// Step 2: REST verify handshake.
		$response = wp_remote_get(
			esc_url_raw( rtrim( $url, '/' ) . '/wp-json/rms/v1/verify' ),
			array(
				'headers' => array( 'Authorization' => 'RMS ' . $key ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array( 'message' => sprintf(
					/* translators: %s: error message */
					esc_html__( 'Verification request failed: %s', 'remote-media-source' ),
					$response->get_error_message()
				) )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['verified'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Remote did not verify. Check your connection key.', 'remote-media-source' ) ) );
		}

		$status = array(
			'timestamp' => time(),
			'success'   => true,
			'message'   => sprintf(
				/* translators: %s: remote site name */
				esc_html__( 'Connected to %s', 'remote-media-source' ),
				sanitize_text_field( $body['site_name'] ?? 'remote site' )
			),
		);

		update_option( 'rms_last_connection', $status );

		wp_send_json_success( $status );
	}
}
```

- [ ] **Step 2: Lint**

```bash
composer lint
```

Expected: 0 errors.

- [ ] **Step 3: Commit**

```bash
git add src/Admin/SettingsPage.php
git commit -m "feat(admin): add SettingsPage — role selector, source/consumer config, AJAX handlers"
```

---

## Task 8: Admin/PluginLinks + Core/Assets + assets/admin.js

**Files:**
- Create: `src/Admin/PluginLinks.php`
- Create: `src/Core/Assets.php`
- Create: `assets/admin.js`

- [ ] **Step 1: Create `src/Admin/PluginLinks.php`**

```php
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
	 * @param array $links Existing action links.
	 * @return array Modified action links.
	 */
	public static function add_settings_link( array $links ): array {
		$url  = admin_url( 'options-general.php?page=remote-media-source' );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'remote-media-source' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}
}
```

- [ ] **Step 2: Create `src/Core/Assets.php`**

```php
<?php
/**
 * Admin asset enqueuing.
 *
 * @package RemoteMediaSource
 */

namespace RemoteMediaSource\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues admin JS on the Remote Media Source settings page only.
 */
class Assets {

	/**
	 * Enqueue scripts if on the plugin settings page.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public static function register( string $hook_suffix ): void {
		if ( 'settings_page_remote-media-source' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'rms-admin',
			RMS_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery' ),
			RMS_VERSION,
			true
		);

		wp_localize_script(
			'rms-admin',
			'rmsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonces'  => array(
					'generateKey'    => wp_create_nonce( 'rms_generate_key' ),
					'testConnection' => wp_create_nonce( 'rms_test_connection' ),
				),
				'i18n'    => array(
					'copySuccess'       => __( 'Copied!', 'remote-media-source' ),
					'copyFail'          => __( 'Copy failed — please select and copy manually.', 'remote-media-source' ),
					'regenerateConfirm' => __( 'Regenerating will invalidate the current key and disconnect all consumers. Continue?', 'remote-media-source' ),
					'testing'           => __( 'Testing…', 'remote-media-source' ),
					'testConnection'    => __( 'Test Connection', 'remote-media-source' ),
					'hide'              => __( 'Hide', 'remote-media-source' ),
					'reveal'            => __( 'Reveal', 'remote-media-source' ),
				),
			)
		);
	}
}
```

- [ ] **Step 3: Create `assets/admin.js`**

```js
/* global rmsAdmin, jQuery */
( function ( $ ) {
	'use strict';

	// Role selector: toggle source / consumer sections.
	$( '#rms_role' ).on( 'change', function () {
		var role = $( this ).val();
		$( '#rms-source-settings' ).toggle( role === 'source' );
		$( '#rms-consumer-settings' ).toggle( role === 'consumer' );
	} );

	// Generate key (first time) or Regenerate key.
	$( document ).on( 'click', '#rms-generate-key, #rms-regenerate-key', function ( e ) {
		e.preventDefault();
		if ( $( this ).is( '#rms-regenerate-key' ) ) {
			if ( ! window.confirm( rmsAdmin.i18n.regenerateConfirm ) ) {
				return;
			}
		}
		$.post(
			rmsAdmin.ajaxUrl,
			{
				action: 'rms_generate_key',
				nonce: rmsAdmin.nonces.generateKey,
			},
			function ( response ) {
				if ( ! response.success ) {
					window.alert( response.data.message );
					return;
				}
				$( '#rms-key-modal-value' ).val( response.data.key );
				$( '#rms-key-modal' ).show();
			}
		);
	} );

	// Copy key from modal.
	$( '#rms-key-modal-copy' ).on( 'click', function () {
		var $btn = $( this );
		var key  = $( '#rms-key-modal-value' ).val();
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( key ).then( function () {
				$btn.text( rmsAdmin.i18n.copySuccess );
				setTimeout( function () {
					$btn.text( 'Copy Key' );
				}, 2000 );
			} );
		} else {
			window.prompt( rmsAdmin.i18n.copyFail, key );
		}
	} );

	// Dismiss modal — reload so the prefix display updates.
	$( '#rms-key-modal-dismiss' ).on( 'click', function () {
		$( '#rms-key-modal' ).hide();
		window.location.reload();
	} );

	// Reveal / hide the consumer connection key field.
	$( '#rms-reveal-key' ).on( 'click', function () {
		var $field  = $( '#rms_remote_key' );
		var isPass  = $field.attr( 'type' ) === 'password';
		$field.attr( 'type', isPass ? 'text' : 'password' );
		$( this ).text( isPass ? rmsAdmin.i18n.hide : rmsAdmin.i18n.reveal );
	} );

	// Test connection.
	$( '#rms-test-connection' ).on( 'click', function () {
		var $btn    = $( this );
		var $result = $( '#rms-test-result' );

		$btn.prop( 'disabled', true ).text( rmsAdmin.i18n.testing );
		$result.text( '' ).css( 'color', '' );

		$.post(
			rmsAdmin.ajaxUrl,
			{
				action: 'rms_test_connection',
				nonce: rmsAdmin.nonces.testConnection,
			},
			function ( response ) {
				$btn.prop( 'disabled', false ).text( rmsAdmin.i18n.testConnection );
				if ( response.success ) {
					$result.css( 'color', '#00a32a' ).text( '✓ ' + response.data.message );
				} else {
					$result.css( 'color', '#d63638' ).text( '✗ ' + response.data.message );
				}
			}
		).fail( function () {
			$btn.prop( 'disabled', false ).text( rmsAdmin.i18n.testConnection );
			$result.css( 'color', '#d63638' ).text( '✗ Request failed.' );
		} );
	} );

} )( jQuery );
```

- [ ] **Step 4: Lint**

```bash
composer lint
```

Expected: 0 errors.

- [ ] **Step 5: Commit**

```bash
git add src/Admin/PluginLinks.php src/Core/Assets.php assets/admin.js
git commit -m "feat(admin): add PluginLinks, Assets enqueue, and admin.js interactions"
```

---

## Task 9: Wire All Services in Plugin.php

**Files:**
- Modify: `src/Core/Plugin.php`

- [ ] **Step 1: Update `src/Core/Plugin.php`** — replace the empty service list stubs with the full wired lists

Replace the `$services` and `$admin_services` property declarations and `init()` with:

```php
	/**
	 * Services registered on every request.
	 *
	 * @var array<class-string>
	 */
	private array $services = array(
		\RemoteMediaSource\Source\RestEndpoint::class,
		\RemoteMediaSource\Consumer\UploadDir::class,
		\RemoteMediaSource\Consumer\UploadMode::class,
	);

	/**
	 * Admin-only services: [hook, callable] pairs.
	 *
	 * @var array<array{0: string, 1: callable}>
	 */
	private array $admin_services = array(
		array( 'admin_menu',                   array( \RemoteMediaSource\Admin\SettingsPage::class, 'register' ) ),
		array( 'admin_enqueue_scripts',        array( \RemoteMediaSource\Core\Assets::class, 'register' ) ),
		array( 'wp_ajax_rms_generate_key',     array( \RemoteMediaSource\Admin\SettingsPage::class, 'ajax_generate_key' ) ),
		array( 'wp_ajax_rms_test_connection',  array( \RemoteMediaSource\Admin\SettingsPage::class, 'ajax_test_connection' ) ),
		array( 'admin_post_rms_save_settings', array( \RemoteMediaSource\Admin\SettingsPage::class, 'handle_save' ) ),
	);
```

- [ ] **Step 2: Run full test suite**

```bash
composer test:unit:php
```

Expected: all tests pass.

- [ ] **Step 3: Lint**

```bash
composer lint
```

Expected: 0 errors.

- [ ] **Step 4: Commit**

```bash
git add src/Core/Plugin.php
git commit -m "feat(core): wire all services into Plugin registry"
```

---

## Task 10: readme.txt + README.md

**Files:**
- Create: `readme.txt`
- Create: `README.md`

- [ ] **Step 1: Create `readme.txt`**

```
=== Remote Media Source ===
Contributors: 9ete
Tags: media, uploads, staging, development, cdn
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Serve media from a remote WordPress site on local, dev, and staging environments.

== Description ==

Remote Media Source solves a common multi-environment problem: non-production sites (local, dev, staging) duplicating gigabytes of production media, causing storage waste and sync headaches.

Install the plugin on every environment. Set your production site to **Source** and your local/dev/staging sites to **Consumer**. Consumers rewrite all upload directory URLs to point at the production source — media loads from prod without copying a single file.

**Features:**

* Two-role model: Source (generates a connection key) and Consumer (rewrites media URLs)
* Authenticated connection verification — consumers must verify against the source before the URL rewrite activates
* Configurable upload policy per consumer: local-only (default) or block uploads entirely
* One-click "Test Connection" that confirms the remote is reachable and running this plugin
* Connection key shown once on generation and never stored in plaintext on the source

**Use case example:**

A production site has 4 GB of product images. Your local dev environment points its upload directory at production. Developers see real product images without syncing anything. Staging does the same. When you need to add new images, you do it on production (the source), and all consumers pick them up immediately.

== Installation ==

1. Upload the `remote-media-source` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Settings > Remote Media Source** and choose a role for this site.

**Setting up a source/consumer pair:**

1. On your **production** site: set role to **Source**, click **Generate Connection Key**, and copy the key.
2. On your **local/dev/staging** site: set role to **Consumer**, enter the production URL and paste the key, then click **Test Connection**.
3. Once the connection is verified, all upload URLs on the consumer are rewritten to the source automatically.

== Frequently Asked Questions ==

= Does this copy files from production to local? =

No. The plugin rewrites URLs only — your browser fetches media directly from the production server. No files are transferred or stored locally.

= What happens if the remote source is unreachable? =

The URL rewrite only activates after a successful test connection. If the remote goes offline after activation, media URLs will return 404s until the remote is restored. The plugin does not fall back to local files.

= Is the connection key stored securely? =

On the **source** site, only a hash of the key is stored — the raw key is shown once and never saved. On the **consumer** site, the raw key is stored in `wp_options` to authenticate outgoing requests. Access to `wp-admin` and the database should be restricted on consumer environments.

= Can I use this on a site that isn't WordPress? =

No. Both the source and consumer must be WordPress 6.0+ sites with this plugin installed.

== External Services ==

This plugin communicates with a remote WordPress site configured by the site administrator.

**When:** The consumer site makes HTTP requests to the configured Remote Source URL when the administrator clicks "Test Connection" in the settings page.

**What is sent:** An `Authorization: RMS <key>` header containing the connection key stored in the consumer's database.

**What is received:** Site metadata from the source (site name, WP version, plugin version, uploads base URL).

**Privacy:** The remote site's privacy policy applies to data transmitted during connection verification. The plugin author (LowerMedia LLC) does not operate or have access to any remote site configured by users of this plugin.

== Changelog ==

= 1.0.0 =
* Initial release.
* Source role: connection key generation and REST verification endpoint.
* Consumer role: upload_dir URL rewrite, local and block upload modes, test connection.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
```

- [ ] **Step 2: Create `README.md`**

```markdown
# Remote Media Source

A WordPress plugin that lets local, dev, and staging environments serve media from a remote WordPress source — no file syncing required.

## How it works

Install the plugin on every environment. Assign roles in **Settings › Remote Media Source**:

- **Source** (production): generates a connection key, exposes an authenticated verification endpoint.
- **Consumer** (local/dev/staging): stores the remote URL + key, rewrites all upload URLs to the source, and enforces a configurable upload policy.

Once a consumer verifies its connection, all media URLs point to the source. Browsers fetch files directly from production — nothing is stored locally.

## Requirements

- WordPress 6.0+
- PHP 8.1+
- Both source and consumer must have this plugin installed

## Setup

1. **Source site (production):** Settings › Remote Media Source → Role: Source → Generate Connection Key → copy the key.
2. **Consumer site (local/dev/stg):** Settings › Remote Media Source → Role: Consumer → enter source URL + key → Test Connection.

## Development

```bash
git clone <repo>
cd remote-media-source
composer install
composer install-hooks
```

| Command | Purpose |
|---|---|
| `composer lint` | PHPCS check |
| `composer lint:fix` | PHPCBF auto-fix |
| `composer test:unit:php` | PHPUnit unit tests |
| `composer build-zip` | Build distribution zip |
| `composer plugin-check` | WP.org Plugin Check (requires WP-CLI + WP env) |

## License

GPLv2 or later — see [gpl-2.0.txt](gpl-2.0.txt).
```

- [ ] **Step 3: Commit**

```bash
git add readme.txt README.md
git commit -m "docs: add readme.txt (WP.org) and README.md with external services disclosure"
```

---

## Task 11: Final QA

- [ ] **Step 1: Run full lint + tests**

```bash
composer lint && composer test:unit:php
```

Expected: 0 lint errors, all tests pass.

- [ ] **Step 2: Build the distribution zip**

```bash
composer build-zip
```

Expected output:
```
Created distribution package: .../zips/remote-media-source-1.0.0.zip
Created archive package: .../zips/remote-media-source-1.0.0-archive.zip
Created copy without version: .../remote-media-source.zip
```

- [ ] **Step 3: Run WP.org Plugin Check** (requires a running WP environment with WP-CLI)

```bash
composer plugin-check
```

Expected: 0 errors. Fix any warnings flagged by Plugin Check before continuing.

Common Plugin Check issues to pre-empt:
- Missing `load_plugin_textdomain()` call — add to `Activator::activate()` or a dedicated init hook in `Plugin::init()`.
- Direct output without proper escaping — review any `echo` calls outside of `esc_*` wrappers.
- `$_GET`/`$_POST` access without `sanitize_*` — verify all form field reads in `handle_save()` and AJAX handlers are sanitized.

- [ ] **Step 4: Add `load_plugin_textdomain` if Plugin Check flags it**

In `src/Core/Plugin.php`, add to the `init()` method:

```php
load_plugin_textdomain( 'remote-media-source', false, dirname( plugin_basename( RMS_PLUGIN_FILE ) ) . '/languages' );
```

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "chore: final QA — plugin-check passing, build verified"
```

---

## Spec Coverage Verification

| Spec requirement | Task |
|---|---|
| Source role: key generation | Task 3 |
| Source role: REST verify endpoint | Task 4 |
| Consumer role: upload_dir rewrite | Task 5 |
| Consumer upload modes: local + block | Task 6 |
| Settings page: role selector, source/consumer tabs | Task 7 |
| One-time key modal (never stored) | Task 7, 8 |
| Test connection: HEAD + REST handshake | Task 7 |
| Rewrite inactive until verified connection | Task 5 (is_active gate) |
| Admin JS: role toggle, key modal, reveal, test | Task 8 |
| WPCS/PHPCS + PHPCompatibilityWP tooling | Task 1 |
| Pre-commit lint:fix hook | Task 1 |
| Commit-msg CC validation + lint + tests | Task 1 |
| build-zip composer script | Task 1 |
| .distignore | Task 1 |
| PHPUnit 10.x TDD suite | Tasks 3–6 |
| readme.txt with External Services section | Task 10 |
| WP.org Plugin Check passing | Task 11 |
| `load_plugin_textdomain` | Task 11 |
