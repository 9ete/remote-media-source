# Remote Media Source — Plugin Design Spec

**Date:** 2026-05-20  
**Ticket:** UR-48  
**Author:** Pete Lower  
**Status:** Approved for implementation planning

---

## Overview

A distributable, WP.org-ready WordPress plugin that allows non-production environments (local, dev, staging) to serve media from a designated remote WordPress site instead of maintaining their own uploads directory.

The plugin installs on all environments. Each site picks one **role**:

- **Source** — the media truth (typically production). Generates a connection key and exposes an authenticated REST endpoint.
- **Consumer** — defers to a remote source (typically local/dev/stg). Rewrites all upload URLs to the remote and enforces a configurable upload policy.

---

## Scope: v1.0

| Feature | Included |
|---|---|
| Source role: connection key generation + settings UI | Yes |
| Consumer role: `upload_dir` URL rewrite | Yes |
| Consumer upload modes: local-only + block | Yes |
| Test connection button (HTTP HEAD + REST handshake) | Yes |
| WP.org-ready scaffolding (PHPCS, PHPUnit, build zip, hooks) | Yes |
| External services disclosure in readme.txt | Yes |
| Live media library proxy (classic + Gutenberg) | v1.1 |
| Shadow attachment creation on media selection | v1.1 |
| Upload mode: proxy to remote | v1.1 |

---

## Plugin Metadata

```
Plugin Name:  Remote Media Source
Plugin URI:   https://lowermedia.net/plugins/remote-media-source
Description:  Serve media from a remote WordPress site on local/dev/staging environments.
Version:      1.0.0
Author:       9ete
Author URI:   https://lowermedia.net
Text Domain:  remote-media-source
Requires PHP: 8.1
Requires at least: 6.0
License:      GPLv2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
```

---

## File Structure

```
remote-media-source/
├── remote-media-source.php       # Plugin header, constants, bootstrap
├── autoload.php                  # Namespace → src/ resolver (no Composer autoload)
├── README.md                     # GitHub README
├── readme.txt                    # WP.org listing (all required sections)
├── gpl-2.0.txt
├── composer.json
├── phpcs.xml.dist
├── phpunit.xml.dist
├── .distignore
├── .editorconfig
├── .gitignore
├── .githooks/
│   ├── pre-commit                # composer lint:fix
│   └── commit-msg                # Conventional Commits validation + lint + unit tests
├── bin/
│   └── build-zip.sh              # Versioned + WP.org zip builder
├── src/
│   ├── Core/
│   │   ├── Plugin.php            # Service registration (services + admin_services pattern)
│   │   ├── Assets.php            # Admin JS/CSS enqueue
│   │   └── Activator.php         # Activation hook, capability check
│   ├── Admin/
│   │   ├── SettingsPage.php      # Settings UI: role selector + per-role config
│   │   └── PluginLinks.php       # "Settings" action link in plugins list
│   ├── Source/
│   │   ├── KeyManager.php        # Generate, store, hash, rotate connection key
│   │   └── RestEndpoint.php      # Authenticated REST endpoint for connection verification
│   └── Consumer/
│       ├── UploadDir.php         # upload_dir filter → remote baseurl
│       └── UploadMode.php        # Enforce local-only or block upload modes
├── assets/
│   └── admin.js                  # Test connection AJAX, UI feedback
└── tests/
    ├── bootstrap.php
    └── Unit/
        ├── bootstrap.php
        ├── Source/
        │   └── KeyManagerTest.php
        └── Consumer/
            ├── UploadDirTest.php
            └── UploadModeTest.php
```

---

## Constants

Defined in `remote-media-source.php`:

| Constant | Value |
|---|---|
| `RMS_VERSION` | Plugin version string from header |
| `RMS_PLUGIN_FILE` | `__FILE__` |
| `RMS_PLUGIN_DIR` | `plugin_dir_path( __FILE__ )` |
| `RMS_PLUGIN_URL` | `plugin_dir_url( __FILE__ )` |
| `RMS_REST_NAMESPACE` | `rms/v1` |

---

## Options (wp_options)

