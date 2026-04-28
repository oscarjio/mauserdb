#!/bin/bash
# lead-agent.sh — Mauserdb multi-worker development agent
# Runs every hour. Spawns 2-3 parallel workers: one builds features, one audits/fixes bugs.

cd /home/clawd/clawd/mauserdb

LOGFILE="/home/clawd/clawd/logs/mauserdb_lead_agent.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
LOCKFILE="/tmp/mauserdb_lead_agent.lock"

if [ -f "$LOCKFILE" ]; then
    OLD_PID=$(cat "$LOCKFILE")
    if kill -0 "$OLD_PID" 2>/dev/null; then
        echo "[$TIMESTAMP] Already running (PID $OLD_PID), skipping." >> "$LOGFILE"
        exit 0
    fi
fi
echo $$ > "$LOCKFILE"
trap "rm -f $LOCKFILE" EXIT

echo "" >> "$LOGFILE"
echo "═══════════════════════════════════════" >> "$LOGFILE"
echo "[$TIMESTAMP] lead-agent starting (multi-worker mode)" >> "$LOGFILE"

DEV_LOG=$(tail -n 100 dev-log.md 2>/dev/null || echo "No dev-log.md yet")
GIT_LOG=$(git log --oneline -20 2>/dev/null || echo "No git log")
ROUTES=$(grep "path:" noreko-frontend/src/app/app.routes.ts | grep -v "^//" | head -80)

# ─── WORKER 1: Feature builder ───────────────────────────────────────────────
PROMPT_WORKER1="You are a senior full-stack developer working autonomously on the mauserdb operator-intelligence project at /home/clawd/clawd/mauserdb/.

This is an Angular 20+ + PHP/PDO system for an IBC washing facility. Managers use it to track operators and production.

## ABSOLUTE RULES
- NEVER touch: *-live pages (rebotling-live, tvattlinje-live, saglinje-live, klassificeringslinje-live)
- NEVER touch noreko-plcbackend/
- NEVER add items to the main navigation menu (menu/menu.html) — new features go to the Alla Funktioner page (/funktioner) only
- All passwords: bcrypt only
- Never git add dist/ or .env files
- Build before commit: cd noreko-frontend && npx ng build
- Fix ALL TypeScript errors before committing (warnings OK)
- All UI text in Swedish
- Dark theme: #1a202c bg, #2d3748 cards, #e2e8f0 text, Bootstrap 5
- Components: implements OnInit, OnDestroy + destroy\$ = new Subject<void>() + takeUntil(this.destroy\$) + clearInterval in ngOnDestroy
- HTTP: timeout(5000) + catchError(() => of(null)) + isFetching guard
- New routes: add to app.routes.ts with canActivate: [adminGuard]
- NEW PAGES: Do NOT add to menu/menu.html — routes are accessible via /rebotling/operator-prestation hub or /funktioner
- PDO named params in UNION ALL: unique names (:from1,:from2,:from3 etc.) — PDO forbids duplicates
- Math in Angular templates: add Math = Math; as class property
- IBC/h aggregation: use SUM(ibc_ok)/SUM(drifttid/60) on unique shifts (GROUP BY skiftraknare), NOT average-of-ratios
- All 3 positions work simultaneously on the same shift — ibc_ok is shared, NOT additive per position

## DEPLOY COMMANDS (run after build, both are required)
Frontend: sshpass -p '5vBtkUS6tfLVoAor' rsync -avz --delete noreko-frontend/dist/noreko-frontend/ user@mauserdb.com:/var/www/mauserdb-dev/noreko-frontend/dist/noreko-frontend/ -e 'ssh -o StrictHostKeyChecking=no -p 32546'
Backend:  sshpass -p '5vBtkUS6tfLVoAor' rsync -avz --exclude='db_config.php' noreko-backend/ user@mauserdb.com:/var/www/mauserdb-dev/noreko-backend/ -e 'ssh -o StrictHostKeyChecking=no -p 32546'

