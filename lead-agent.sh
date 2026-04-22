#!/bin/bash
# lead-agent.sh — Mauserdb operator-intelligence agent
# Runs every hour. One focused worker per run to stay within ~50% of token budget.

cd /home/clawd/clawd/mauserdb

LOGFILE="/home/clawd/clawd/logs/mauserdb_lead_agent.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
LOCKFILE="/tmp/mauserdb_lead_agent.lock"

# Prevent overlapping runs
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
echo "[$TIMESTAMP] lead-agent starting" >> "$LOGFILE"

DEV_LOG=$(tail -n 80 dev-log.md 2>/dev/null || echo "No dev-log.md yet")
GIT_LOG=$(git log --oneline -15 2>/dev/null || echo "No git log")

PROMPT="You are a senior full-stack developer working autonomously on the mauserdb operator-intelligence project at /home/clawd/clawd/mauserdb/.

This is an Angular 20+ + PHP/PDO system for an IBC washing facility. Managers use it to track operators and production.

## ABSOLUTE RULES
- NEVER touch: *-live pages (rebotling-live, tvattlinje-live, saglinje-live, klassificeringslinje-live)
- NEVER touch noreko-plcbackend/
- All passwords: bcrypt only
- Never git add dist/ or .env files
- Build before commit: cd noreko-frontend && npx ng build
- Fix ALL TypeScript errors before committing (warnings OK)
- All UI text in Swedish
- Dark theme: #1a202c bg, #2d3748 cards, #e2e8f0 text, Bootstrap 5
- Components: implements OnInit, OnDestroy + destroy\$ = new Subject<void>() + takeUntil(this.destroy\$) + clearInterval in ngOnDestroy
- HTTP: timeout(5000) + catchError(() => of(null)) + isFetching guard
- New routes: add to app.routes.ts with canActivate: [adminGuard]
- New pages: add menu item to menu/menu.html under Rebotling admin-section (after 'Operatörsanalys' line)
- PDO named params in UNION ALL: unique names (:from1,:from2,:from3 etc.) — PDO forbids duplicates
- Math in Angular templates: add Math = Math; as class property

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

## ALREADY BUILT — DO NOT REBUILD
- /rebotling/operator-analys — Period A vs B comparison (OperatorAnalysPage)
  API: ?action=rebotling&run=operator-analys (getOperatorAnalys, queryOperatorPeriodStats, queryOperatorWeeklyTrend)

## CURRENT STATE
### dev-log (last 80 lines):
${DEV_LOG}

### Recent commits:
${GIT_LOG}

## YOUR MISSION — OPERATOR INTELLIGENCE (pick ONE unfinished feature and build it fully)

The owner wants to identify which operators perform well vs. which ones don't.
Goal: managers should quickly see who to schedule and who deserves bonus.

### FEATURE BACKLOG (build in order, skip if already done):

**1. /rebotling/operator-scores — Reliability Score cards**
Backend endpoint: ?action=rebotling&run=operator-scores
- Query last 90 days. For each operator, per-position and overall:
  * ibc_per_h: SUM(ibc_ok)/(SUM(drifttid)/60.0)
  * vs_team: their ibc_per_h / team_avg_ibc_per_h_at_same_position * 100 (index)
  * consistency: compute per-shift IBC/h, then 100 - (stddev/avg*100) capped 0-100
  * trend: slope of weekly IBC/h (last 8 weeks) normalized to -50..+50 range
  * score: vs_team*0.5 + consistency*0.3 + (trend+50)*0.2, capped 0-100
  * rating: Elite(≥75), Solid(50-74), Developing(25-49), NeedsAttention(<25)
  * antal_skift, best_shift_ibc_h, worst_shift_ibc_h
- Minimum 3 shifts to include operator. Return sorted by score desc.
Angular page OperatorScoresPage:
- 4 summary badges at top: count per tier
- Cards grid (auto-fill minmax(260px,1fr)): one card per operator
  Card: name, rating badge (green/blue/yellow/red), score number, IBC/h vs snitt arrow, konsistens%, trend arrow, skift count
