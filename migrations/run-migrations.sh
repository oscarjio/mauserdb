#!/bin/bash
# Kör alla databasmigrationer i ordning
# Användning: ./run-migrations.sh [mysql-host] [mysql-port]
#
# Standardvärden: host=localhost, port=33061

set -e

HOST="${1:-localhost}"
PORT="${2:-33061}"
USER="aiab"
DB="mauserdb"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

echo -e "${YELLOW}═══════════════════════════════════════${NC}"
echo -e "${YELLOW}  Mauserdb - Databas-migrationer${NC}"
echo -e "${YELLOW}═══════════════════════════════════════${NC}"
echo ""
echo -e "Host: ${HOST}:${PORT}"
echo -e "Databas: ${DB}"
echo ""

# Hitta alla migrations-filer i ordning
MIGRATIONS=$(ls "$SCRIPT_DIR"/[0-9]*.sql 2>/dev/null | sort)

if [ -z "$MIGRATIONS" ]; then
    echo -e "${YELLOW}Inga migrationer hittades.${NC}"
    exit 0
fi

echo -e "Hittade följande migrationer:"
for f in $MIGRATIONS; do
    echo "  - $(basename "$f")"
done
echo ""

echo -e "Ange lösenord för MySQL-användaren '${USER}':"
read -s -r PASS
echo ""

FAILED=0
for f in $MIGRATIONS; do
    NAME=$(basename "$f")
    echo -ne "  ${YELLOW}Kör ${NAME}...${NC} "
    if mysql -h "$HOST" -P "$PORT" -u "$USER" -p"$PASS" "$DB" < "$f" 2>/tmp/migration_err; then
        echo -e "${GREEN}OK${NC}"
    else
        echo -e "${RED}FEL${NC}"
        cat /tmp/migration_err
        FAILED=$((FAILED + 1))
    fi
done

echo ""
if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}Alla migrationer kördes framgångsrikt.${NC}"
else
    echo -e "${RED}${FAILED} migration(er) misslyckades. Se felmeddelanden ovan.${NC}"
    exit 1
fi
