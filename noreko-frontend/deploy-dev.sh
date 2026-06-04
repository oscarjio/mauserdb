#!/bin/bash
# Snabb build + deploy till dev.mauserdb.com
# Användning: ./deploy-dev.sh

set -e
cd "$(dirname "$0")"

echo "🔨 Bygger..."
npx ng build

echo "🚀 Deployar till dev..."
rsync -avz --delete \
  dist/noreko-frontend/ \
  user@mauserdb.com:/var/www/mauserdb-dev/noreko-frontend/dist/noreko-frontend/ \
  -e "ssh -p 32546" \
  --quiet

echo "✅ Klart! https://dev.mauserdb.com"