## DATABASE (rebotling)
Table: rebotling_skiftrapport
  datum DATE, skiftraknare INT, op1 INT, op2 INT, op3 INT, product_id INT,
  ibc_ok INT, ibc_ej_ok INT, bur_ej_ok INT, totalt INT,
  drifttid INT (minutes), rasttime INT, lopnummer INT, driftstopptime INT, created_at DATETIME
Positions: op1=Tvättplats, op2=Kontrollstation, op3=Truckförare
op1/op2/op3 = operator number → operators table (number, name, active)

## ROUTE HUB — operator-prestation
All operator analysis tools are accessible via /rebotling/operator-prestation hub page.
Existing routes built so far (do NOT rebuild these):
- operator-scores, operator-analys, operator-matcher, shift-dna, operator-trend-heatmap
- team-optimizer, operator-monthly-report, operator-kvartal, operator-performance-map
- operator-aktivitet, operator-compare, bonus-kalkylator, operator-inlarning, operator-varning
- operator-produkt, operator-stopptid, skift-kalender, operator-veckodag, operator-kassation
- skift-prognos, ibc-forlust, operator/:number (profile)

## CURRENT STATE
### dev-log (last 100 lines):
${DEV_LOG}

### Recent commits:
${GIT_LOG}

## YOUR MISSION — pick ONE task and complete it fully

### PRIORITY 1: Fix broken/stub pages
Many pages were scaffolded by the lead agent but may be stubs (empty or non-functional).
Check these pages one by one and if they are stubs/broken, implement them properly:
- operator-trend-heatmap, team-optimizer, operator-monthly-report, operator-aktivitet
- operator-compare, bonus-kalkylator, operator-inlarning, operator-varning
- operator-produkt, operator-stopptid, skift-kalender, operator-veckodag
- operator-kassation, skift-prognos, ibc-forlust
Pick ONE broken/stub page and implement it fully with real backend data.

### PRIORITY 2: New useful features (if all above are working)
Ideas that would genuinely help managers:
- Shift efficiency calendar (heatmap by date, color = IBC/h vs avg)
- Operator head-to-head: pick two operators, see all their shifts on the same chart
- 'Who works best with whom' — team composition analysis
- Position specialty index: which operator's average at each position vs their overall avg

## WORKFLOW
1. Read dev-log.md — find what's NOT yet built or is broken
2. Pick ONE task. Read the relevant source files (backend + frontend).
3. Implement backend method + add routing line (if new endpoint needed)
4. Fix or create Angular page + route in app.routes.ts (NOT in menu.html)
5. Build: cd noreko-frontend && npx ng build — fix ALL errors
6. Deploy both frontend + backend
7. git add <specific files> && git commit -m 'feat/fix: ...' && git push origin main
8. Append to dev-log.md: \$(date +%Y-%m-%d) | TASK | status | notes

Build ONE complete feature end-to-end. Do not stop halfway."

# ─── WORKER 2: Audit & bug fixer ─────────────────────────────────────────────
PROMPT_WORKER2="You are a QA engineer + bug fixer working autonomously on mauserdb at /home/clawd/clawd/mauserdb/.

Angular 20+ + PHP/PDO system for an IBC washing facility.

## ABSOLUTE RULES
- NEVER touch: *-live pages, noreko-plcbackend/
- NEVER add items to menu/menu.html
- All passwords: bcrypt only
- Never git add dist/ or .env files
- Build before commit: cd noreko-frontend && npx ng build

## DEPLOY COMMANDS
Frontend: sshpass -p '5vBtkUS6tfLVoAor' rsync -avz --delete noreko-frontend/dist/noreko-frontend/ user@mauserdb.com:/var/www/mauserdb-dev/noreko-frontend/dist/noreko-frontend/ -e 'ssh -o StrictHostKeyChecking=no -p 32546'
Backend:  sshpass -p '5vBtkUS6tfLVoAor' rsync -avz --exclude='db_config.php' noreko-backend/ user@mauserdb.com:/var/www/mauserdb-dev/noreko-backend/ -e 'ssh -o StrictHostKeyChecking=no -p 32546'

