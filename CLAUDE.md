# CLAUDE.md — mauserdb projekt

## BUILD-REGLER (KRITISKT)

### ng build --watch är alltid aktiv på servern
```bash
# Kontrollera att watch lever:
ps aux | grep -E 'ng.*build.*watch' | grep -v grep

# Om dead → starta om:
cd /home/clawd/clawd/mauserdb && nohup bash watch-deploy.sh > /tmp/ng-watch-deploy.log 2>&1 &

# Följ loggen:
tail -f /tmp/ng-watch-deploy.log
```

**ALDRIG kör `npx ng build` manuellt vid fil-ändring.**  
Watch bygger inkrementellt på 5–15s. `npx ng build` tar 2–3 min.  
Watch deployas automatiskt via rsync till `user@mauserdb.com` (dev-server).

### Watch konfiguration
- Kör: `ng build --watch --configuration watch`
- `--configuration watch` = strictTemplates AV + snabb inkrementell build
- Deploy trigger: "Watching for file changes" (initial) eller "Output location:" + rebuild_pending=1

---

## DOMÄNREGLER — SKIFT & PRODUKTION (KRITISKT)

### ETT skift per dag — ALLTID (inte treskift)
Mauserdb/Noreko Älvängen kör **ett skift per dag**, vardag som helg.

- **Max 1 skiftrapport/dag.** Om data visar 2–3 skift/dag → BUG eller dubblett-aggregering, INTE verkligt treskift
- **"Skift #3" i expand-vy** = skiftrapport nr 3 i sortordning över *flera dagar*, INTE dagens tredje skift
- **Drifttid-cap: max ~10h/dag** (INTE 24h). Visa varning om >10h — det tyder på bugg
- **Vardag (mån–fre):** körs alltid — förvänta produktion
- **Helg (lör/sön):** körs BARA IBLAND — "0 skift på helg" är ofta KORREKT, visa "Ingen produktion (helg)" istället för varning
- **Missade-skift-badge:** Flagga INTE helger utan PLC-aktivitet. Flagga BARA helger med PLC cycles > 0 men ingen skiftrapport
- **Mål:** vardag = 140 IBC, helg = 0 IBC (i `tvattlinje_weekday_goals`) — helg=0 bekräftat 2026-07 (inga helgskift); koden använder 0, ej 60
- **Snitt/bästa-dag:** Dividera med antal dagar med *faktisk produktion*, INTE alla kalenderdagar

---

## DOMÄNREGLER — DRIFTTID / RAST / DRIFTSTOPP (KRITISKT, fastställt av Oscar 2026-10-19)

**D4007 ("drifttid") är REDAN NETTO — utan rast och UTAN driftstopp. Dra ALDRIG av
rast eller driftstopp från drifttid någonstans i koden — det är dubbelavdrag.**

- **DRIFTTID** = PLC D4007. Tiden linjen är IGÅNG, redan exklusive rast och driftstopp.
- **RAST** = PLC D4008, egen räknare. Planerad paus. ALDRIG driftstopp, ALDRIG en förlust.
- **DRIFTSTOPP** = PLC D4011, egen räknare. Sätts med egen knapp i PLC vid haveri/fel på
  linjen. Kalla det ALDRIG bara "stopp" i UI/kod — "stopp" är tvetydigt mot vanligt stopp.
- **VANLIGT STOPP** (t.ex. utlöst ljusbom) har INGEN egen räknare, ingår redan i
  drifttiden (D4007 räknar INTE upp under ett sådant stopp) och syns bara som lägre takt.
- Rast/driftstopp skickas in vid skiftrapport-inskick och får ENDAST dras av från
  PÅSLAGEN tid (onoff-spann), ALDRIG från D4007/drifttid.
- **Bevis** (2026-10-15): drifttid 255 + rast 17 + driftstopp 26 = 298 = runtime_today
  exakt. PLC-diagnostiken samma dag: drifttid ökar INTE under rast (08:59–09:16: 118→121
  nästan oförändrat; 11:59–12:33: 277→283 nästan oförändrat).
- **Tillgänglighet** (kort/KPI) = drifttid / planerad skifttid — SAMMA definition som
  OEE:s tillgänglighetsfaktor, INTE drifttid/(drifttid+driftstopp).

---

## DEPLOYMENT

Se `MEMORY.md` för full deployment-info. Sammanfattning:
- Dev: watch-deploy.sh (ovan) → auto-rsync till mauserdb.com dev
- Prod: Oscar deployar alltid själv — föreslå det ALDRIG
- DB-ändringar: SQL-migreringsfiler i `noreko-backend/migrations/`, `git add -f`

---

## SÄKERHETSREGLER

- **Alltid bcrypt** för lösenord — ALDRIG sha1/md5
- **Aldrig `git add dist/`** — dist/ ska aldrig committas
- **Rör ALDRIG** live-sidorna: rebotling-live, tvattlinje-live, saglinje-live, klassificeringslinje-live