| Key | Role | Type | Notes |
|---|---|---|---|
| `rms_role` | Both | `string` | `source` \| `consumer` \| `''` |
| `rms_connection_key_hash` | Source | `string` | `wp_hash()` of the raw key |
| `rms_connection_key_prefix` | Source | `string` | First 8 chars of raw key (shown in UI for identification) |
| `rms_remote_url` | Consumer | `string` | Remote site base URL, no trailing slash |
| `rms_remote_key` | Consumer | `string` | Raw connection key; stored in DB, admin-only access |
| `rms_upload_mode` | Consumer | `string` | `local` \| `block` |
| `rms_last_connection` | Consumer | `array` | `{ timestamp, success, message }` |

The raw key (`rms_remote_key`) is stored in `wp_options`. Access is gated to `manage_options` capability throughout. The UI masks the value after save.

---

## Roles & Settings UI

A single **Settings > Remote Media Source** admin page with a **Role** selector at the top. Switching role clears the opposing role's options to avoid stale config.

### Source tab (visible when role = source)

- **Connection Key** — display area showing prefix + masked remainder. Buttons: "Copy Full Key" (JS clipboard), "Regenerate Key" (POST with nonce). Regenerating invalidates any consumer currently using the old key.
- **Status** — read-only: "Source active. X consumer(s) verified." (future; v1.0 shows static "Source active.")

### Consumer tab (visible when role = consumer)

- **Remote Source URL** — text input, validated as HTTPS URL on save.
- **Connection Key** — password input, masked after save with "Reveal" toggle.
- **Upload Mode** — radio: "Local only (default)" / "Block uploads".
- **Test Connection** — button triggers AJAX → server-side HEAD + REST handshake → returns inline success/error message with timestamp. The `upload_dir` URL rewrite does not activate until at least one successful test has been recorded, so this step is required before the consumer is fully active. A persistent notice on the settings page indicates whether the consumer is active or pending verification.

---

## Source: Key Generation (KeyManager.php)

Key format: 32 random bytes, `bin2hex` encoded → 64-char hex string.

```
Generation:  wp_generate_password( 64, false )
Storage:     hash  → wp_hash( $key ) saved as rms_connection_key_hash
             prefix → substr( $key, 0, 8 ) saved as rms_connection_key_prefix
             raw   → returned once to the UI, never stored
```

Verification on incoming requests: `hash_equals( wp_hash( $supplied_key ), get_option( 'rms_connection_key_hash' ) )`.

The raw key is shown once on generation. If lost, admin regenerates — old key is immediately invalid.

---

## Source: REST Endpoint (RestEndpoint.php)

Registered at: `GET /wp-json/rms/v1/verify`

Authentication: custom `Authorization: RMS <key>` request header. Checked in a `permission_callback` using `KeyManager::verify()`.

Response on success:
```json
{
  "verified": true,
  "site_name": "Site Name",
  "wp_version": "6.x",
  "plugin_version": "1.0.0",
  "uploads_baseurl": "https://example.com/wp-content/uploads"
}
```

Response on failure: `WP_Error` with `rms_unauthorized`, HTTP 401.

This endpoint is the only one registered in v1.0. It serves the Test Connection check and confirms the remote is a WordPress site running this plugin with a valid key.

---

## Consumer: URL Rewrite (UploadDir.php)

Hooks `upload_dir` filter. When consumer role is active and `rms_remote_url` is set, replaces `baseurl` and `url`:

```php
$dirs['baseurl'] = rtrim( get_option( 'rms_remote_url' ), '/' ) . '/wp-content/uploads';
$dirs['url']     = $dirs['baseurl'] . '/' . gmdate( 'Y/m' );
```

`basedir` and `path` are left unchanged so any local uploads still land in the local filesystem (local-only mode).

The filter is a no-op when:
- Role is not `consumer`
- `rms_remote_url` is empty
- `rms_last_connection` has no successful verification on record

---

## Consumer: Upload Mode (UploadMode.php)

### Local-only (default)
No additional hooks. Uploads proceed normally, land in local `wp-content/uploads/`. URLs are rewritten by `UploadDir.php` to the remote baseurl, so locally-uploaded files won't be accessible via their generated URLs unless the same filename exists on the remote — this is intentional and documented.

