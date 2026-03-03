#!/bin/bash
# auto-develop.sh — Startar autonomt Claude-arbete på mauserdb
# Körs av cron, behöver ingen manuell interaktion.

set -e

PROJECT="/home/clawd/clawd/mauserdb"
LOG="$PROJECT/dev-log.md"
CLAUDE="/home/clawd/.npm-global/bin/claude"
LOCKFILE="/tmp/mauserdb-autodev.lock"

# Förhindra att flera instanser körs samtidigt
if [ -f "$LOCKFILE" ]; then
    PID=$(cat "$LOCKFILE")
    if kill -0 "$PID" 2>/dev/null; then
        echo "[$(date)] Autodev körs redan (PID $PID), hoppar över." >> /tmp/mauserdb-autodev.log
        exit 0
    fi
fi
echo $$ > "$LOCKFILE"
trap "rm -f $LOCKFILE" EXIT

echo "[$(date)] Startar autodev-session..." >> /tmp/mauserdb-autodev.log

cd "$PROJECT"
git pull --rebase origin main >> /tmp/mauserdb-autodev.log 2>&1 || true

PROMPT=$(cat <<'PROMPT'
Du är en autonom senior fullstack-utvecklare på projektet mauserdb (IBC-tvätteri produktionssystem).

Ditt uppdrag: Fortsätt autonomt förbättra systemet, fokus på rebotling-sektionen.

Börja med att läsa:
1. /home/clawd/clawd/mauserdb/dev-log.md — vad har gjorts, vad planeras
2. /home/clawd/clawd/.claude/projects/-home-clawd/memory/MEMORY.md — regler och arkitektur

Sedan: starta 2-3 parallella agenter (via Task-verktyget) som var och en jobbar autonomt på olika delar.
Varje agent ska:
- Läsa relevant kod innan den ändrar något
- Implementera konkreta förbättringar
- Köra `cd /home/clawd/clawd/mauserdb/noreko-frontend && npx ng build` och fixa kompileringsfel
- Commita specifika filer och pusha till GitHub
- Uppdatera dev-log.md

REGLER (kritiska):
- Rör ALDRIG: rebotling-live, tvattlinje-live, saglinje-live, klassificeringslinje-live
- All UI-text på svenska
- Databasändringar → SQL-fil i noreko-backend/migrations/ + git add -f
- Dark theme: #1a202c bg, #2d3748 cards, #e2e8f0 text, Bootstrap 5
- Angular standalone components, OnInit+OnDestroy med destroy$ pattern

Prioritera feature-arbete som ger mest värde för en produktionschef på ett IBC-tvätteri:
- Bonussystem transparency (operatörer ser sina poäng i realtid)
- Produktionsstatistik med veckojämförelser
- Tydliga KPI:er och prognoser
- Bra rapporter för export
PROMPT
)

$CLAUDE --print "$PROMPT" >> /tmp/mauserdb-autodev.log 2>&1

echo "[$(date)] Autodev-session klar." >> /tmp/mauserdb-autodev.log
