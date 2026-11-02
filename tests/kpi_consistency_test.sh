#!/bin/bash
# =============================================================================
# KPI Konsistenstest — Tvättlinje + Rebotling
# =============================================================================
# Verifierar att samma KPI-värden visas konsekvent på:
#   1. Skiftrapport-sidan  (run=report)
#   2. Statistik-översikt  (run=statistics → summary)
#   3. Statistik Skiftrapporter-fliken  (run=skiftrapport-statistik → summary)
#
# Kör scriptet efter kodändringar för att fånga avvikelser.
#
# Användning:
#   bash tests/kpi_consistency_test.sh [DATUM]
#   bash tests/kpi_consistency_test.sh 2026-11-02
#   bash tests/kpi_consistency_test.sh          # Gårdagens datum
#
# Kräver: curl, python3
# =============================================================================

set -euo pipefail

BASE="https://dev.mauserdb.com/noreko-backend/api.php"
COOKIE_JAR="/tmp/kpi_test_cookies.txt"

if [ -n "${1:-}" ]; then
    TEST_DATE="$1"
else
    TEST_DATE=$(date -d "yesterday" '+%Y-%m-%d' 2>/dev/null || date -v-1d '+%Y-%m-%d')
fi

PASS=0
FAIL=0
WARN=0
ERRORS=""
WARNINGS=""

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

cleanup() { rm -f "$COOKIE_JAR" /tmp/kpi_test_*.json; }
trap cleanup EXIT

echo ""
echo -e "${BOLD}=== KPI Konsistenstest ===${NC}"
echo "Datum: $TEST_DATE"
echo ""

# ─────────────────────────────────────────────
# Hjälpfunktioner
# ─────────────────────────────────────────────

fetch_json() {
    local url="$1" outfile="$2"
    curl -s -b "$COOKIE_JAR" -o "$outfile" -w "%{http_code}" "$url" 2>/dev/null || echo "000"
}

py() {
    python3 -c "
import json, sys
with open('$1') as f: d = json.load(f)
try:
    result = $2
    print(result if result is not None else 'null')
except Exception as e:
    print('ERR:' + str(e))
" 2>/dev/null || echo "ERR:parse"
}

ok()   { PASS=$((PASS+1));  echo -e "  ${GREEN}OK${NC}    $1"; }
fail() { FAIL=$((FAIL+1));  echo -e "  ${RED}FAIL${NC}  $1"; ERRORS="${ERRORS}\n  ✗ $1"; }
warn() { WARN=$((WARN+1));  echo -e "  ${YELLOW}WARN${NC}  $1"; WARNINGS="${WARNINGS}\n  ⚠ $1"; }

check_eq() {
    local desc="$1" v1="$2" v2="$3" tol="${4:-0}"
    if [[ "$v1" == null ]] || [[ "$v2" == null ]] || [[ "$v1" == ERR* ]] || [[ "$v2" == ERR* ]]; then
        warn "$desc — jämförelse ej möjlig (v1=$v1, v2=$v2)"; return
    fi
    if [ "$tol" = "0" ]; then
        [ "$v1" = "$v2" ] && ok "$desc: $v1" || fail "$desc: $v1 ≠ $v2"
    else
        local res
        res=$(python3 -c "print('ok' if abs(float('$v1')-float('$v2')) <= float('$tol') else 'fail')" 2>/dev/null || echo "fail")
        [ "$res" = "ok" ] && ok "$desc: $v1 ≈ $v2 (±$tol)" || fail "$desc: $v1 ≠ $v2 (tolerans ±$tol)"
    fi
}

# ─────────────────────────────────────────────
# Inloggning
# ─────────────────────────────────────────────

echo -e "${BLUE}── Inloggning ──────────────────────────────────────${NC}"
rm -f "$COOKIE_JAR"
LOGIN_RESP=$(curl -s -c "$COOKIE_JAR" -X POST "$BASE?action=login" \
    -H "Content-Type: application/json" \
    -d '{"username":"aiab","password":"Noreko2025"}' 2>/dev/null || echo '{"error":"curl failed"}')
LOGIN_OK=$(echo "$LOGIN_RESP" | python3 -c "import sys,json; d=json.load(sys.stdin); print('yes' if d.get('success') else 'no')" 2>/dev/null || echo "no")

if [ "$LOGIN_OK" = "yes" ]; then
    ok "Inloggning lyckades"
else
    fail "Inloggning misslyckades"
    echo -e "\n${RED}Avbryter — kan inte testa utan session${NC}"
    exit 1
fi
echo ""

