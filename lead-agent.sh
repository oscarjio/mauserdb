#!/bin/bash
# lead-agent.sh — Ledaragent för mauserdb. Körs av cron.
# Analyserar läget, prioriterar, startar workers som kodar.

export DISABLE_AUTOUPDATER=1
export CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC=1

PROJECT="/home/clawd/clawd/mauserdb"
CLAUDE="/home/clawd/.local/bin/claude"
LOCKFILE="/tmp/mauserdb-lead.lock"
RUNLOG="/tmp/mauserdb-lead.log"
RATELIMIT_FILE="/tmp/mauserdb-ratelimit.txt"
BUDGET_FILE="/tmp/mauserdb-budget.txt"
MAX_LOG_LINES=2000
BUDGET_WINDOW=18000   # 5 timmar i sekunder
MAX_RUNS_PER_WINDOW=3 # färre körningar, mer turns per körning

export PATH="/home/clawd/.local/bin:/home/clawd/.npm-global/bin:/usr/local/bin:/usr/bin:/bin:$PATH"
unset CLAUDECODE

# Förhindra parallella körningar
if [ -f "$LOCKFILE" ]; then
    PID=$(cat "$LOCKFILE")
    if kill -0 "$PID" 2>/dev/null; then
        echo "[$(date '+%Y-%m-%d %H:%M')] Lead-agent körs redan (PID $PID), hoppar över." >> "$RUNLOG"
        exit 0
    fi
    rm -f "$LOCKFILE"
fi
echo $$ > "$LOCKFILE"
trap "rm -f $LOCKFILE" EXIT

# Rotera log
if [ -f "$RUNLOG" ]; then
    LOG_LINES=$(wc -l < "$RUNLOG" 2>/dev/null || echo 0)
    if [ "$LOG_LINES" -gt "$MAX_LOG_LINES" ]; then
        tail -n 500 "$RUNLOG" > "${RUNLOG}.tmp" && mv "${RUNLOG}.tmp" "$RUNLOG"
        echo "[$(date '+%Y-%m-%d %H:%M')] Log roterad" >> "$RUNLOG"
    fi
fi

# Budget-kontroll
NOW=$(date +%s)
WINDOW_START=$((NOW - BUDGET_WINDOW))
touch "$BUDGET_FILE" 2>/dev/null || true
awk -v ws="$WINDOW_START" '$1+0 > ws {print}' "$BUDGET_FILE" > "${BUDGET_FILE}.tmp" 2>/dev/null || true
mv "${BUDGET_FILE}.tmp" "$BUDGET_FILE" 2>/dev/null || true
RUNS_IN_WINDOW=$(wc -l < "$BUDGET_FILE" 2>/dev/null | tr -d ' ' || echo 0)

if [ "$RUNS_IN_WINDOW" -ge "$MAX_RUNS_PER_WINDOW" ]; then
    echo "[$(date '+%Y-%m-%d %H:%M')] BUDGET: $RUNS_IN_WINDOW/$MAX_RUNS_PER_WINDOW i 5h-fönstret — hoppar över." >> "$RUNLOG"
    exit 0
fi

echo "$NOW" >> "$BUDGET_FILE"
echo "[$(date '+%Y-%m-%d %H:%M')] BUDGET: $((RUNS_IN_WINDOW + 1))/$MAX_RUNS_PER_WINDOW. Session startar." >> "$RUNLOG"

cd "$PROJECT"

# Synka från remote
if git status --porcelain 2>/dev/null | grep -q '^[^?]'; then
    git stash push -m "lead-auto-stash-$(date +%s)" >> "$RUNLOG" 2>&1 || true
    git pull --rebase origin main >> "$RUNLOG" 2>&1 || true
    git stash pop >> "$RUNLOG" 2>&1 || true
else
    git pull --rebase origin main >> "$RUNLOG" 2>&1 || true
fi

# Bygg prompt
RECENT_COMMITS=$(git log --oneline -10 2>/dev/null || echo "inga commits")
PROMPT_FILE=$(mktemp /tmp/mauserdb-prompt-XXXXXX.md)

cat > "$PROMPT_FILE" << ENDPROMPT
Du är LEDARAGENTEN för mauserdb — ett produktionssystem (IBC-tvätteri).
Du koordinerar utvecklingen och startar worker-agenter som kodar.

## Senaste commits:
$RECENT_COMMITS

