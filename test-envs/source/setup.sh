#!/usr/bin/env bash
# -----------------------------------------------------------------------------
# rms-source Lando setup
# Run once from this directory: bash setup.sh
# Safe to re-run — skips already-completed steps.
# -----------------------------------------------------------------------------
set -euo pipefail
cd "$(dirname "$0")"

SITE_URL="https://rms-source.lndo.site"
SITE_TITLE="RMS Source (Production)"
ADMIN_EMAIL="pete@petelower.com"

echo "==> Starting Lando..."
lando start

echo "==> Downloading WordPress core..."
lando wp core download --version=latest --skip-content 2>/dev/null || true

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

echo "==> Activating Remote Media Source plugin..."
lando wp plugin activate remote-media-source

echo "==> Setting role to Source..."
lando wp option update rms_role source

echo ""
echo "╔══════════════════════════════════════════════════════╗"
echo "║  rms-source ready                                    ║"
echo "╠══════════════════════════════════════════════════════╣"
echo "║  Site:   $SITE_URL     ║"
echo "║  Admin:  $SITE_URL/wp-admin  ║"
echo "║  Login:  admin / admin                               ║"
echo "╠══════════════════════════════════════════════════════╣"
echo "║  Next step:                                          ║"
echo "║  1. Open Admin > Settings > Remote Media Source      ║"
echo "║  2. Click 'Generate Connection Key'                  ║"
echo "║  3. Copy the key — you'll need it for consumers      ║"
echo "╚══════════════════════════════════════════════════════╝"