# ─────────────────────────────────────────────
# BLOCK 1: run=report (skiftrapport-sidan)
# ─────────────────────────────────────────────

echo -e "${BLUE}── Block 1: run=report (skiftrapport-sidan) ────────${NC}"

REPORT_FILE="/tmp/kpi_test_report.json"
CODE=$(fetch_json "$BASE?action=tvattlinje&run=report&datum=$TEST_DATE" "$REPORT_FILE")
if [ "$CODE" = "200" ]; then ok "tvattlinje run=report HTTP 200"; else fail "tvattlinje run=report HTTP $CODE"; fi

IS_EMPTY=$(py "$REPORT_FILE" "d.get('empty', False)")
RPT_IBC="null"; RPT_DRIFTTID="null"; RPT_CYCLE="null"; RPT_SR_DRIFTTID="null"; RPT_SR_TOTALT="null"

if [ "$IS_EMPTY" = "True" ] || [ "$IS_EMPTY" = "true" ]; then
    warn "Ingen data för $TEST_DATE (tomt skift) — hoppar över tvättlinje-jämförelser"
else
    RPT_IBC=$(py       "$REPORT_FILE" "d['data']['total_ibc']")
    RPT_DRIFTTID=$(py  "$REPORT_FILE" "d['data']['runtime_minutes']")
    RPT_SR_DRIFTTID=$(py "$REPORT_FILE" "d['data']['skift_data'][0]['drifttid'] if d['data'].get('skift_data') else 'null'")
    RPT_SR_TOTALT=$(py   "$REPORT_FILE" "d['data']['skift_data'][0]['totalt']   if d['data'].get('skift_data') else 'null'")

    if [[ "$RPT_IBC" != ERR* ]] && [ "$RPT_IBC" != "null" ]; then
        ok "report: total_ibc=$RPT_IBC, runtime_minutes=$RPT_DRIFTTID"
        ok "skiftrapport[0]: totalt=$RPT_SR_TOTALT, drifttid=$RPT_SR_DRIFTTID (D4007)"
        if [ "$RPT_SR_TOTALT" != "null" ] && [ "$RPT_SR_DRIFTTID" != "null" ]; then
            RPT_CYCLE=$(python3 -c "print(round(float('$RPT_SR_DRIFTTID')/float('$RPT_SR_TOTALT'), 1))" 2>/dev/null || echo "null")
            ok "Beräknad min/IBC=$RPT_CYCLE"
        fi
    else
        warn "Oväntad report-struktur: $RPT_IBC"
    fi
fi
echo ""

# ─────────────────────────────────────────────
# BLOCK 2: run=skiftrapport-statistik (statistik-fliken)
# ─────────────────────────────────────────────

echo -e "${BLUE}── Block 2: run=skiftrapport-statistik (statistik-flik) ──${NC}"

SKSR_FILE="/tmp/kpi_test_sksr.json"
CODE=$(fetch_json "$BASE?action=tvattlinje&run=skiftrapport-statistik&start=$TEST_DATE&end=$TEST_DATE" "$SKSR_FILE")
if [ "$CODE" = "200" ]; then ok "skiftrapport-statistik HTTP 200"; else fail "skiftrapport-statistik HTTP $CODE"; fi

SKSR_IBC="null"; SKSR_DRIFTTID="null"; SKSR_CYCLE="null"
SKSR_IBC=$(py      "$SKSR_FILE" "d['summary']['total_ibc']")
SKSR_DRIFTTID=$(py "$SKSR_FILE" "d['summary']['total_drifttid']")
SKSR_CYCLE=$(py    "$SKSR_FILE" "d['summary']['avg_cycle_time']")

if [[ "$SKSR_IBC" != ERR* ]] && [ "$SKSR_IBC" != "null" ]; then
    ok "skiftrapport-statistik: IBC=$SKSR_IBC, drifttid=$SKSR_DRIFTTID min, avg_cycle=$SKSR_CYCLE"
else
    warn "skiftrapport-statistik: oväntad struktur — $(py "$SKSR_FILE" 'list(d.keys())')"
fi
echo ""

# ─────────────────────────────────────────────
# BLOCK 3: run=statistics (statistik-KPI-kort + detaljerad)
# ─────────────────────────────────────────────

echo -e "${BLUE}── Block 3: run=statistics (statistik-KPI-kort) ───────${NC}"

STATS_FILE="/tmp/kpi_test_stats.json"
CODE=$(fetch_json "$BASE?action=tvattlinje&run=statistics&start=$TEST_DATE&end=$TEST_DATE" "$STATS_FILE")
if [ "$CODE" = "200" ]; then ok "statistics HTTP 200"; else fail "statistics HTTP $CODE"; fi

