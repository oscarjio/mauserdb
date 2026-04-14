#!/bin/bash
# lead-agent.sh — Autonomous mauserdb UI improvement agent
# Runs 6 times daily: 06:00, 09:00, 12:00, 15:00, 18:00, 21:00

cd /home/clawd/clawd/mauserdb

LOGFILE="/home/clawd/clawd/logs/mauserdb_lead_agent.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

echo "" >> "$LOGFILE"
echo "═══════════════════════════════════════" >> "$LOGFILE"
echo "[$TIMESTAMP] lead-agent starting" >> "$LOGFILE"

DEV_LOG=$(tail -n 100 dev-log.md 2>/dev/null || echo "No dev-log.md yet")
GIT_LOG=$(git log --oneline -20 2>/dev/null || echo "No git log")

PROMPT="You are a senior full-stack UI developer working autonomously on the mauserdb project at /home/clawd/clawd/mauserdb/.

This is an Angular 20+ + PHP monitoring system for an IBC washing/rebotling facility. Operators and managers use this daily to track production.

## ABSOLUTE RULES
- NEVER touch: tvattlinje-live, rebotling-live, saglinje-live, klassificeringslinje-live
- All passwords use bcrypt (never change auth code)
- Never git add dist/ or .env files
- Build ALWAYS before committing: cd noreko-frontend && npx ng build
- Fix ALL TypeScript errors before committing (warnings OK)
- Push to GitHub: git push origin main
- Write to dev-log.md after every session
- All UI text in Swedish

## CURRENT STATE
### Recent dev-log:
${DEV_LOG}

### Recent commits:
${GIT_LOG}

## YOUR MISSION — UI/UX QUALITY

Make the production statistics and shift reports genuinely useful and visually impressive. Think: a production manager opens this dashboard and immediately understands what's happening on the floor.

### Priority focus areas:

**1. tvattlinje-statistik** — Make it visually excellent:
   - Large, clear KPI cards at the top: IBC idag, Effektivitet %, Stopptid, OEE
   - Color-coded status: green (≥85% OEE), yellow (60-85%), red (<60%)
   - Production trend chart: smooth line chart showing IBC/hour over the shift
   - Stoppage breakdown: horizontal bar chart showing top stop reasons with time
   - Shift comparison: today vs yesterday vs week average
   - All charts should use Chart.js with the dark theme (#1a202c bg, #2d3748 cards)

**2. tvattlinje-skiftrapport** — Enhance the shift report view:
   - Clear shift summary header with large numbers
   - Timeline visualization showing when machine was running vs stopped
   - Downloadable PDF with professional layout (logo, date, summary table)
   - Color indicators: ok=green, ej_ok=red, omtvätt=yellow

**3. rebotling-statistik** — Check and fix any remaining issues:
   - Verify all charts render correctly
   - Fix any broken endpoints or missing data
   - Ensure the efficiency calculation is correct

### Design principles:
- Dark theme throughout: bg #1a202c, cards #2d3748, text #e2e8f0
- Use Bootstrap 5 grid for layout
- Big numbers for KPIs (font-size 2-3rem)
- Subtle animations on load (CSS transitions)
- Mobile-friendly (responsive grid)
- Status dots/badges: 🟢🟡🔴 or Bootstrap badges
- Charts: Chart.js with matching dark colors

### Technical patterns (MUST follow):
- Components: implements OnInit, OnDestroy + destroy$ = new Subject<void>() + takeUntil(this.destroy$)
- HTTP: timeout(5000) + catchError + isFetching guard
- Math in templates: add Math = Math; as class property
- API calls: api.php?action=tvattlinje&run=<method> or api.php?action=rebotling&run=<method>

## WORKFLOW
1. Read dev-log.md — find the most impactful UI improvement not yet done
2. Read the current state of the target component (ts + html + css)
3. Implement the improvement
4. Run: cd noreko-frontend && npx ng build — fix all TypeScript errors
5. git add <specific files> && git commit -m 'feat/fix: description' && git push origin main
6. Append to dev-log.md: what you did, what's next

Read DESIGN_GUIDE.md first — it is the law for all UI work. Then implement the highest-priority improvement not yet done. Focus: simple primary view, chart view-switcher (dag/vecka/månad + linje/stapel), hide details under tabs or "Visa avancerat" button. Do not add more KPI cards — simplify if anything. Build, fix errors, commit, push, update dev-log.md.

echo "[$TIMESTAMP] Running Claude CLI..." >> "$LOGFILE"

/home/clawd/.local/bin/claude \
    --dangerously-skip-permissions \
    --print \
    "$PROMPT" >> "$LOGFILE" 2>&1

EXIT_CODE=$?
echo "[$TIMESTAMP] lead-agent done (exit: $EXIT_CODE)" >> "$LOGFILE"
