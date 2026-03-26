# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-26 (session #344)*
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
Session #256-#344: BUGGJAKT — Se dev-log.md for detaljer.

## OPPEN BACKLOG (prioritetsordning)

GRUNDLIG GENOMGANG + FORBATTRING — vi har nu prod_db_schema.sql och deploy-pipeline.

### Session #327+ (NYA VERKTYG):
- prod_db_schema.sql i projektroten = facit for SQL
- Deploy: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- Prod DB: ssh -p 32546 user@mauserdb.com + mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb
- mb_string polyfill i api.php (servern saknar php-mbstring)
- VIKTIGT: rsync --exclude='db_config.php' for backend deploy (fixat session #329)

### Nasta:
- [ ] Granska rapporter djupare (veckorapport/morgonrapport SQL+UI+PDF)
- [ ] Granska bonus-systemet (BonusController SQL + bonus-dashboard UI)
- [ ] Granska skiftrapporter (endpoints + alla skiftrapport-UI)
- [ ] Granska stopporsakssystemet (endpoints + stopporsak-UI+grafer)
- [ ] Ytterligare prestandaoptimering (fler endpoints >500ms)

## BESLUTSDAGBOK (senaste 3)

### 2026-03-26 — Session #344 (klar)
Worker A: Dashboard 3 controllers (15 ep) granskade — 4 buggar fixade: ProduktionsDashboardController refererade icke-existerande station/cykel_tid-kolumner (brot 3 ep), getDagligtMal felaktig enum+ORDER BY, StatusController+RebotlingAdminController cykel_tid-bugg. Prestandaopt: rebotling 935ms->512ms (-45%). Auth 6 controllers — bcrypt+CSRF+rate limiting OK.
Worker B: Dashboard+rebotling-adjacenta+auth UI granskade. Login label-for+autocomplete fixat. Users/create-user dark theme fixat. A11y: kontrast 11.5:1 OK, keyboard-nav OK. 30 diakritikfixar i 14 filer. Build+deploy OK.

### 2026-03-26 — Session #343 (klar)
Worker A: Personal/roster (41 ep), lager/inventarie (28 ep), rapporter (36 ep) — alla SQL matchar schema. 1 buggfix: OperatorController::getOperatorTrend() duplicate named params. 105 endpoints, 0 st 500.
Worker B: Personal/roster 6 komp + rapporter 4 komp + rebotling-statistik 10+ graf-komp granskade — alla OK. 55 diakritikfixar i 22 filer. Build+deploy OK.

### 2026-03-26 — Session #342 (klar)
Worker A: produktion_procent verifierad — momentan takt fran PLC, INTE kumulativ. Kvalitetskontroll (8 ep), Energi (3 ep), Underhall (29 ep) — alla SQL matchar schema. 116 endpoints, 0 st 500.
Worker B: Kvalitetskontroll 10 komp + Underhall 4 komp granskade — alla OK. 70+ diakritikfixar i 21 filer. Build+deploy OK.
