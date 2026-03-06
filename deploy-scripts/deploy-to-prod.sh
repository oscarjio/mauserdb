#!/bin/bash

# Noreko Deploy Script - Dev till Prod
# Kör detta script för att deploya från dev till produktion

set -e  # Avbryt vid fel

# Färger för output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Konfiguration
DEV_FRONTEND="/var/www/mauserdb-dev/noreko-frontend/dist/noreko-frontend/browser"
DEV_BACKEND="/var/www/mauserdb-dev/noreko-backend"
PROD_FRONTEND="/var/www/mauserdb-prod/noreko-frontend/dist/noreko-frontend/browser"
PROD_BACKEND="/var/www/mauserdb-prod/noreko-backend"
BACKUP_DIR="/var/www/mauserdb-backups"

echo -e "${YELLOW}╔════════════════════════════════════════╗${NC}"
echo -e "${YELLOW}║   NOREKO PRODUCTION DEPLOYMENT         ║${NC}"
echo -e "${YELLOW}╔════════════════════════════════════════╗${NC}"
echo ""

# Säkerhetskontroll
echo -e "${RED}⚠️  Du är på väg att deploya till PRODUKTION!${NC}"
echo -e "${RED}⚠️  Detta kommer påverka live-sajten!${NC}"
echo ""
echo "Skriv 'DEPLOY' för att fortsätta (eller Ctrl+C för att avbryta):"
read -r confirm

if [ "$confirm" != "DEPLOY" ]; then
    echo -e "${RED}❌ Deploy avbruten${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}✓ Startar deployment...${NC}"
echo ""

# Skapa backup-mapp om den inte finns
mkdir -p "$BACKUP_DIR"

# 1. Backup av nuvarande produktion
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_PATH="$BACKUP_DIR/prod_backup_$TIMESTAMP"

echo -e "${YELLOW}📦 Skapar backup av produktion...${NC}"
mkdir -p "$BACKUP_PATH"
cp -r "$PROD_FRONTEND" "$BACKUP_PATH/frontend" 2>/dev/null || true
cp -r "$PROD_BACKEND" "$BACKUP_PATH/backend" 2>/dev/null || true
echo -e "${GREEN}✓ Backup skapad: $BACKUP_PATH${NC}"
echo ""

# 2. Bygg production build av frontend
echo -e "${YELLOW}🔨 Bygger production build...${NC}"
cd "$(dirname "$0")/../noreko-frontend"
npm run build --configuration production
echo -e "${GREEN}✓ Build klar${NC}"
echo ""

# 3. Kopiera frontend till prod
echo -e "${YELLOW}📤 Kopierar frontend till produktion...${NC}"
mkdir -p "$PROD_FRONTEND"
rsync -av --delete "$(dirname "$0")/../noreko-frontend/dist/noreko-frontend/browser/" "$PROD_FRONTEND/"
echo -e "${GREEN}✓ Frontend deployad${NC}"
echo ""

# 4. Kopiera PHP backend till prod
echo -e "${YELLOW}📤 Kopierar backend till produktion...${NC}"
mkdir -p "$PROD_BACKEND"
rsync -av --exclude='.git' --exclude='*.log' --exclude='tmp/' \
    "$DEV_BACKEND/" "$PROD_BACKEND/"
echo -e "${GREEN}✓ Backend deployad${NC}"
echo ""

# 5. Sätt rätt permissions
echo -e "${YELLOW}🔒 Sätter permissions...${NC}"
chown -R www-data:www-data "$PROD_FRONTEND" "$PROD_BACKEND" 2>/dev/null || {
    echo -e "${YELLOW}⚠️  Kunde inte sätta www-data permissions (kör kanske inte som root?)${NC}"
}
chmod -R 755 "$PROD_FRONTEND"
chmod -R 755 "$PROD_BACKEND"
echo -e "${GREEN}✓ Permissions satta${NC}"
echo ""

# 6. Rensa gamla backups (behåll senaste 10)
echo -e "${YELLOW}🧹 Rensar gamla backups...${NC}"
cd "$BACKUP_DIR"
ls -t | tail -n +11 | xargs -r rm -rf
echo -e "${GREEN}✓ Gamla backups rensade${NC}"
echo ""

echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║     ✅ DEPLOYMENT SLUTFÖRD!            ║${NC}"
echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
echo ""
echo -e "Backup sparad i: ${YELLOW}$BACKUP_PATH${NC}"
echo ""
echo -e "${YELLOW}💡 Om något gick fel, återställ med:${NC}"
echo -e "   ./rollback-prod.sh $TIMESTAMP"
echo ""