- sortBy controls: score | ibc_per_h | name
- Colors: Elite #68d391, Solid #63b3ed, Developing #f6ad55, NeedsAttention #fc8181

**2. /rebotling/operator-matcher — Scheduling Matrix**
Backend endpoint: ?action=rebotling&run=operator-matcher&days=30
- For each operator, per position (op1/op2/op3):
  * avg_ibc_per_h, antal_skift
  * rating: 'top'(≥110% team avg), 'avg'(90-109%), 'below'(<90%), 'none'(0 shifts)
- Return: { operators: [...sorted by name], team_avg: { op1, op2, op3 } }
Angular page OperatorMatcherPage:
- Days selector: 14/30/60/90
- Matrix table: operators as rows, 3 positions as columns
  Cells: colored chip with IBC/h and shift count. top=green, avg=blue, below=red, none=gray
- Team average row at bottom with different style
- Summary below: 'Bäst på Tvättplats: Ted (22.5 IBC/h)'
- Key UX: manager picks next shift team in 30 seconds by scanning colors

**3. /rebotling/shift-dna — Shift fingerprint feed**
Backend endpoint: ?action=rebotling&run=shift-dna&limit=50&offset=0
- Return last N shifts with: datum, skiftraknare, operator names (join operators table),
  ibc_ok, ibc_per_h (=ibc_ok/(drifttid/60.0)), vs_team_avg, drifttid, product_id
  Mark each shift: 'great'(≥120% avg), 'good'(105-119%), 'avg'(90-104%), 'weak'(70-89%), 'poor'(<70%)
Angular page ShiftDnaPage:
- List/feed of recent shifts, newest first
- Each shift row shows: date, shift#, operator badges (colored by name), IBC/h, vs-avg indicator, runtime
- Shift rating color strip on left (green/blue/gray/yellow/red)
- Click on shift to expand: show all details including stop time, kassation
- Filter by operator (select dropdown) and rating

**4. /rebotling/operator/:number — Operator Profile**
Backend endpoint: ?action=rebotling&run=operator-profile&op=NUMBER
- All shifts for operator (as op1, op2, or op3), last 6 months
- Per shift: datum, pos, ibc_ok, ibc_per_h, vs_team_avg_at_pos
- Summary: avg_ibc_h, best_shift, worst_shift, most_common_pos, attendance_days
Angular page OperatorProfilePage with route param :number
- Scatter chart: all shifts as dots (x=date, y=IBC/h, color=position)
- Running average line
- Position breakdown tabs
- Personal bests section
- 'Effect on team': their avg vs team avg when they work vs when they don't

## WORKFLOW
1. Read dev-log.md — find first feature from backlog NOT yet built
2. Read DESIGN_GUIDE.md for UI rules
3. Read noreko-backend/classes/RebotlingController.php (bottom of file for context on existing methods)
4. Implement backend method + add routing line
5. Create src/app/pages/<name>/<name>.ts + .html + .css
6. Add route to app.routes.ts + menu item to menu/menu.html
7. Build: cd noreko-frontend && npx ng build — fix all errors
8. Deploy both frontend + backend (commands above)
9. Test backend via PHP CLI: ssh to dev and run php include test
10. git add <specific files> && git commit -m 'feat: ...' && git push origin main
11. Append one line to dev-log.md: $(date +%Y-%m-%d) | FEATURE NAME | status | what's next

Build ONE complete feature end-to-end. Do not stop halfway. If you hit a blocker, document it in dev-log.md and move to the next feature."

echo "[$TIMESTAMP] Running worker agent..." >> "$LOGFILE"

/home/clawd/.local/bin/claude \
    --dangerously-skip-permissions \
    --print \
    "$PROMPT" >> "$LOGFILE" 2>&1

EXIT_CODE=$?
echo "[$TIMESTAMP] Worker done (exit: $EXIT_CODE)" >> "$LOGFILE"
