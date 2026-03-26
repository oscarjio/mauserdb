# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-26 (session #350)*
*Fullstandig historik: lead-memory-archive.md*

---

## Projektoversikt

IBC-tvatteri (1000L plasttankar i metallbur). Systemet ger VD realtidsoerblick + rattvis operatorsbonus.

**Roller:** VD (KPI:er, 10-sek overblick), Operator (bonuslage live, motiverande), Admin (mal, regler, skift).
**Linjer:** Rebotling (AKTIV, bra data), Tvattlinje/Saglinje/Klassificeringslinje (EJ igang).
**Stack:** Angular 20+ -> PHP/PDO -> MySQL. PLC -> plcbackend (ror ALDRIG) -> DB.

## AGARENS DIREKTIV (2026-03-15)

- **FOKUS: BUGGJAKT** — koncentrera er pa att hitta och fixa buggar
- **Rebotling** — enda linjen med bra data
- **INGA NYA FEATURES** — prioritera kvalitet och stabilitet
- Granska controllers, services, templates systematiskt
- VD ska forsta laget pa 10 sekunder

## ABSOLUTA REGLER (bryt ALDRIG)

1. **Ror ALDRIG livesidorna**: `rebotling-live`, `tvattlinje-live`, `saglinje-live`, `klassificeringslinje-live`
2. **Ror ALDRIG plcbackend/** — PLC-datainsamling i produktion
3. **ALLTID bcrypt** — AuthHelper anvander password_hash/password_verify. Andra ALDRIG till sha1/md5
4. **ALDRIG rora dist/** — borttagen fran git, ska aldrig tillbaka
5. DB-andringar -> SQL-fil i `noreko-backend/migrations/YYYY-MM-DD_namn.sql` + `git add -f`
6. All UI-text pa **svenska**
7. Dark theme: `#1a202c` bg, `#2d3748` cards, `#e2e8f0` text, Bootstrap 5
8. Commit + push bara nar feature ar klar och bygger
9. Bygg: `cd noreko-frontend && npx ng build`

## AGARENS INSTRUKTIONER (dokumentera allt — agaren ska aldrig behova upprepa sig)

- Fokus rebotling. Ovriga linjer ej igang.
- Systemet ar for VD (overgripande koll) + rattvis individuell operatorsbonus.
- DB ligger INTE pa denna server — deployas manuellt. DB-andringar via SQL-migrering.
- Agenterna stannar aldrig — hall arbete igang.
- Ledaragenten driver projektet sjalvstandigt — sok internet, granska kod, uppfinn features.
- Lagg till nya funktioner i navigationsmenyn direkt.
- Kunden utvarderar efterat — jobba fritt och kreativt.
- Graferna behover detaljerade datapunkter, adaptiv granularitet.

## Tekniska monster

- **AuthService**: `loggedIn$` och `user$` BehaviorSubjects
- **Lifecycle**: `implements OnInit, OnDestroy` + `destroy$ = new Subject<void>()` + `takeUntil(this.destroy$)` + `clearInterval/clearTimeout` + `chart?.destroy()`
- **HTTP polling**: `setInterval` + `timeout(5000)` + `catchError` + `isFetching` guard
- **APP_INITIALIZER**: `firstValueFrom(auth.fetchStatus())`
- **Math i templates**: `Math = Math;` som class property
- **operators-tabell**: `id` (PK auto-increment) och `number` (synligt badgenummer). op1/op2/op3 i rebotling_ibc = operators.number. users.operator_id = operators.number.

## Bug Hunt Status

Bug Hunts #1-#50 genomforda. Kodbasen har genomgatt systematisk granskning.
Session #57-#104: Feature-utveckling. Se lead-memory-archive.md.
Session #105-#170: BUGGJAKT — ~2000+ buggar. Se lead-memory-archive.md.
Session #190-#244: BUGGJAKT — ~1100+ buggar. Se lead-memory-archive.md.
Session #245-#255: BUGGJAKT — 27 buggar. Kodbasen nara rent-status. Se lead-memory-archive.md.
Session #256-#350: BUGGJAKT — Se dev-log.md for detaljer.

## OPPEN BACKLOG (prioritetsordning)

GRUNDLIG GENOMGANG + FORBATTRING — vi har nu prod_db_schema.sql och deploy-pipeline.

### Session #327+ (NYA VERKTYG):
- prod_db_schema.sql i projektroten = facit for SQL
- Deploy: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- Prod DB: ssh -p 32546 user@mauserdb.com + mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb
- mb_string polyfill i api.php (servern saknar php-mbstring)
- VIKTIGT: rsync --exclude='db_config.php' for backend deploy (fixat session #329)

### Nasta:
- [x] station_id-referens fix (session #350 — COALESCE borttagen, hardkodad fallback)
- [x] Responsiv design-sweep (session #350 — 31 tabeller fixade i 16 filer)
- [x] Prestandaoptimering (session #350 — 11 queries optimerade, composite index, 77% snabbare)
- [x] Felhantering UI (session #350 — redan komplett, inget att fixa)
- [x] Chart.js enhetlig styling (session #350 — redan konsekvent, inget att fixa)
- [x] Endpoint-svarstider (session #350 — alla under 1s, 3 optimerade)
- [ ] Oanvanda variabler/funktioner (HistoriskSammanfattning + Skiftjamforelse)
- [ ] Rebotling E2E regressionstest
- [ ] Operatorsbonus-berakning verifiering

## BESLUTSDAGBOK (senaste 3)

### 2026-03-26 — Session #350 (klar)
Worker A: station_id-bugg fixad i 2 controllers (COALESCE borttagen). 11 queries optimerade (DATE()→range). Batch-queries: 12→4 for sammanfattning. Composite index tillagd (datum,skiftraknare). Svarstider: 77% snabbare (1.42s→0.33s), 65% snabbare (1.07s→0.38s).
Worker B: 31 tabeller responsiv-fixade i 16 filer (table-responsive wrappers). Felhantering+Chart.js redan komplett. 2 diakritikfixar. Build+deploy OK.

### 2026-03-26 — Session #349 (klar)
Worker A: 15 controllers djupgranskade. 1 bugg fixad: MinDagController duplicerade PDO-params. 60+ endpoints curl-testade. Rebotling E2E verifierat (4908 rader).
Worker B: 30+ frontend-sidor granskade. 18 trasiga ikoner fixade (bi→fa). produktion_procent bekraftat EJ kumulativt. Build+deploy OK.

### 2026-03-26 — Session #348 (klar)
Worker A: 18 controllers djupgranskade. 3 buggar fixade (YEARWEEK-alias, station_id, reason/operator_name). 70+ endpoints curl-testade.
Worker B: 23+ frontend-sidor granskade. 7 diakritikfixar. Dark theme/lifecycle/svenska OK.
