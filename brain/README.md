# Remote Media Source — Project Brain

## What this plugin does

Solves a recurring problem on multi-environment WordPress projects: non-production sites (local, dev, staging) duplicating gigabytes of production media, causing storage waste and sync headaches.

**Source role (prod):** Generates a connection key. Exposes an authenticated REST endpoint at `GET /wp-json/rms/v1/verify`.

**Consumer role (local/dev/stg):** Stores the remote URL + key. Rewrites all `upload_dir` URLs to the remote source. Enforces a configurable upload policy (local-only or block). Provides a Test Connection button that verifies the remote is reachable and running this plugin with a valid key.

## Origin

Designed May 2026 from UrSource project ticket UR-48 (MMDB Solutions client). UrSource had ~4.6 GB of media duplicated across dev/staging/prod on WP Engine. The plugin is generic — not tied to UrSource.

## Jira / tickets

No Jira project assigned yet. Commit messages use bare Conventional Commits (`feat:`, `fix:`, etc.) without a ticket prefix.

## Key decisions

- **WP.org first-submission target** — Plugin Check must pass, External Services disclosure required in readme.txt.
- **v1.0 scope is deliberately narrow** — URL rewrite + connection + upload modes only. Live media library proxy deferred to v1.1 to reduce reviewer surface area and ship faster.
- **Connection key model** — Source stores a `wp_hash()` of the key only. Consumer stores the raw key (required for outbound auth). Raw key shown once on generation; regenerating invalidates all consumers.
- **No Composer autoload** — custom `autoload.php` (PSR-4 style, same pattern as ai-content-by-parallax). Keeps vendor/ lean.
- **Upload mode "block"** — enforced via `upload_mimes` returning empty array + upload handler interception. Blocks both classic uploader and REST media endpoint.
- **`upload_dir` rewrite only activates after a verified test connection** — prevents misconfigured consumers from silently serving broken URLs.

## Reference

- Design spec: `specs/2026-05-20-remote-media-source-design.md`
- Reference plugin pattern: [ai-content-by-parallax](https://github.com/MMDBSolutions/ai-content-by-parallax)
- WP Plugin Check: `lando wp plugin check remote-media-source`