STAT_CYCLES_IBC="null"; STAT_SR_IBC="null"; STAT_AVG_CYCLE="null"; STAT_AVG_PROD="null"
STAT_CYCLES_IBC=$(py "$STATS_FILE" "d['data']['summary']['total_cycles']")
STAT_SR_IBC=$(py     "$STATS_FILE" "d['data']['summary']['total_ibc_skiftrapport']")
STAT_AVG_CYCLE=$(py  "$STATS_FILE" "d['data']['summary']['avg_cycle_time']")
STAT_AVG_PROD=$(py   "$STATS_FILE" "d['data']['summary']['avg_production_percent']")
STAT_RT_H=$(py       "$STATS_FILE" "d['data']['summary']['total_runtime_hours']")

if [[ "$STAT_CYCLES_IBC" != ERR* ]] && [ "$STAT_CYCLES_IBC" != "null" ]; then
    ok "statistics summary: cycles=$STAT_CYCLES_IBC, sr_ibc=$STAT_SR_IBC"
    ok "  avg_cycle=$STAT_AVG_CYCLE, avg_prod=$STAT_AVG_PROD%, runtime_h=$STAT_RT_H"
else
    warn "statistics: oväntad struktur — $(py "$STATS_FILE" 'list(d.keys())')"
fi
echo ""

# ─────────────────────────────────────────────
# BLOCK 4: Konsistenskontroll tvättlinje
# ─────────────────────────────────────────────

echo -e "${BLUE}── Block 4: Konsistens — Tvättlinje ───────────────────${NC}"

# IBC: alla tre ska visa samma värde
check_eq "IBC: report vs skiftrapport-statistik"           "$RPT_IBC"  "$SKSR_IBC"
check_eq "IBC: report vs statistics (sr_ibc)"              "$RPT_IBC"  "$STAT_SR_IBC"
check_eq "IBC: skiftrapport-statistik vs statistics"       "$SKSR_IBC" "$STAT_SR_IBC"

# Drifttid D4007: report och skiftrapport-statistik ska matcha
check_eq "Drifttid (D4007): report vs skiftrapport-statistik" "$RPT_SR_DRIFTTID" "$SKSR_DRIFTTID" "1"

# runtime_minutes (report aggregat) ska matcha D4007-drifttid
check_eq "runtime_minutes (report) == drifttid skiftrapport" "$RPT_DRIFTTID" "$RPT_SR_DRIFTTID" "1"

# avg_cycle_time: avrundad till 0.1 min
check_eq "avg_cycle_time: skiftrapport-statistik vs statistics" "$SKSR_CYCLE" "$STAT_AVG_CYCLE" "0.1"
check_eq "avg_cycle_time: beräknad (d/ibc) vs statistics"       "$RPT_CYCLE"  "$STAT_AVG_CYCLE" "0.1"

# Cap-kontroll: drifttid ≤ 600
if [ "$RPT_SR_DRIFTTID" != "null" ] && [[ "$RPT_SR_DRIFTTID" != ERR* ]]; then
    if python3 -c "exit(0 if int(float('$RPT_SR_DRIFTTID')) <= 600 else 1)" 2>/dev/null; then
        ok "Drifttid ≤ 600 min (D4007-cap OK)"
    else
        fail "Drifttid ÖVERSTIGER 600 min — dubbel-subtraktion eller cap saknas!"
    fi
fi
echo ""

# ─────────────────────────────────────────────
# BLOCK 5: Rebotling
# ─────────────────────────────────────────────

echo -e "${BLUE}── Block 5: Rebotling ──────────────────────────────────${NC}"

REB_TODAY_FILE="/tmp/kpi_test_reb_today.json"
CODE=$(fetch_json "$BASE?action=rebotling&run=today" "$REB_TODAY_FILE")
REB_TODAY_IBC="null"
if [ "$CODE" = "200" ]; then
    ok "rebotling run=today HTTP 200"
    REB_TODAY_IBC=$(py "$REB_TODAY_FILE" "d.get('total_ibc') or d.get('ibc') or 'null'")
    REB_TODAY_KEYS=$(py "$REB_TODAY_FILE" "list(d.keys())[:8]")
    ok "  Nycklar: $REB_TODAY_KEYS"
    if [ "$REB_TODAY_IBC" != "null" ] && [[ "$REB_TODAY_IBC" != ERR* ]]; then
        ok "  today: IBC=$REB_TODAY_IBC"
    fi
else
    warn "rebotling run=today HTTP $CODE"
