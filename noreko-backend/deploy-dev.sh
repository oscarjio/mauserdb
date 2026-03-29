#!/bin/bash
# Deploy backend till dev.mauserdb.com
# Användning: cd /home/clawd/clawd/mauserdb && bash noreko-backend/deploy-dev.sh
# OBS: db_config.php exkluderas — den hanteras separat per server.

set -e
cd "$(dirname "$0")"

echo "Deployar backend till dev..."
rsync -avz --delete \
  --exclude='db_config.php' \
  --exclude='cors_origins.php' \
  --exclude='app_config.php' \
  --exclude='logs/' \
  --exclude='.git/' \
  ./ \
  user@mauserdb.com:/var/www/mauserdb-dev/noreko-backend/ \
  -e "ssh -p 32546" \
  --quiet

echo "Klart! Backend deployat till dev.mauserdb.com"
