#!/usr/bin/env bash
# -----------------------------------------------------------------------------
# rms-consumer-2 Lando setup (simulates a staging environment)
# Run once from this directory: bash setup.sh
# rms-source must already be running before Test Connection will work.
# -----------------------------------------------------------------------------
set -euo pipefail
cd "$(dirname "$0")"

SITE_URL="https://rms-consumer-2.lndo.site"
SITE_TITLE="RMS Consumer 2 (Staging)"
SOURCE_URL="https://rms-source.lndo.site"
ADMIN_EMAIL="pete@petelower.com"

echo "==> Starting Lando..."
lando start

echo "==> Downloading WordPress core..."
lando wp core download --version=latest --skip-content --force

echo "==> Creating wp-config.php..."
lando wp config create \
  --dbname=wordpress \
  --dbuser=wordpress \
  --dbpass=wordpress \
  --dbhost=database \
  --force

echo "==> Enabling debug logging..."
lando wp config set WP_DEBUG true --raw
lando wp config set WP_DEBUG_LOG true --raw
lando wp config set WP_DEBUG_DISPLAY false --raw

echo "==> Installing WordPress..."
if ! lando wp core is-installed 2>/dev/null; then
  lando wp core install \
    --url="$SITE_URL" \
    --title="$SITE_TITLE" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email="$ADMIN_EMAIL" \
    --skip-email
else
  echo "   (already installed, skipping)"
fi

echo "==> Adding local-dev mu-plugin (SSL bypass for Lando self-signed certs)..."
# webroot is '.' so wp-content lives at the env root, not inside wordpress/
mkdir -p wp-content/mu-plugins
cat > wp-content/mu-plugins/rms-local-dev.php << 'PHP'
<?php
/**
 * Local development helpers for Remote Media Source testing.
 * DO NOT deploy to any real environment — for test-envs only.
 */

// Allow wp_remote_get() to connect to Lando's self-signed SSL certs.
add_filter( 'https_ssl_verify', '__return_false' );
add_filter( 'https_local_ssl_verify', '__return_false' );
PHP

echo "==> Installing default theme..."
lando wp theme install twentytwentyfive --activate 2>/dev/null || true

echo "==> Activating Remote Media Source plugin..."
lando wp plugin activate remote-media-source

echo "==> Setting role to Consumer and pre-filling source URL..."
lando wp option update rms_role consumer
lando wp option update rms_remote_url "$SOURCE_URL"

echo "==> Setting upload mode to 'block' (staging typically blocks new uploads)..."
lando wp option update rms_upload_mode block

echo ""
echo "╔══════════════════════════════════════════════════════╗"
echo "║  rms-consumer-2 ready                                ║"
echo "╠══════════════════════════════════════════════════════╣"
echo "║  Site:   $SITE_URL  ║"
echo "║  Admin:  $SITE_URL/wp-admin   ║"
echo "║  Login:  admin / admin                               ║"
echo "╠══════════════════════════════════════════════════════╣"
echo "║  Config: Upload mode = block (verify blocked upload) ║"
echo "╠══════════════════════════════════════════════════════╣"
echo "║  Next step:                                          ║"
echo "║  1. Open Admin > Settings > Remote Media Source      ║"
echo "║  2. Paste the connection key from rms-source         ║"
echo "║  3. Save Settings, then click 'Test Connection'      ║"
echo "╚══════════════════════════════════════════════════════╝"
