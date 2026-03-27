#!/bin/bash
# =============================================================================
# Rebotling E2E Regressionstest
# Testar ALLA rebotling-relaterade endpoints mot dev.mauserdb.com
# Verifierar: HTTP 200, giltig JSON, inga error-falt
# =============================================================================

set -euo pipefail

BASE="https://dev.mauserdb.com/noreko-backend/api.php"
COOKIE_JAR="/tmp/rebotling_e2e_cookies.txt"
PASS=0
FAIL=0
SKIP=0
ERRORS=""

# Farger
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
NC='\033[0m'

cleanup() {
    rm -f "$COOKIE_JAR" /tmp/rebotling_e2e_response.json
}
trap cleanup EXIT

# ---- Logga in for att fa session ----
echo "=== Rebotling E2E Regressionstest ==="
echo "Datum: $(date '+%Y-%m-%d %H:%M:%S')"
echo "Target: $BASE"
echo ""
echo "--- Loggar in ---"

LOGIN_RESP=$(curl -s -c "$COOKIE_JAR" -X POST "$BASE?action=login" \
    -H "Content-Type: application/json" \
    -d '{"username":"aiab","password":"Noreko2025"}' 2>/dev/null || echo '{"error":"curl failed"}')

LOGIN_OK=$(echo "$LOGIN_RESP" | python3 -c "import sys,json; d=json.load(sys.stdin); print('yes' if d.get('success') else 'no')" 2>/dev/null || echo "no")

if [ "$LOGIN_OK" != "yes" ]; then
    echo -e "${YELLOW}VARNING: Inloggning misslyckades, testar anda (401 forvantade for skyddade endpoints)${NC}"
    echo "Svar: $LOGIN_RESP"
    echo ""
fi