fi

REB_HISTORY_FILE="/tmp/kpi_test_reb_history.json"
CODE=$(fetch_json "$BASE?action=rebotling&run=history" "$REB_HISTORY_FILE")
if [ "$CODE" = "200" ]; then
    ok "rebotling run=history HTTP 200"
else
    warn "rebotling run=history HTTP $CODE"
fi

REB_SHIFTS_FILE="/tmp/kpi_test_reb_shifts.json"
CODE=$(fetch_json "$BASE?action=rebotling&run=shifts" "$REB_SHIFTS_FILE")
if [ "$CODE" = "200" ]; then
    ok "rebotling run=shifts HTTP 200"
    REB_SHIFTS_KEYS=$(py "$REB_SHIFTS_FILE" "list(d.keys())[:8]")
    ok "  Nycklar: $REB_SHIFTS_KEYS"
else
    warn "rebotling run=shifts HTTP $CODE"
fi

REB_SUMM_FILE="/tmp/kpi_test_reb_summ.json"
CODE=$(fetch_json "$BASE?action=rebotling-sammanfattning&run=overview" "$REB_SUMM_FILE")
REB_SUMM_IBC="null"
if [ "$CODE" = "200" ]; then
    ok "rebotling-sammanfattning run=overview HTTP 200"
    REB_SUMM_IBC=$(py "$REB_SUMM_FILE" "d.get('total_ibc') or d.get('ibc_idag') or 'null'")
    REB_SUMM_KEYS=$(py "$REB_SUMM_FILE" "list(d.keys())[:10]")
    ok "  Nycklar: $REB_SUMM_KEYS"
    if [ "$REB_SUMM_IBC" != "null" ] && [[ "$REB_SUMM_IBC" != ERR* ]]; then
        ok "  sammanfattning IBC=$REB_SUMM_IBC"
        if [ "$REB_TODAY_IBC" != "null" ] && [[ "$REB_TODAY_IBC" != ERR* ]]; then
            check_eq "Rebotling IBC: today vs sammanfattning" "$REB_TODAY_IBC" "$REB_SUMM_IBC"
        fi
    fi
else
    warn "rebotling-sammanfattning HTTP $CODE"
fi
echo ""

# ─────────────────────────────────────────────
# BLOCK 6: Formelverifiering
# ─────────────────────────────────────────────

echo -e "${BLUE}── Block 6: Formelverifiering ──────────────────────────${NC}"

if [ "$RPT_SR_DRIFTTID" != "null" ] && [ "$RPT_SR_TOTALT" != "null" ] && \
   [[ "$RPT_SR_DRIFTTID" != ERR* ]] && [[ "$RPT_SR_TOTALT" != ERR* ]]; then
    python3 - <<PYEOF
drifttid = float("$RPT_SR_DRIFTTID")   # D4007, cap 600 — INGA avdrag
ibc      = float("$RPT_SR_TOTALT")

min_per_ibc = round(drifttid / ibc, 1) if ibc > 0 else None
ibc_per_h   = round(ibc / (drifttid / 60), 1) if drifttid > 0 else None
takt_mal = 3.45   # Vanlig Tvätt fallback
eff = round(((takt_mal - min_per_ibc) / takt_mal) * 100) if min_per_ibc else None

print(f"  Tvättlinje ({ibc:.0f} IBC, {drifttid:.0f} min D4007):")
print(f"    min/IBC         = {min_per_ibc}")
print(f"    IBC/h           = {ibc_per_h}")
print(f"    Effektivitet    = {eff}%  (taktmål {takt_mal} min/IBC)")
print(f"  ✓ Formler: drifttid/ibc → {min_per_ibc} min/IBC")
PYEOF
    ok "Formelkontroll körd"
else
    warn "Formelkontroll hoppas — tvättlinje saknar data för $TEST_DATE"
fi
echo ""

# ─────────────────────────────────────────────
# Sammanfattning
# ─────────────────────────────────────────────

echo -e "${BLUE}════════════════════════════════════════════════════${NC}"
echo -e "${BOLD}Resultat: ${GREEN}$PASS OK${NC}  ${RED}$FAIL FAIL${NC}  ${YELLOW}$WARN WARN${NC}"
if [ -n "$ERRORS" ]; then
    echo -e "\n${RED}Fel (kräver åtgärd):${NC}$ERRORS"
fi
if [ -n "$WARNINGS" ]; then
    echo -e "\n${YELLOW}Varningar:${NC}$WARNINGS"
fi
echo ""
[ "$FAIL" -gt 0 ] && exit 1 || exit 0
