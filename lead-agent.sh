#!/bin/bash
# lead-agent.sh — Ledaragenten som orkesterar allt utvecklingsarbete på mauserdb.
# Körs av cron var 30 min. Hanterar rate limits och startar om automatiskt.

# Viktiga env-variabler för headless/autonom drift
export DISABLE_AUTOUPDATER=1
export CLAUDE_CODE_DISABLE_NONESSENTIAL_TRAFFIC=1

PROJECT="/home/clawd/clawd/mauserdb"
CLAUDE="/home/clawd/.npm-global/bin/claude"
LOCKFILE="/tmp/mauserdb-lead.lock"
RUNLOG="/tmp/mauserdb-lead.log"
RATELIMIT_FILE="/tmp/mauserdb-ratelimit.txt"
MAX_LOG_LINES=2000

# Se till att node/npm finns i PATH (för npx ng build i worker-agenter)
export PATH="/home/clawd/.npm-global/bin:/usr/local/bin:/usr/bin:/bin:$PATH"

# Tillåt att köras utanför en aktiv Claude-session (t.ex. manuell testning)
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

# Rotera log om den blir för stor
if [ -f "$RUNLOG" ]; then
    LOG_LINES=$(wc -l < "$RUNLOG" 2>/dev/null || echo 0)
    if [ "$LOG_LINES" -gt "$MAX_LOG_LINES" ]; then
        tail -n 500 "$RUNLOG" > "${RUNLOG}.tmp" && mv "${RUNLOG}.tmp" "$RUNLOG"
        echo "[$(date '+%Y-%m-%d %H:%M')] Log roterad (var $LOG_LINES rader)" >> "$RUNLOG"
    fi
fi

echo "[$(date '+%Y-%m-%d %H:%M')] ========== Lead-agent session startar ==========" >> "$RUNLOG"

cd "$PROJECT"

# Synka från remote: stasha lokala ändringar om det behövs
if git status --porcelain 2>/dev/null | grep -q '^[^?]'; then
    echo "[$(date '+%Y-%m-%d %H:%M')] Stashar lokala ändringar inför git pull..." >> "$RUNLOG"
    git stash push -m "lead-agent-auto-stash-$(date +%s)" >> "$RUNLOG" 2>&1 || true
    git pull --rebase origin main >> "$RUNLOG" 2>&1 || true
    git stash pop >> "$RUNLOG" 2>&1 || true
else
    git pull --rebase origin main >> "$RUNLOG" 2>&1 || true
fi

# Samla kontext och skriv prompt till tempfil (undviker för stora shell-argument)
RECENT_COMMITS=$(git log --oneline -10 2>/dev/null || echo "inga commits")
PROMPT_FILE=$(mktemp /tmp/mauserdb-prompt-XXXXXX.md)

cat > "$PROMPT_FILE" << ENDPROMPT
Du är LEDARAGENTEN för projektet mauserdb — ett produktionssystem för ett IBC-tvätteri.
Din roll: Senior programmeringsansvarig / tech lead. Du koordinerar allt arbete, håller projektet rörligt
och fattar beslut om vad som ska prioriteras. Du skriver ALDRIG kod själv — du delegerar till worker-agenter.

## Senaste git-commits:
$RECENT_COMMITS

## VIKTIGT: Läs dessa filer direkt med Read-verktyget för aktuell kontext:
- /home/clawd/clawd/mauserdb/lead-memory.md — din backlog och beslutsdagbok
- /home/clawd/clawd/mauserdb/dev-log.md (sista 60 raderna) — senaste aktivitet

---

## Din uppgift denna session:

### Steg 1 — Analysera läget
- Läs lead-memory.md med Read-verktyget
- Läs de sista 60 raderna av dev-log.md med Read-verktyget
- Granska git-loggen (git log --oneline -20) för att se vad som committats
- Identifiera vilka backlog-items som är klara vs fortfarande öppna

