#!/bin/bash

# Snabb deploy UTAN säkerhetskontroller
# Använd endast för mindre uppdateringar

set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

DEV_FRONTEND="/var/www/dev/frontend"
DEV_BACKEND="/var/www/dev/backend"
PROD_FRONTEND="/var/www/frontend"
PROD_BACKEND="/var/www/backend"

echo -e "${YELLOW}⚡ SNABB DEPLOY${NC}"
echo ""

# Bygg frontend
echo -e "${YELLOW}🔨 Bygger...${NC}"
cd "$(dirname "$0")/../noreko-frontend"
npm run build --configuration production

# Kopiera
echo -e "${YELLOW}📤 Kopierar...${NC}"
rsync -a --delete "$(dirname "$0")/../noreko-frontend/dist/noreko-frontend/browser/" "$PROD_FRONTEND/"
rsync -a --exclude='.git' "$DEV_BACKEND/" "$PROD_BACKEND/"

echo -e "${GREEN}✅ Klart!${NC}"
