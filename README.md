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
npm install   # Cypress e2e tooling
```

| Command | Purpose |
|---|---|
| `composer lint` | PHPCS check |
| `composer lint:fix` | PHPCBF auto-fix |
| `composer test` | PHPUnit unit tests |
| `composer test:e2e` | Cypress e2e suite (needs the [test-envs](test-envs/README.md) Lando sites running) |
| `composer build-zip` | Build distribution zip |
| `composer plugin-check` | WP.org Plugin Check against the dist build (needs the rms-source Lando site) |
| `composer wp-repo-screenshots` | Regenerate the wp.org screenshots |
| `composer check` | Full release gate: lint + unit + e2e + plugin-check |

Local end-to-end testing runs against three Lando WordPress sites (one source,
two consumers) — see [test-envs/README.md](test-envs/README.md). WP.org listing
assets (banner, icon) live in `.wordpress-org/` with their HTML sources.

## License

GPLv2 or later — see [gpl-2.0.txt](gpl-2.0.txt).
