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
