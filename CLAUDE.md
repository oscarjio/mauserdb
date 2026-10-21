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
- **Tillgänglighet mot planerad skifttid är BORTTAGEN** för tvättlinje (Oscar 2026-10-20
  BESLUT, ersätter raden ovan som gällde 2026-10-19 till 2026-10-20) — planerad tid
  (495/435 min) är inte relevant, linjen körs när det passar. "Tillgänglighet" visas INTE
  längre som eget nyckeltal någonstans i tvättlinjens UI (sammanfattningskort, TILLG%-
  kolumner, Excel-kolumn, OEE-trendens serie, PDF-donut). Se RÄKNEKÄRNA-avsnittet nedan
  för den nya **driftfaktor**-formeln som ersätter den som OEE:s första faktor.

---

## RÄKNEKÄRNA — TVÄTTLINJENS KPI:er (KRITISKT, fastställt av Oscar 2026-10-20)

**Detta är de ENDA tillåtna formlerna för tvättlinjens nyckeltal.** De bor i EN PHP-klass
och EN TS-fil som speglar varandra exakt — uppfinn ALDRIG en egen variant i en controller
eller komponent. Rebotling/övriga linjer berörs inte och har sin egen (oförändrade)
semantik.

- **Källor:** `noreko-backend/classes/TvattlinjeMetrics.php` (PHP) och
  `noreko-frontend/src/app/utils/tvattlinje-metrics.ts` (TS, identisk spegel).
- **Används av:** `TvattlinjeController` (getStatistics, getOeeTrend,
  getSkiftrapportStatistik), `SkiftjamforelseController`, `HistoriskSammanfattningController`
  (alla tre endast när `?line=tvattlinje`), samt `shared-skiftrapport.ts` och
  `tvattlinje-statistik.ts` i frontend (alla gated på `config.line === 'tvattlinje'`).

**Definitioner:**
- **Drifttid** = D4007, cappad till 600 min/skift, **inga avdrag** (se domänregeln ovan).
- **Driftfaktor** (Oscar 2026-10-20 BESLUT, ERSÄTTER "Tillgänglighet"/planerad-tid-formeln
  nedan) = drifttid / (drifttid + driftstopp). Driftstopp = D4011, inskickat med
  skiftrapporten. Rast räknas ALDRIG in. Saknas driftstopp (=0 eller ej registrerat) →
  driftfaktor 100% (ingen känd förlust att straffa för). Planerad skifttid (495/435 min)
  används INTE längre någonstans i OEE-vägen — linjen körs när det passar, oavsett dag.
  Driftfaktor exponeras ALDRIG som eget nyckeltal i UI:t — bara som OEE:s första faktor.
- **IBC** (för P, EFF och snitt min/IBC) = **ALLA TVÄTTADE** = ok + ej_ok + omtvätt (samma
  som PLC:ns "tot"). Varje omtvätt är en tvättcykel linjen utfört — blanda ALDRIG ihop
  detta med ok+ej_ok (saknar omtvätt) på vissa ställen och totalt på andra.
- **Måltakt** = produktens `cycle_time_minutes` (tvattlinje_produkter), IBC-viktad över
  perioden om flera produkter körts. Fallback 3,0 min ENDAST om ingen produktkoppling
  alls finns. ALDRIG ett fast tak som "20 IBC/h" (det motsvarar bara 3,0 min/IBC och är
  fel för produkter med annan takt, t.ex. Vanlig Tvätt/Peroxid 3,40, Olja/Credin 4,00).
- **P (Prestanda)** = IBC × måltakt / drifttid, cap 100%.
- **K (Kvalitet)** = ok / IBC (ok / alla tvättade).
- **OEE** = driftfaktor × P × K.
- **Effektivitet** = (måltakt − drifttid/IBC) / måltakt — **SIGNERAD, INGEN cap** (+ =
  snabbare än mål, − = långsammare). Skilj den ALLTID från OEE:s prestandafaktor (som
  ÄR kapad 0–100) — de är olika mått, blanda inte ihop dem.

**Exempel (2026-10-15, torsdag, Vanlig Tvätt måltakt 3,40 min/IBC, driftstopp 26 min):**
drifttid 255 min, ok 94, totalt 98 → driftfaktor = 255/(255+26) = 90,7%, P = 98×3,40/255
= 131% → cap 100%, K = 94/98 = 95,9%, OEE ≈ 87,0%, EFF = (3,40−255/98)/3,40 ≈ +23,5%.

**Historik:** 2026-10-19–2026-10-20 gällde A (Tillgänglighet) = drifttid/planerad tid som
OEE:s första faktor (OEE ≈ 49,4% för exemplet ovan). Detta ersattes 2026-10-20 av
driftfaktor eftersom planerad skifttid inte är relevant för hur linjen faktiskt körs.

---

## GIT — LÖPANDE COMMIT & PUSH (fastställt av Oscar 2026-10-20)

**Committa löpande i små, logiska steg** — minst efter varje avslutad delpunkt i ett
uppdrag (t.ex. räknekärnan, ett enskilt kort/UI-fix, Excel-exporten, adminsidan var för
sig). **Pusha branchen till GitHub efter varje commit** så arbetet aldrig bara ligger
lokalt och går förlorat om sessionen avslutas. Tydliga commit-meddelanden som säger vad
som ändrats och varför (inte bara "fix"). **Pusha ALDRIG till main** — bara till den
aktuella feature-branchen. Gäller allt arbete framåt, inte bara detta uppdrag.

**Lägg ut på DEV efter varje avslutat steg** (fastställt av Oscar 2026-10-21): så fort ett
steg är committat och pushat, merga det direkt till dev också så att dev.mauserdb.com
alltid visar senaste committade versionen — vänta inte tills hela uppdraget är klart.
Gör i huvud-checkouten `/home/clawd/clawd/mauserdb` (inte i worktreen):
```bash
cd /home/clawd/clawd/mauserdb
git merge --ff-only worktree-mauserdb-bugfixes
bash noreko-backend/deploy-dev.sh
# vänta in att ng build --watch (se BUILD-REGLER ovan) har byggt om och rsyncat frontend
```
Bekräfta med `CODE_VERSION`-filen att dev visar den nya committen. **Endast dev** — rör
ALDRIG produktionen (Oscar deployar alltid prod själv), **ingen push till main**, och
**kör ALDRIG migrationsfiler** automatiskt (Oscar godkänner och kör dem själv).

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
