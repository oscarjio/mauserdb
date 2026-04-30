#!/bin/bash
# lead-agent.sh — Mauserdb single-worker agent (token-effektivt läge)
# Runs every 3h. One focused worker per run.

cd /home/clawd/clawd/mauserdb

LOGFILE="/home/clawd/clawd/logs/mauserdb_lead_agent.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
LOCKFILE="/tmp/mauserdb_lead_agent.lock"

if [ -f "$LOCKFILE" ]; then
    AGE=$(( $(date +%s) - $(stat -c %Y "$LOCKFILE") ))
    if [ "$AGE" -lt 5400 ]; then
        echo "[$TIMESTAMP] Already running (lock age ${AGE}s), skipping." >> "$LOGFILE"
        exit 0
    fi
    rm -f "$LOCKFILE"
fi
echo $$ > "$LOCKFILE"
trap "rm -f $LOCKFILE" EXIT

echo "" >> "$LOGFILE"
echo "═══════════════════════════════════════" >> "$LOGFILE"
echo "[$TIMESTAMP] lead-agent starting" >> "$LOGFILE"

DEV_LOG=$(tail -n 20 dev-log.md 2>/dev/null || echo "No dev-log.md")
GIT_LOG=$(git log --oneline -10 2>/dev/null || echo "No git log")

PROMPT="You are a full-stack developer + QA engineer working autonomously on mauserdb at /home/clawd/clawd/mauserdb/.
Angular 20+ + PHP/PDO system for an IBC washing facility. Stack: Angular 20, PHP 8.2, MySQL.

## ABSOLUTE RULES
- NEVER touch: *-live pages, noreko-plcbackend/
- NEVER add items to menu/menu.html (use /funktioner hub only)
- All passwords: bcrypt only. Never git add dist/ or .env files
- Build before commit: cd noreko-frontend && npx ng build (fix ALL type errors)
- All UI text in Swedish, dark theme #1a202c/#2d3748/#e2e8f0, Bootstrap 5
- Components: OnInit+OnDestroy, destroy\$+takeUntil, timeout(15000)+catchError
- IBC/h: SUM(ibc_ok)/SUM(drifttid/60) on unique shifts (GROUP BY skiftraknare)
- PDO UNION ALL: unique named params (:from1,:from2,:from3 etc.)

## DEPLOY COMMANDS
Frontend: sshpass -p '5vBtkUS6tfLVoAor' rsync -avz --delete noreko-frontend/dist/noreko-frontend/ user@mauserdb.com:/var/www/mauserdb-dev/noreko-frontend/dist/noreko-frontend/ -e 'ssh -o StrictHostKeyChecking=no -p 32546'
Backend:  sshpass -p '5vBtkUS6tfLVoAor' rsync -avz --exclude='db_config.php' noreko-backend/ user@mauserdb.com:/var/www/mauserdb-dev/noreko-backend/ -e 'ssh -o StrictHostKeyChecking=no -p 32546'

## DATABASE
rebotling_skiftrapport: datum, skiftraknare, op1, op2, op3, ibc_ok, drifttid (min), rasttime, driftstopptime
Positions: op1=Tvättplats, op2=Kontrollstation, op3=Truckförare
op1/op2/op3 = operators.number

## PRIORITY TASKS (from lead-memory.md — read it for full details)

### PRIO 0 — DEPLOY FRONTEND (dist byggt lokalt, ej på servern)
Run the frontend rsync deploy command above. This fixes:
- Login-knapp ej klickbar (commit cc11bcd7)
- Operatorsportal toast-spam (commit e19fcc62)
- operator-inlarning fryser (stale dist saknar chunk)
- Funktioner-cards 404 (stale dist saknar chunk-filer)

### PRIO 1 — ibc_count-fixar (5 controllers visar fel IBC-siffror)
Correct pattern: SELECT DATE(datum) AS dag, MAX(ibc_count) AS day_total FROM rebotling_ibc GROUP BY DATE(datum) → SUM day_total i PHP
Fix these controllers (read lead-memory.md for details):
- RebotlingTrendanalysController: hamtaDagligData() + veckosammanfattning()
- RebotlingSammanfattningController: getOverview() + getProduktion7d()
- MorgonrapportController: 5+ metoder
- KvalitetstrendanalysController: hamtaDagarData() + hamtaVeckoData()
- RebotlingStationsdetaljController: getIbcData()

### PRIO 2 — Övriga buggar (se lead-memory.md)
- ibc-forlust chart tom (Chart.js global declare → proper import)
- Op 444 utan namn (COALESCE fallback i getOperatorScores etc.)

## CURRENT STATE
### dev-log (senaste 20 rader):
${DEV_LOG}

### Recent commits:
${GIT_LOG}

## WORKFLOW
1. Read lead-memory.md for full bug details
2. Start with PRIO 0 (deploy) — it takes 1 minute and fixes 4 bugs instantly
3. Then pick ONE PRIO 1 controller and fix ibc_count aggregation
4. Build + deploy both frontend + backend
5. git add <specific files> && git commit -m 'fix: ...' && git push origin main
6. Append one line to dev-log.md: \$(date +%Y-%m-%d) | BUGFIX | what fixed | status

Do NOT build new features. Focus on fixing confirmed bugs from the backlog."

WORKER_TIMEOUT=1800  # 30 min max

echo "[$TIMESTAMP] Launching worker..." >> "$LOGFILE"
timeout $WORKER_TIMEOUT /home/clawd/.local/bin/claude \
    --dangerously-skip-permissions \
    --print \
    "$PROMPT" >> "$LOGFILE" 2>&1
EXIT_CODE=$?

if [ $EXIT_CODE -eq 124 ]; then
    echo "[$TIMESTAMP] Worker TIMED OUT after ${WORKER_TIMEOUT}s" >> "$LOGFILE"
fi
echo "[$TIMESTAMP] Worker done (exit: $EXIT_CODE)" >> "$LOGFILE"