### Steg 2 — Uppdatera lead-memory.md
Redigera /home/clawd/clawd/mauserdb/lead-memory.md:
- Markera genomförda items med [x] och commit-hash
- Lägg till nya observationer och tekniska insikter
- Prioritera om backlog-listan
- Skriv in beslut i BESLUTSDAGBOK
- Uppdatera "Senast uppdaterad" datumet

### Steg 3 — Starta worker-agenter
Starta 2-3 parallella worker-agenter (via Task-verktyget) på de högst prioriterade ÖPPNA items.

Varje worker-agent ska:
- Få tydliga instruktioner om EXAKT vilka filer de äger
- Inte överlappa med varandra (separerade filuppsättningar)
- ALDRIG röra livesidorna (rebotling-live, tvattlinje-live, saglinje-live, klassificeringslinje-live)
- Bygga: \`cd /home/clawd/clawd/mauserdb/noreko-frontend && npx ng build\`
- Commita specifika filer och pusha
- Uppdatera dev-log.md

### Steg 4 — Uppdatera dev-log.md
Skriv en rad i /home/clawd/clawd/mauserdb/dev-log.md med vad som planeras denna session.

### Steg 5 — Commit lead-memory och dev-log
git -C /home/clawd/clawd/mauserdb add lead-memory.md dev-log.md
git -C /home/clawd/clawd/mauserdb commit -m "Lead: $(date '+%Y-%m-%d') session-update"
git -C /home/clawd/clawd/mauserdb push

---

## ABSOLUTA REGLER:
1. Rör ALDRIG: rebotling-live, tvattlinje-live, saglinje-live, klassificeringslinje-live
2. DB-ändringar → SQL i noreko-backend/migrations/ + \`git add -f\`
3. All UI-text på svenska
4. Angular dark theme: #1a202c bg, #2d3748 cards, #e2e8f0 text, Bootstrap 5
5. Commit + push bara när en feature är klar och bygger klart
6. Starta MAX 2 worker-agenter per session (spara token-budget så ägaren kan ställa frågor)
7. Håll egna token-användning minimal — analysera snabbt, delegera direkt till workers

## Projektsökväg: /home/clawd/clawd/mauserdb/
## Git remote: github.com:oscarjio/mauserdb.git branch main
ENDPROMPT

echo "[$(date '+%Y-%m-%d %H:%M')] Startar lead-agent Claude-session (prompt: $(wc -c < "$PROMPT_FILE") bytes)..." >> "$RUNLOG"

# Kör Claude via stdin (undviker argument-storleksproblem) med streaming till log
TMPOUT=$(mktemp)
$CLAUDE --dangerously-skip-permissions --print "$(cat "$PROMPT_FILE")" 2>&1 | tee -a "$RUNLOG" > "$TMPOUT"
CLAUDE_EXIT=${PIPESTATUS[0]}
rm -f "$PROMPT_FILE"

echo "[$(date '+%Y-%m-%d %H:%M')] Claude avslutade med exit-kod: $CLAUDE_EXIT" >> "$RUNLOG"

# Detektera rate limit
if grep -qiE "usage limit|rate limit|out of extra usage|resets [0-9]" "$TMPOUT"; then
    RESET_MSG=$(grep -oiE "resets [^\n]+" "$TMPOUT" | head -1 || echo "okänd tid")
    echo "[$(date '+%Y-%m-%d %H:%M')] RATE LIMIT detekterad. $RESET_MSG. Nästa cron-körning försöker igen." >> "$RUNLOG"
    echo "$(date '+%Y-%m-%d %H:%M') - $RESET_MSG" > "$RATELIMIT_FILE"
else
    rm -f "$RATELIMIT_FILE"
    echo "[$(date '+%Y-%m-%d %H:%M')] Lead-agent session klar." >> "$RUNLOG"
fi
rm -f "$TMPOUT"
