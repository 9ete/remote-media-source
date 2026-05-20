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
