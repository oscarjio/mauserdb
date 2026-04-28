#!/bin/bash
# lead-agent-watchdog.sh — Vaktar lead-agent, startar om vid hängning
# Cron: */20 * * * * /home/clawd/clawd/mauserdb/lead-agent-watchdog.sh

LEAD_SCRIPT="/home/clawd/clawd/mauserdb/lead-agent.sh"
LEAD_LOG="/home/clawd/clawd/logs/mauserdb_lead_agent.log"
WATCHDOG_LOG="/home/clawd/clawd/logs/mauserdb_watchdog.log"
LOCKFILE="/tmp/mauserdb_lead_agent.lock"
MAX_AGE_MIN=95   # Döda om lead-agent kört längre än detta (workers timeout=3600s + margin)
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

log() { echo "[$TIMESTAMP] $1" >> "$WATCHDOG_LOG"; }

# ── 1. Håll loggarna rimliga (max 5000 rader) ─────────────────────────────────
for F in "$LEAD_LOG" "$WATCHDOG_LOG"; do
    if [ -f "$F" ] && [ "$(wc -l < "$F")" -gt 5000 ]; then
        tail -2000 "$F" > "${F}.tmp" && mv "${F}.tmp" "$F"
        log "Trunkerade $F till 2000 rader"
    fi
done

# ── 2. Kolla om lead-agenten är igång ────────────────────────────────────────
if [ -f "$LOCKFILE" ]; then
    OLD_PID=$(cat "$LOCKFILE" 2>/dev/null)

    if [ -n "$OLD_PID" ] && kill -0 "$OLD_PID" 2>/dev/null; then
        # Processen lever — mät ålder via lock-filens mtime (skrivs vid start)
        LOCK_MTIME=$(stat -c %Y "$LOCKFILE" 2>/dev/null || echo 0)
        NOW_TS=$(date +%s)
        ELAPSED_MIN=$(( (NOW_TS - LOCK_MTIME) / 60 ))

        if [ "$ELAPSED_MIN" -gt "$MAX_AGE_MIN" ]; then
            log "STUCK: Lead-agent PID $OLD_PID har kört ${ELAPSED_MIN}min (>${MAX_AGE_MIN}min). Dödar."

            # Döda processgrupp (inkl. workers/claude-barn)
            PGID=$(ps -o pgid= -p "$OLD_PID" 2>/dev/null | tr -d ' ')
            if [ -n "$PGID" ] && [ "$PGID" != "0" ]; then
                kill -- -"$PGID" 2>/dev/null
            fi
            sleep 3
            kill -9 "$OLD_PID" 2>/dev/null

            # Döda eventuellt kvarlevande orphan claude-processer för detta projekt
            pkill -f "claude.*mauserdb" 2>/dev/null

            rm -f "$LOCKFILE"
            log "Dödad och lock rensad. Startar om."
        else
            log "OK: Lead-agent PID $OLD_PID kör (${ELAPSED_MIN}min). Inget att göra."
            exit 0
        fi
    else
        # Lock finns men processen är död — rensa
        log "Inaktuell lock (PID $OLD_PID död). Rensar."
        rm -f "$LOCKFILE"
    fi
else
    log "Ingen lock-fil — lead-agent är inte igång."
fi

# ── 3. Kolla om den senaste körningen var för länge sedan ────────────────────
LAST_START=$(grep "lead-agent starting" "$LEAD_LOG" 2>/dev/null | tail -1 | grep -oP '\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}')
if [ -n "$LAST_START" ]; then
    LAST_TS=$(date -d "$LAST_START" +%s 2>/dev/null || echo 0)
    NOW_TS=$(date +%s)
    MINUTES_AGO=$(( (NOW_TS - LAST_TS) / 60 ))
    log "Senaste körning: $LAST_START (${MINUTES_AGO}min sedan)"
else
    log "Ingen tidigare körning hittad i loggen."
    MINUTES_AGO=9999
fi

# ── 4. Starta om lead-agent ───────────────────────────────────────────────────
log "Startar om lead-agent..."
nohup bash "$LEAD_SCRIPT" >> "$LEAD_LOG" 2>&1 &
NEW_PID=$!
log "Lead-agent startad med PID $NEW_PID"
