# Remote Media Source — Claude Instructions

## Project at a glance

- **Type:** Distributable WordPress plugin (WP.org submission target)
- **Slug:** `remote-media-source`
- **Namespace:** `RemoteMediaSource\`
- **PHP prefix:** `rms_` / class prefix `RMS_`
- **Stack:** PHP 8.1+, WordPress 6.0+, no WooCommerce dependency
- **Tooling:** Composer, PHPCS/WPCS 3.x, PHPUnit 10.x, WP-CLI
- **Local dev:** Lando (set up when implementation begins). Site URL TBD.
- **Author:** 9ete / LowerMedia LLC

## Context

This plugin was designed in May 2026 to solve a recurring problem on client WordPress projects: non-production environments (local, dev, staging) duplicating gigabytes of production media. The full design history and spec live in `brain/specs/`.

It originated from UrSource (UR-48) but is a standalone, generic plugin — not tied to that client.

## Spec

Full design spec: `brain/specs/2026-05-20-remote-media-source-design.md`

Read this before any non-trivial implementation work.

## Code standards

- PHP 8.1+, class-based architecture, WPCS/PHPCS 3.x compliant
- `WordPress`, `WordPress-Extra`, `WordPress-Docs` rulesets
- `PHPCompatibilityWP` tested against 8.1–8.4
- All output escaped. All inputs sanitized. All forms/AJAX nonce-verified.
- `current_user_can( 'manage_options' )` on every settings action.
- No inline scripts or styles — use `wp_enqueue_*`.
- Text domain: `remote-media-source` on all i18n strings.
- WP.org Plugin Check tool must pass with zero errors before any release.

## PHP change workflow (non-negotiable)

1. `php -l <file>` every modified PHP file before staging.
2. Load affected surface in local Lando env.
3. Tail `wp-content/debug.log` while exercising the change.
4. Only then push/deploy.

## Git conventions

- Atomic conventional commits. No ticket prefix on this project (open source, no Jira key assigned).
- Prefixes: `feat:`, `fix:`, `docs:`, `chore:`, `refactor:`, `test:`, `build:`, `ci:`
- Never co-author commits.
- Install hooks after cloning: `composer install-hooks`
- `.githooks/pre-commit` — `composer lint:fix`
- `.githooks/commit-msg` — Conventional Commits validation + lint + unit tests

## Branching

- `main` — release commits only
- `develop` — all shipped work
- `vX.X.X` — release branches (cherry-pick from task branches)
- Task branches: short descriptive names (`feat/source-key-manager`, `fix/upload-dir-filter`)

## Build & release

```bash
composer build-zip        # produces remote-media-source.zip (WP.org) + versioned archive
composer plugin-check     # build-zip → lando wp plugin check → cleanup
```

## WP.org requirements (always in scope)

- `readme.txt` must have: Description, Installation, FAQ, Screenshots, Changelog, Upgrade Notice, External Services.
- External Services section is mandatory — this plugin makes outbound HTTP calls by design.
- Plugin Check must pass before tagging any release.

## v1.0 scope

URL rewrite + source/consumer connection + settings UI + local/block upload modes + test connection.

## v1.1 scope (not in current plan)

Live media library proxy (classic + Gutenberg), shadow attachment creation, proxy-to-remote upload mode. See spec for details.
