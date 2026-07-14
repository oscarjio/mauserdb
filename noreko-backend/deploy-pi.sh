#!/bin/bash
# ============================================================================
# deploy-pi.sh — Deploy backend till Pi:n (FAS 1 Pi-aggregering)
# ----------------------------------------------------------------------------
# Pi:n kör internal-api.php nära DB:n. Speglar noreko-backend till Pi:n men
# exkluderar per-server-hemligheter och lokalt genererade filer.
#
# KONFIG läses från noreko-backend/pi_deploy.conf (git-ignorerad, per-server).
#   Skapa den från pi_deploy.conf.example och sätt PI_HOST/PI_PORT/PI_PATH.
#
#   ⚠ PI_PATH MÅSTE matcha sökvägen som den INSTALLERADE piagg-internal.service
#   servar (WorkingDirectory / `php -S ... -t <PATH>` i ExecStart). Idag pekar
#   piagg-internal.service.example på /var/www/mauserdb/noreko-backend medan
#   deploy-dev.sh skriver till /var/www/mauserdb-dev/noreko-backend — verifiera
#   vilken katalog tjänsten FAKTISKT kör från innan skarp körning, annars
#   deployar du till fel plats och Pi:n fortsätter servera gammal kod.
#
# Kör aldrig mot prod utan Oscars godkännande.
# Användning: cd /home/clawd/clawd/mauserdb && bash noreko-backend/deploy-pi.sh
# ============================================================================

set -e
cd "$(dirname "$0")"

CONF="./pi_deploy.conf"
if [[ -f "$CONF" ]]; then
    # shellcheck disable=SC1090
    source "$CONF"
fi

PI_HOST="${PI_HOST:-}"
PI_PORT="${PI_PORT:-22}"
PI_PATH="${PI_PATH:-}"
PIAGG_SERVICE="${PIAGG_SERVICE:-piagg-internal}"

if [[ -z "$PI_HOST" || -z "$PI_PATH" || "$PI_HOST" == *REPLACE_WITH_PI_HOST* ]]; then
    echo "deploy-pi.sh: PI_HOST/PI_PATH saknas eller är placeholder. Avbryter."
    echo "  Skapa noreko-backend/pi_deploy.conf (kopiera pi_deploy.conf.example) och sätt:"
    echo "     PI_HOST=aiab@127.0.0.1        # eller Pi:ns faktiska adress"
    echo "     PI_PORT=42222"
    echo "     PI_PATH=/var/www/mauserdb/noreko-backend/   # = sökvägen piagg-internal.service servar"
    echo "     PIAGG_SERVICE=piagg-internal"
    echo "  ⚠ VERIFIERA att PI_PATH == WorkingDirectory/-t-sökvägen i installerad piagg-internal.service."
    exit 1
fi

# Stämpla deployad kodversion FÖRE rsync (CodeVersion::get() → cache-nycklar + X-Code-Version-header).
CODE_VERSION="$(git rev-parse --short HEAD 2>/dev/null || date +%s)"
echo "$CODE_VERSION" > CODE_VERSION
echo "CODE_VERSION = $CODE_VERSION"

echo "Deployar backend till Pi ($PI_HOST:$PI_PATH)..."
rsync -avz --delete \
  --exclude='db_config.php' \
  --exclude='agg_config.php' \
  --exclude='internal_token.php' \
  --exclude='cors_origins.php' \
  --exclude='app_config.php' \
  --exclude='logs/' \
  --exclude='cache/' \
  --exclude='.git/' \
  --exclude='pi_deploy.conf' \
  ./ \
  "$PI_HOST:$PI_PATH" \
  -e "ssh -p $PI_PORT" \
  --quiet

# Rensa Pi:ns aggregat-cache (agg_*.json) så nya kodens svar inte döljs av gamla,
# och starta om internal-api-tjänsten så den nya koden laddas.
ssh -p "$PI_PORT" "$PI_HOST" \
  "rm -f ${PI_PATH%/}/cache/agg_*.json 2>/dev/null; sudo systemctl restart ${PIAGG_SERVICE} 2>/dev/null || systemctl --user restart ${PIAGG_SERVICE} 2>/dev/null || true"

echo "Klart! Backend deployat till Pi ($PI_HOST) (CODE_VERSION=$CODE_VERSION)."
echo "Verifiera utifrån: curl -sI '<edge-url>?action=tvattlinje&run=statistics&start=...&end=...' | grep -iE 'X-Pi-Version|X-Pi-Stale'"
echo "  → X-Pi-Version ska nu vara $CODE_VERSION och X-Pi-Stale ska försvinna (edge cachar Pi igen)."
