#!/bin/bash
# lead-agent.sh — Ledaragenten som orkesterar allt utvecklingsarbete på mauserdb.
# Körs av cron var 3:e timme. Startar worker-agenter och håller projektet rörligt.

set -e

PROJECT="/home/clawd/clawd/mauserdb"
CLAUDE="/home/clawd/.npm-global/bin/claude"
LOCKFILE="/tmp/mauserdb-lead.lock"
RUNLOG="/tmp/mauserdb-lead.log"

# Förhindra parallella körningar
if [ -f "$LOCKFILE" ]; then
    PID=$(cat "$LOCKFILE")
    if kill -0 "$PID" 2>/dev/null; then
        echo "[$(date '+%Y-%m-%d %H:%M')] Lead-agent körs redan (PID $PID), hoppar över." >> "$RUNLOG"
        exit 0
    fi
fi
echo $$ > "$LOCKFILE"
trap "rm -f $LOCKFILE" EXIT

echo "[$(date '+%Y-%m-%d %H:%M')] ========== Lead-agent session startar ==========" >> "$RUNLOG"

cd "$PROJECT"
git pull --rebase origin main >> "$RUNLOG" 2>&1 || true

# Samla kontext för ledaragenten
RECENT_COMMITS=$(git log --oneline -10 2>/dev/null || echo "inga commits")
LEAD_MEMORY=$(cat lead-memory.md 2>/dev/null || echo "lead-memory.md saknas")
DEV_LOG=$(tail -60 dev-log.md 2>/dev/null || echo "dev-log.md saknas")

PROMPT=$(cat <<PROMPT
Du är LEDARAGENTEN för projektet mauserdb — ett produktionssystem för ett IBC-tvätteri.
Din roll: Senior programmeringsansvarig / tech lead. Du koordinerar allt arbete, håller projektet rörligt
och fattar beslut om vad som ska prioriteras. Du skriver ALDRIG kod själv — du delegerar till worker-agenter.

## Ditt eget minne (lead-memory.md):
$LEAD_MEMORY

## Dev-log (senaste aktivitet):
$DEV_LOG

## Senaste git-commits:
$RECENT_COMMITS

---

## Din uppgift denna session:

### Steg 1 — Analysera läget
- Granska git-loggen: vad har faktiskt committats sedan förra sessionen?
- Läs lead-memory.md (redan inläst ovan) — vad var planerat?
- Identifiera vilka backlog-items som verkar klara (finns i commits) vs fortfarande öppna
- Läs specifika filer om du behöver verifiera att något verkligen är implementerat

### Steg 2 — Uppdatera lead-memory.md
Redigera /home/clawd/clawd/mauserdb/lead-memory.md:
- Markera genomförda items med [x] och commit-hash
- Lägg till nya observationer (buggar du hittar, tekniska insikter)
- Prioritera om backlog-listan om det behövs
- Skriv in beslut i BESLUTSDAGBOK
- Uppdatera "Senast uppdaterad" datumet
- Rensa bort gamla agent-IDs från AKTIVA AGENTER, skriv in sessionens datum

### Steg 3 — Starta worker-agenter
Starta 2-3 parallella worker-agenter (via Task-verktyget) på de högst prioriterade ÖPPNA items.

Varje worker-agent ska:
- Få tydliga instruktioner om EXAKT vilka filer de äger
- Inte överlappa med varandra (ge dem tydligt separerade filuppsättningar)
- Veta att de ALDRIG får röra livesidorna
- Bygga: \`cd /home/clawd/clawd/mauserdb/noreko-frontend && npx ng build\`
- Commita specifika filer och pusha
- Uppdatera dev-log.md

### Steg 4 — Uppdatera dev-log.md
Skriv en rad i /home/clawd/clawd/mauserdb/dev-log.md med vad som planeras denna session.

### Steg 5 — Commit lead-memory och dev-log
git -C /home/clawd/clawd/mauserdb add lead-memory.md dev-log.md
git -C /home/clawd/clawd/mauserdb commit -m "Lead: [datum] session-update, starta [N] worker-agenter"
git -C /home/clawd/clawd/mauserdb push

---

## ABSOLUTA REGLER du alltid måste följa:
1. Rör ALDRIG: rebotling-live, tvattlinje-live, saglinje-live, klassificeringslinje-live
2. DB-ändringar → SQL i noreko-backend/migrations/ + \`git add -f\`
3. All UI-text på svenska
4. Angular dark theme: #1a202c bg, #2d3748 cards, #e2e8f0 text, Bootstrap 5
5. Commit + push bara när en feature är klar och bygger klart

## Projektsökväg: /home/clawd/clawd/mauserdb/
## Git remote: github.com:oscarjio/mauserdb.git branch main
PROMPT
)

echo "[$(date '+%Y-%m-%d %H:%M')] Startar lead-agent Claude-session..." >> "$RUNLOG"
$CLAUDE --print "$PROMPT" >> "$RUNLOG" 2>&1
echo "[$(date '+%Y-%m-%d %H:%M')] Lead-agent session klar." >> "$RUNLOG"
