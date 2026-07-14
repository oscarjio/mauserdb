#!/bin/bash
# ============================================================================
# deploy-pi.sh — Deploy backend till Pi:n för FAS 1 Pi-aggregering
# ----------------------------------------------------------------------------
# Pi:n kör internal-api.php nära DB:n. Denna deploy speglar noreko-backend till
# Pi:n MEN exkluderar per-server-hemligheter och lokalt genererade filer.
#
# OBS: PI_HOST är en PLACEHOLDER — den fysiska Pi:n är inte uppsatt än (väntar
# på Oscar/infra). Sätt rätt host/port/path innan skarp körning. Kör aldrig
# mot prod utan Oscars godkännande.
#
# Användning: cd /home/clawd/clawd/mauserdb && bash noreko-backend/deploy-pi.sh
# ============================================================================

set -e
cd "$(dirname "$0")"

# --- PLACEHOLDER — fyll i när Pi:n finns ---
PI_HOST="${PI_HOST:-pi@REPLACE_WITH_PI_HOST}"
PI_PORT="${PI_PORT:-22}"
PI_PATH="${PI_PATH:-/var/www/mauserdb/noreko-backend/}"

if [[ "$PI_HOST" == *REPLACE_WITH_PI_HOST* ]]; then
    echo "deploy-pi.sh: PI_HOST är inte konfigurerad (placeholder). Avbryter."
    echo "  Sätt PI_HOST/PI_PORT/PI_PATH (env eller redigera skriptet) när Pi:n är uppsatt."
    exit 1
fi

# Cache-busting: stämpla deployad kodversion FÖRE rsync (samma som deploy-dev.sh).
# CodeVersion::get() lägger den i cache-nycklarna → gammal cache blir oåtkomlig direkt.
CODE_VERSION="$(git rev-parse --short HEAD 2>/dev/null || date +%s)"
echo "$CODE_VERSION" > CODE_VERSION
echo "CODE_VERSION = $CODE_VERSION"

echo "Deployar backend till Pi ($PI_HOST)..."
rsync -avz --delete \
  --exclude='db_config.php' \
  --exclude='agg_config.php' \
  --exclude='internal_token.php' \
  --exclude='cors_origins.php' \
  --exclude='app_config.php' \
  --exclude='logs/' \
  --exclude='cache/' \
  --exclude='.git/' \
  ./ \
  "$PI_HOST:$PI_PATH" \
  -e "ssh -p $PI_PORT" \
  --quiet

# Städa gammal versionerad cache (redan oåtkomlig via nya nyckeln) — hindrar obegränsad tillväxt.
ssh -p "$PI_PORT" "$PI_HOST" \
  "find ${PI_PATH%/}/cache -type f \( -name 'swr_*.json' -o -name 'tvattlinje_statistics_*.json' \) -mtime +1 -delete" \
  2>/dev/null || true

echo "Klart! Backend deployat till Pi ($PI_HOST) (CODE_VERSION=$CODE_VERSION)."
