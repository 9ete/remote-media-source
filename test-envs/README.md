# Remote Media Source — Local Test Environments

Three Lando WordPress installs for end-to-end plugin testing.

| Site | URL | Role | Upload mode |
|---|---|---|---|
| `rms-source` | https://rms-source.lndo.site | Source | — |
| `rms-consumer-1` | https://rms-consumer-1.lndo.site | Consumer | local (default) |
| `rms-consumer-2` | https://rms-consumer-2.lndo.site | Consumer | block |

All three share the same plugin code via a volume mount — edits to `src/` take effect immediately without rebuilding.

## Requirements

- [Lando](https://lando.dev) ≥ 3.6
- Docker Desktop ≥ 20.10 (for `host-gateway` support in consumer containers)
- Node.js ≥ 18 + `npm install` at the repo root (Cypress e2e + screenshot capture)

All three sites must be running for `composer test:e2e`, `composer plugin-check`,
and the full `composer check` release gate.

## Setup

Run each setup script **in order** from its own directory:

```bash
# 1. Source site
cd source && bash setup.sh

# 2. Consumer 1 (local dev simulation — local upload mode)
cd ../consumer-1 && bash setup.sh

# 3. Consumer 2 (staging simulation — block upload mode)
cd ../consumer-2 && bash setup.sh
```

All three sites: **admin / admin**

## Test walkthrough

### 1. Generate a connection key (source)

1. Open https://rms-source.lndo.site/wp-admin
2. Settings → Remote Media Source
3. Click **Generate Connection Key** — copy the key from the modal

### 2. Connect consumer-1

1. Open https://rms-consumer-1.lndo.site/wp-admin
2. Settings → Remote Media Source
3. Paste the key → **Save Settings** → **Test Connection**
4. Should show ✓ Connected

### 3. Verify URL rewriting

1. Upload an image on the source site (Media → Add New)
2. On consumer-1, create a post and insert the image via the media library
3. The `src` attribute in the rendered page should point to `rms-source.lndo.site`, not `rms-consumer-1.lndo.site`

### 4. Verify local upload guard (consumer-1)

1. On consumer-1, upload a new image
2. The file should be stored locally AND accessible — URL should point to `rms-consumer-1.lndo.site`
3. Confirm the file exists locally: `lando ssh -c "ls /app/wordpress/wp-content/uploads/$(date +%Y/%m)/"` from the `consumer-1/` directory

### 5. Verify block mode (consumer-2)

1. Connect consumer-2 the same way as consumer-1
2. Try uploading any file → should return an error message
3. Existing media library items should still render from the source

## Networking note

Consumer containers reach the source via `extra_hosts: rms-source.lndo.site:host-gateway`. This routes PHP's `wp_remote_get()` calls through Docker Desktop's host gateway to Lando's traefik proxy, which forwards to the source container.

If Test Connection returns a curl/SSL error, ensure:
- `rms-source` is running (`lando start` from `source/`)
- Docker Desktop is version 20.10+

The `mu-plugins/rms-local-dev.php` file installed by each consumer's setup script disables SSL certificate verification so Lando's self-signed certs don't block the handshake.

## Teardown

```bash
# Stop all three (run from each directory)
lando stop    # or: lando destroy --yes  (removes DB too)
```