## YOUR MISSION — Audit and fix existing functionality

### AUDIT CHECKLIST — work through this systematically:

**Backend audit** (check each endpoint returns correct JSON):
- Does each PHP controller method handle missing params gracefully?
- Are PDO named params unique in UNION ALL queries? (duplicates cause SQLSTATE[HY093])
- Does every controller use \$this->pdo (NOT \$this->db)?
- Are IBC/h calculations using SUM(ibc)/SUM(min/60) on unique shifts (GROUP BY skiftraknare)?
- Does every catch block use error_log() and return proper JSON error?

**Frontend audit** (check each Angular component):
- Does every component implement OnInit + OnDestroy with destroy\$ + takeUntil?
- Does every HTTP call have timeout(15000) + catchError?
- Are there any references to non-existent fields (ibc_per_h_avg, perf_score, consistency_score, tierKey, scoreBg)?
- Do all charts get destroyed in ngOnDestroy?
- Are there TypeScript errors? Run: cd noreko-frontend && npx ng build 2>&1 | grep error

**Specific known issues to fix:**
1. TypeScript build: run ng build and fix any type errors
2. Check all pages that were recently added (operator-kvartal, operator-performance-map, operator-aktivitet, etc.) — do they actually load data or show empty/error state?
3. Tvättlinje statistik: 'Detaljerad Statistik' should be collapsed by default (user must click to expand) — check if this is implemented
4. Check rebotling-statistik for similar collapse behavior

## WORKFLOW
1. Run: cd noreko-frontend && npx ng build 2>&1 | grep -E 'error|Error' to find TypeScript errors
2. Pick the most impactful bugs to fix
3. Fix them, build, deploy
4. git add + commit + push
5. Append to dev-log.md: \$(date +%Y-%m-%d) | AUDIT | what was fixed | what still needs work

Fix as many bugs as possible. Prioritize breaking bugs over cosmetic ones."

WORKER_TIMEOUT=3600  # 1 hour max per worker

echo "[$TIMESTAMP] Launching Worker 1 (feature builder)..." >> "$LOGFILE"
timeout $WORKER_TIMEOUT /home/clawd/.local/bin/claude \
    --dangerously-skip-permissions \
    --print \
    "$PROMPT_WORKER1" >> "$LOGFILE" 2>&1 &
W1_PID=$!
echo "[$TIMESTAMP] Worker 1 PID: $W1_PID" >> "$LOGFILE"

# Small stagger to avoid git conflicts
sleep 30

echo "[$TIMESTAMP] Launching Worker 2 (audit/bugfix)..." >> "$LOGFILE"
timeout $WORKER_TIMEOUT /home/clawd/.local/bin/claude \
    --dangerously-skip-permissions \
    --print \
    "$PROMPT_WORKER2" >> "$LOGFILE" 2>&1 &
W2_PID=$!
echo "[$TIMESTAMP] Worker 2 PID: $W2_PID" >> "$LOGFILE"

# Wait for both workers (bounded by WORKER_TIMEOUT)
wait $W1_PID
W1_EXIT=$?
wait $W2_PID
W2_EXIT=$?

if [ $W1_EXIT -eq 124 ]; then
    echo "[$TIMESTAMP] Worker 1 TIMED OUT after ${WORKER_TIMEOUT}s" >> "$LOGFILE"
fi
if [ $W2_EXIT -eq 124 ]; then
    echo "[$TIMESTAMP] Worker 2 TIMED OUT after ${WORKER_TIMEOUT}s" >> "$LOGFILE"
fi
echo "[$TIMESTAMP] Workers done (W1 exit: $W1_EXIT, W2 exit: $W2_EXIT)" >> "$LOGFILE"