## STEG 1 — Läs kontext (SNABBT — max 3 Read-anrop)
Läs dessa filer med Read:
- /home/clawd/clawd/mauserdb/backlog.md (att-göra-listan — kort fil)
- /home/clawd/clawd/mauserdb/lead-memory.md (regler och status — ~100 rader)
- /home/clawd/clawd/mauserdb/dev-log.md (sista 20 raderna — se vad som senast gjorts)

## STEG 2 — Starta 2 worker-agenter (via Agent-verktyget)
Starta dem PARALLELLT. Ge STORA, meningsfulla uppgifter — sessions körs bara var 2:e timme.
- En specifik uppgift från backlog.md (markera den PÅGÅR)
- Tydliga instruktioner om EXAKT vilka filer de äger (ingen överlapp)
- En worker på ny feature/förbättring, en på buggjakt ELLER annan feature
- Varje worker ska ha max_turns=80 — de ska hinna bygga klart ordentliga features

Varje worker-prompt MÅSTE innehålla dessa regler:
1. Rör ALDRIG: rebotling-live, tvattlinje-live, saglinje-live, klassificeringslinje-live, plcbackend/
2. ALLTID bcrypt — ändra ALDRIG till sha1/md5
3. ALDRIG röra dist/
4. DB-ändringar → SQL i noreko-backend/migrations/ + git add -f
5. All UI-text på svenska
6. Dark theme: #1a202c bg, #2d3748 cards, #e2e8f0 text, Bootstrap 5
7. Lifecycle: OnInit/OnDestroy + destroy$ + takeUntil + clearInterval/clearTimeout
8. Bygg: cd /home/clawd/clawd/mauserdb/noreko-frontend && npx ng build
9. Commit specifika filer (inte git add -A) och push
10. Uppdatera dev-log.md med vad som gjorts

## STEG 3 — Underhåll backlog.md (DITT VIKTIGASTE JOBB)
Backloggen får ALDRIG vara tom. Du bestämmer vad som ska göras.
- Markera pågående items i backlog.md
- Ta bort [x]-markerade items (de är klara)
- Om färre än 5 öppna items → DU MÅSTE fylla på. Så här:
  1. Granska koden — vilka sidor kan förbättras? Vad saknas?
  2. Tänk som VD/operatör — vad behöver de se? Vad saknas i deras vardag?
  3. Buggjakt — leta buggar och lägg som uppgift
  4. Sök inspiration (WebSearch: "OEE dashboard", "manufacturing KPI", "operator bonus gamification")
  5. Identifiera oanvänd data i DB som kan visualiseras
- Håll backlog.md UNDER 40 rader — kort och konkret

## STEG 4 — Uppdatera lead-memory.md (kort)
- Uppdatera BESLUTSDAGBOK (behåll max 3 senaste)
- Håll filen UNDER 120 rader

## STEG 5 — Commit och push
git add backlog.md lead-memory.md dev-log.md
git commit -m "Lead: \$(date '+%Y-%m-%d') session-update"
git push

## ÄGARENS PRIORITERING (2026-03-09):
1. FOKUS REBOTLING — enda linjen med bra data
2. Statistiksidan — enkel överblick produktion över tid
3. Buggjakt löpande
4. VD ska förstå läget på 10 sekunder

## Projektsökväg: /home/clawd/clawd/mauserdb/
ENDPROMPT

echo "[$(date '+%Y-%m-%d %H:%M')] Startar Claude (prompt: $(wc -c < "$PROMPT_FILE") bytes)..." >> "$RUNLOG"

TMPOUT=$(mktemp)
stdbuf -oL $CLAUDE --dangerously-skip-permissions --max-turns 50 --print "$(cat "$PROMPT_FILE")" 2>&1 | stdbuf -oL tee -a "$RUNLOG" > "$TMPOUT"
CLAUDE_EXIT=${PIPESTATUS[0]}
rm -f "$PROMPT_FILE"

echo "[$(date '+%Y-%m-%d %H:%M')] Exit: $CLAUDE_EXIT" >> "$RUNLOG"

if grep -qiE "usage limit|rate limit|out of extra usage|resets [0-9]" "$TMPOUT"; then
    RESET_MSG=$(grep -oiE "resets [^\n]+" "$TMPOUT" | head -1 || echo "okänd tid")
    echo "[$(date '+%Y-%m-%d %H:%M')] RATE LIMIT: $RESET_MSG" >> "$RUNLOG"
    echo "$(date '+%Y-%m-%d %H:%M') - $RESET_MSG" > "$RATELIMIT_FILE"
else
    rm -f "$RATELIMIT_FILE"
    echo "[$(date '+%Y-%m-%d %H:%M')] Session klar." >> "$RUNLOG"
fi
rm -f "$TMPOUT"