# ---- Testfunktion ----
test_endpoint() {
    local description="$1"
    local url="$2"
    local expect_auth="${3:-yes}"  # yes = kraver inloggning

    local response
    local http_code
    local tmp_file="/tmp/rebotling_e2e_response.json"

    http_code=$(curl -s -b "$COOKIE_JAR" -o "$tmp_file" -w "%{http_code}" "$url" 2>/dev/null || echo "000")
    response=$(cat "$tmp_file" 2>/dev/null || echo "")

    # Kolla HTTP-status
    if [ "$http_code" = "000" ]; then
        echo -e "${RED}FAIL${NC} [$http_code] $description — Anslutning misslyckades"
        FAIL=$((FAIL + 1))
        ERRORS="${ERRORS}\n  - $description: Anslutning misslyckades"
        return
    fi

    if [ "$http_code" = "401" ] && [ "$expect_auth" = "yes" ] && [ "$LOGIN_OK" != "yes" ]; then
        echo -e "${YELLOW}SKIP${NC} [$http_code] $description — Ej inloggad"
        SKIP=$((SKIP + 1))
        return
    fi

    if [ "$http_code" != "200" ]; then
        echo -e "${RED}FAIL${NC} [$http_code] $description"
        FAIL=$((FAIL + 1))
        ERRORS="${ERRORS}\n  - $description: HTTP $http_code"
        return
    fi

    # Kolla giltig JSON
    if ! echo "$response" | python3 -c "import sys,json; json.load(sys.stdin)" 2>/dev/null; then
        echo -e "${RED}FAIL${NC} [$http_code] $description — Ogiltig JSON"
        FAIL=$((FAIL + 1))
        ERRORS="${ERRORS}\n  - $description: Ogiltig JSON"
        return
    fi

    # Kolla error-falt
    HAS_ERROR=$(echo "$response" | python3 -c "
import sys,json
d=json.load(sys.stdin)
if d.get('success') == False or 'error' in d:
    print(d.get('error','unknown error'))
else:
    print('ok')
" 2>/dev/null || echo "parse_error")

    if [ "$HAS_ERROR" != "ok" ] && [ "$HAS_ERROR" != "parse_error" ]; then
        echo -e "${RED}FAIL${NC} [$http_code] $description — Error: $HAS_ERROR"
        FAIL=$((FAIL + 1))
        ERRORS="${ERRORS}\n  - $description: $HAS_ERROR"
        return
    fi

    echo -e "${GREEN}PASS${NC} [$http_code] $description"
    PASS=$((PASS + 1))
}

echo ""
echo "--- Testar rebotling-relaterade endpoints ---"
echo ""

# ==========================================
# REBOTLING CORE
# ==========================================
echo "=== Rebotling Core ==="
test_endpoint "rebotling run=today" "$BASE?action=rebotling&run=today"
test_endpoint "rebotling run=history" "$BASE?action=rebotling&run=history"
test_endpoint "rebotling run=operators" "$BASE?action=rebotling&run=operators"
test_endpoint "rebotling run=settings" "$BASE?action=rebotling&run=settings"
test_endpoint "rebotling run=chart" "$BASE?action=rebotling&run=chart"
test_endpoint "rebotling run=live" "$BASE?action=rebotling&run=live"
test_endpoint "rebotling run=shifts" "$BASE?action=rebotling&run=shifts"
test_endpoint "rebotling run=kassation" "$BASE?action=rebotling&run=kassation"

# ==========================================
# REBOTLING SAMMANFATTNING
# ==========================================
echo ""
echo "=== Rebotling Sammanfattning ==="
test_endpoint "rebotling-sammanfattning run=overview" "$BASE?action=rebotling-sammanfattning&run=overview"
test_endpoint "rebotling-sammanfattning run=produktion-7d" "$BASE?action=rebotling-sammanfattning&run=produktion-7d"
test_endpoint "rebotling-sammanfattning run=maskin-status" "$BASE?action=rebotling-sammanfattning&run=maskin-status"

# ==========================================
# HISTORISK SAMMANFATTNING
# ==========================================
echo ""
echo "=== Historisk Sammanfattning ==="
test_endpoint "historisk-sammanfattning run=perioder" "$BASE?action=historisk-sammanfattning&run=perioder"
test_endpoint "historisk-sammanfattning run=rapport (manad)" "$BASE?action=historisk-sammanfattning&run=rapport&typ=manad&period=2026-03"
test_endpoint "historisk-sammanfattning run=trend (manad)" "$BASE?action=historisk-sammanfattning&run=trend&typ=manad&period=2026-03"
test_endpoint "historisk-sammanfattning run=operatorer (manad)" "$BASE?action=historisk-sammanfattning&run=operatorer&typ=manad&period=2026-03"
test_endpoint "historisk-sammanfattning run=stationer (manad)" "$BASE?action=historisk-sammanfattning&run=stationer&typ=manad&period=2026-03"
test_endpoint "historisk-sammanfattning run=stopporsaker" "$BASE?action=historisk-sammanfattning&run=stopporsaker&typ=manad&period=2026-03"

# ==========================================
# SKIFTJAMFORELSE
# ==========================================
echo ""
echo "=== Skiftjamforelse ==="
test_endpoint "skiftjamforelse run=sammanfattning" "$BASE?action=skiftjamforelse&run=sammanfattning"
test_endpoint "skiftjamforelse run=jamforelse" "$BASE?action=skiftjamforelse&run=jamforelse"
test_endpoint "skiftjamforelse run=trend" "$BASE?action=skiftjamforelse&run=trend"
test_endpoint "skiftjamforelse run=best-practices" "$BASE?action=skiftjamforelse&run=best-practices"
test_endpoint "skiftjamforelse run=detaljer" "$BASE?action=skiftjamforelse&run=detaljer"

# ==========================================
# OPERATORSBONUS
# ==========================================
echo ""
echo "=== Operatorsbonus ==="
test_endpoint "operatorsbonus run=overview" "$BASE?action=operatorsbonus&run=overview"
test_endpoint "operatorsbonus run=per-operator" "$BASE?action=operatorsbonus&run=per-operator"
test_endpoint "operatorsbonus run=per-operator (vecka)" "$BASE?action=operatorsbonus&run=per-operator&period=vecka"
test_endpoint "operatorsbonus run=per-operator (manad)" "$BASE?action=operatorsbonus&run=per-operator&period=manad"
test_endpoint "operatorsbonus run=konfiguration" "$BASE?action=operatorsbonus&run=konfiguration"
test_endpoint "operatorsbonus run=historik" "$BASE?action=operatorsbonus&run=historik"
test_endpoint "operatorsbonus run=simulering" "$BASE?action=operatorsbonus&run=simulering"
test_endpoint "operatorsbonus run=simulering (params)" "$BASE?action=operatorsbonus&run=simulering&ibc_per_timme=10&kvalitet=95&narvaro=90&team_mal=80"

# ==========================================
# OEE-RELATERADE
# ==========================================
echo ""
echo "=== OEE ==="
test_endpoint "oee-benchmark run=current-oee" "$BASE?action=oee-benchmark&run=current-oee"
test_endpoint "oee-waterfall run=waterfall-data" "$BASE?action=oee-waterfall&run=waterfall-data"
test_endpoint "oee-jamforelse run=weekly-oee" "$BASE?action=oee-jamforelse&run=weekly-oee"
test_endpoint "oee-trendanalys run=sammanfattning" "$BASE?action=oee-trendanalys&run=sammanfattning"
test_endpoint "maskin-oee run=overview" "$BASE?action=maskin-oee&run=overview"

# ==========================================
# DAGLIG SAMMANFATTNING / BRIEFING
# ==========================================
echo ""
echo "=== Daglig ==="
test_endpoint "daglig-sammanfattning run=daily-summary" "$BASE?action=daglig-sammanfattning&run=daily-summary"
test_endpoint "daglig-briefing run=sammanfattning" "$BASE?action=daglig-briefing&run=sammanfattning"

# ==========================================
# KVALITET
# ==========================================
echo ""
echo "=== Kvalitet ==="
test_endpoint "kvalitetstrend run=overview" "$BASE?action=kvalitetstrend&run=overview"
test_endpoint "kvalitetstrendbrott run=overview" "$BASE?action=kvalitetstrendbrott&run=overview"
test_endpoint "kvalitetstrendanalys run=overview" "$BASE?action=kvalitetstrendanalys&run=overview"
test_endpoint "kvalitetscertifikat run=overview" "$BASE?action=kvalitetscertifikat&run=overview"
test_endpoint "kassationsanalys run=overview" "$BASE?action=kassationsanalys&run=overview"

# ==========================================
# DRIFTSTATUS / PRODUKTION
# ==========================================
echo ""
echo "=== Driftstatus / Produktion ==="
test_endpoint "status run=all" "$BASE?action=status&run=all" "no"
test_endpoint "produktionspuls run=latest" "$BASE?action=produktionspuls&run=latest" "no"
test_endpoint "rebotlingtrendanalys run=trender" "$BASE?action=rebotlingtrendanalys&run=trender"
test_endpoint "rebotling-stationsdetalj run=kpi-idag" "$BASE?action=rebotling-stationsdetalj&run=kpi-idag"
test_endpoint "effektivitet run=trend" "$BASE?action=effektivitet&run=trend"

# ==========================================
# STOPPORSAKER
# ==========================================
echo ""
echo "=== Stopporsaker ==="
test_endpoint "stopporsak-dashboard run=sammanfattning" "$BASE?action=stopporsak-dashboard&run=sammanfattning"
test_endpoint "stopporsak-trend run=weekly" "$BASE?action=stopporsak-trend&run=weekly"
test_endpoint "stopptidsanalys run=overview" "$BASE?action=stopptidsanalys&run=overview"

# ==========================================
# RESULTAT
# ==========================================
echo ""
echo "==========================================="
echo "RESULTAT"
echo "==========================================="
TOTAL=$((PASS + FAIL + SKIP))
echo -e "Totalt:  $TOTAL tester"
echo -e "${GREEN}PASS:    $PASS${NC}"
echo -e "${RED}FAIL:    $FAIL${NC}"
echo -e "${YELLOW}SKIP:    $SKIP${NC}"

if [ $FAIL -gt 0 ]; then
    echo ""
    echo -e "${RED}Misslyckade tester:${NC}"
    echo -e "$ERRORS"
    echo ""
    exit 1
else
    echo ""
    echo -e "${GREEN}Alla tester passerade!${NC}"
    exit 0
fi
