# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-27 (session #359)*
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
Session #256-#354: BUGGJAKT — Se dev-log.md for detaljer.
Session #355: SQL-schema granskning + performance audit + WCAG.
Session #356: E2E regressionstest + HTTP interceptor audit + caching + lazy loading + Chart.js granskning + auth-flode.
Session #357: Rebotling-djupgranskning — 0 SQL-mismatches, produktion_procent OK (inte kumulativ), 3 schema-fixes, heatmap CSS-fix, E2E 50/50 PASS.
Session #358: Icke-rebotling genomgang — 100+ endpoints 0x500, 80+ komponenter 0 buggar, 2 indexes, produktion_procent cap OK, E2E 50/50 PASS.
Session #359: Performance-optimering oee-trendanalys 988ms→124ms + alarm-historik 931ms→130ms (filcache+2 index). CRUD-integrationstest OK. 109 endpoints 0x500. Data-kvalitet DB vs API 0 diskrepanser. 118 grafer OK. E2E 50/50 PASS.
Session #360: EXPLAIN-audit 3 covering indexes + error-handling 3 fixes + stresstest <600ms + security audit 0 SQLi/XSS + API-docs 117 endpoints + 115 endpoints 0x500 + E2E 115/115 PASS.

## OPPEN BACKLOG (prioritetsordning)

GRUNDLIG GENOMGANG + FORBATTRING — vi har nu prod_db_schema.sql och deploy-pipeline.

### Session #327+ (NYA VERKTYG):
- prod_db_schema.sql i projektroten = facit for SQL
- Deploy: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- Prod DB: ssh -p 32546 user@mauserdb.com + mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb
- mb_string polyfill i api.php (servern saknar php-mbstring)
- VIKTIGT: rsync --exclude='db_config.php' for backend deploy (fixat session #329)

### Nasta (session #361):
- [ ] PHP error_log access (behover sudo eller alt loggvag)
- [ ] Cache-strategi review
- [ ] DB connection pooling
- [ ] Frontend bundle-size audit

## BESLUTSDAGBOK (senaste 3)

### 2026-03-27 — Session #360 (klar)
Worker A: EXPLAIN-audit 15+ queries — 3 covering indexes (rebotling_ibc, stopporsak_registreringar, maskin_oee_daglig). Error-handling audit — 3 tysta catch-block fixade. Stresstest alla <600ms. 115 endpoints 0x500. E2E 115/115 PASS.
Worker B: Security audit — 0 SQL injection, 0 XSS, CORS+headers OK. API-docs genererad (117 actions, 500+ subendpoints). UX-audit OK. PHP code quality OK. E2E 115/115 PASS.

### 2026-03-27 — Session #359 (klar)
Worker A: oee-trendanalys 988ms→124ms, alarm-historik 931ms→130ms (filcache 30s TTL + 2 index). CRUD-integrationstest operators/produkttyper/underhallslogg/bonusmal — alla OK. 109 endpoints 0x500. E2E 50/50 PASS. Deploy+index applicerade.
Worker B: Data-kvalitet prod DB vs API — 0 diskrepanser (IBC-count, historik, operatorer alla stammer). 118 graf-filer granskade — dark theme/lifecycle/OEE OK. WCAG AA alla PASS. Template-granskning trackBy+table-responsive OK.

### 2026-03-27 — Session #358 (klar)
Worker A: 100+ icke-rebotling endpoints testade — 0x500. Schema-audit 20+ controllers — 0 mismatches. 2 indexes pa kundordrar. E2E 50/50 PASS.
Worker B: 80+ icke-rebotling komponenter granskade — 0 buggar. Build+deploy OK.
