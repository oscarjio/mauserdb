#!/bin/bash
# Deploy backend till dev.mauserdb.com
# Användning: cd /home/clawd/clawd/mauserdb && bash noreko-backend/deploy-dev.sh
# OBS: db_config.php exkluderas — den hanteras separat per server.

set -e
cd "$(dirname "$0")"

# Cache-busting: stämpla deployad kodversion FÖRE rsync. CodeVersion::get() läser denna fil
# och lägger den i cache-nycklarna → gammal cache blir oåtkomlig direkt (annars serveras
# gamla värden upp till 7 dygn). CODE_VERSION exkluderas INTE nedan, så den följer med.
CODE_VERSION="$(git rev-parse --short HEAD 2>/dev/null || date +%s)"
echo "$CODE_VERSION" > CODE_VERSION
echo "CODE_VERSION = $CODE_VERSION"

echo "Deployar backend till dev..."
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
  user@mauserdb.com:/var/www/mauserdb-dev/noreko-backend/ \
  -e "ssh -p 32546" \
  --quiet

# Städa gammal versionerad cache (redan oåtkomlig via nya nyckeln) — hindrar obegränsad tillväxt.
ssh -p 32546 user@mauserdb.com \
  "find /var/www/mauserdb-dev/noreko-backend/cache -type f \( -name 'swr_*.json' -o -name 'tvattlinje_statistics_*.json' \) -mtime +1 -delete" \
  2>/dev/null || true

echo "Klart! Backend deployat till dev.mauserdb.com (CODE_VERSION=$CODE_VERSION)"