### Block uploads
- `admin_init`: check `$_POST` for `html-upload` and `async-upload.php` requests → `wp_die()` with an admin notice.
- `upload_mimes` filter: return empty array to prevent MIME-type validation passing.
- Enqueue a small admin notice on `media-new.php` and the media modal frame explaining uploads are blocked on this environment.

---

## Connection Test Flow

1. Admin clicks "Test Connection" in consumer settings.
2. JS fires `wp_ajax` action `rms_test_connection` with nonce.
3. Server-side handler:
   a. Validates nonce + `manage_options` capability.
   b. `wp_remote_head( $remote_url )` — confirms URL is reachable, checks HTTP 200.
   c. `wp_remote_get( $remote_url . '/wp-json/rms/v1/verify', [ headers: [ Authorization: RMS $key ] ] )` — verifies plugin is active and key is valid.
   d. Saves result to `rms_last_connection`.
   e. Returns JSON `{ success, message, timestamp }`.
4. JS updates inline status element.

---

## Tooling

### composer.json scripts

| Script | Command |
|---|---|
| `install-hooks` | `git config core.hooksPath .githooks` |
| `lint` | `phpcs` |
| `lint:fix` | `phpcbf` |
| `test` | `phpunit` |
| `test:unit:php` | `./vendor/bin/phpunit` |
| `build-zip` | `./bin/build-zip.sh` |
| `plugin-check` | Build zip → `lando wp plugin check remote-media-source` → cleanup |

### phpcs.xml.dist rules
- `WordPress`, `WordPress-Extra`, `WordPress-Docs`
- `PHPCompatibilityWP` with `testVersion 8.1-8.4`
- `minimum_supported_wp_version 6.0`
- Excludes: `vendor/`, `.git/`, `tests/`, `zips/`, `.claude/`

### .githooks/pre-commit
Runs `composer lint:fix` only (fast auto-format before commit message editor opens).

### .githooks/commit-msg
1. Validates Conventional Commits pattern (`[UR-NNN: ]<type>[scope]: <description>`)
2. `composer lint:fix` + `composer lint`
3. `composer test:unit:php`

### .distignore
Excludes all dev files: `.githooks/`, `tests/`, `bin/`, `vendor/`, `composer.*`, `phpcs.xml.dist`, `phpunit.xml.dist`, `*.md`, `*.markdown`, `node_modules/`, `.claude/`, `zips/`, `.git*`, `.editorconfig`

---

## Unit Tests (v1.0)

| Test file | Covers |
|---|---|
| `KeyManagerTest.php` | Key generation length/format, hash storage, `verify()` returns true/false correctly, regenerate clears old hash |
| `UploadDirTest.php` | Filter applied when role=consumer + URL set, no-op when role=source, no-op when URL empty, correct baseurl format |
| `UploadModeTest.php` | Block mode: upload_mimes returns empty array, local mode: upload_mimes unchanged |

Tests use WP test stubs (Brain\Monkey or WP Core test bootstrap) — no live database required.

---

## WP.org Submission Requirements

- `readme.txt` includes all required sections: Description, Installation, FAQ, Screenshots, Changelog, Upgrade Notice.
- **External Services section** (hard requirement): documents that the consumer role makes HTTP requests to the admin-configured remote URL to verify the connection, lists what data is transmitted (connection key, site metadata), and notes that the remote URL's privacy policy applies.
- All user-facing strings wrapped in `__()` / `esc_html_e()` with text domain `remote-media-source`.
- All output escaped with appropriate `esc_*` functions.
- All forms and AJAX handlers verified with `check_admin_referer()` / `wp_verify_nonce()`.
- All capability checks: `current_user_can( 'manage_options' )`.
- No direct `$wpdb` queries in v1.0 (options API only).
- No inline scripts or styles.
- `Plugin Check` tool passes with zero errors before submission.

---

## v1.1 Scope (not in this plan)

- Live media library proxy: intercept `wp_ajax_query-attachments` (classic modal) and `rest_pre_dispatch` on `GET /wp/v2/media` (Gutenberg), proxy authenticated requests to remote, transform response format.
- Shadow attachment: on media item selection, create a minimal local `attachment` post with `_rms_remote_id` + `_rms_remote_url` meta for deduplication; local record enables featured image / gallery shortcode to store a real post ID.
- Upload mode: proxy to remote via authenticated `POST /wp-json/wp/v2/media`.
