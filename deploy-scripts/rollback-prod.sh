#!/bin/bash

# Noreko Rollback Script - Återställ produktion från backup

set -e

# Färger
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

BACKUP_DIR="/var/www/backups"
PROD_FRONTEND="/var/www/frontend"
PROD_BACKEND="/var/www/backend"

echo -e "${YELLOW}╔════════════════════════════════════════╗${NC}"
echo -e "${YELLOW}║   NOREKO PRODUCTION ROLLBACK           ║${NC}"
echo -e "${YELLOW}╔════════════════════════════════════════╗${NC}"
echo ""

# Visa tillgängliga backups
echo "Tillgängliga backups:"
echo ""
ls -lt "$BACKUP_DIR" | grep "^d" | awk '{print $9}' | nl
echo ""

# Om timestamp angavs som argument
if [ -n "$1" ]; then
    BACKUP_PATH="$BACKUP_DIR/prod_backup_$1"
else
    echo "Ange backup timestamp (eller tryck Enter för senaste):"
    read -r timestamp
    
    if [ -z "$timestamp" ]; then
        # Använd senaste backup
        BACKUP_PATH=$(ls -td "$BACKUP_DIR"/prod_backup_* | head -1)
    else
        BACKUP_PATH="$BACKUP_DIR/prod_backup_$timestamp"
    fi
fi

if [ ! -d "$BACKUP_PATH" ]; then
    echo -e "${RED}❌ Backup hittades inte: $BACKUP_PATH${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}Återställer från: $BACKUP_PATH${NC}"
echo ""
echo -e "${RED}⚠️  Detta kommer skriva över nuvarande produktion!${NC}"
echo "Skriv 'ROLLBACK' för att fortsätta:"
read -r confirm

if [ "$confirm" != "ROLLBACK" ]; then
    echo -e "${RED}❌ Rollback avbruten${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}✓ Startar rollback...${NC}"
echo ""

# Återställ frontend
if [ -d "$BACKUP_PATH/frontend" ]; then
    echo -e "${YELLOW}📥 Återställer frontend...${NC}"
    rsync -av --delete "$BACKUP_PATH/frontend/" "$PROD_FRONTEND/"
    echo -e "${GREEN}✓ Frontend återställd${NC}"
fi

# Återställ backend
if [ -d "$BACKUP_PATH/backend" ]; then
    echo -e "${YELLOW}📥 Återställer backend...${NC}"
    rsync -av --delete "$BACKUP_PATH/backend/" "$PROD_BACKEND/"
    echo -e "${GREEN}✓ Backend återställd${NC}"
fi

echo ""
echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║     ✅ ROLLBACK SLUTFÖRD!              ║${NC}"
echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
echo ""
