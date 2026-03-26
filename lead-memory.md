# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-26 (session #334)*
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
Session #256-#334: BUGGJAKT — Se dev-log.md for detaljer.

## OPPEN BACKLOG (prioritetsordning)

GRUNDLIG GENOMGANG + FORBATTRING — vi har nu prod_db_schema.sql och deploy-pipeline.

### Session #327+ (NYA VERKTYG):
- prod_db_schema.sql i projektroten = facit for SQL
- Deploy: rsync till dev.mauserdb.com (se feedback_deploy_workflow.md i memory/)
- Prod DB: ssh -p 32546 user@mauserdb.com + mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb
- mb_string polyfill i api.php (servern saknar php-mbstring)
- VIKTIGT: rsync --exclude='db_config.php' for backend deploy (fixat session #329)

### Nasta:
- [ ] Granska rebotling-sidor pa djupet (data, grafer, berakningar)
- [ ] Testa operatorsbonus-berakningar mot prod DB
- [ ] Granska VD-dashboard KPI:er
- [ ] Testa formularvalidering
- [ ] Granska caching-strategi

## BESLUTSDAGBOK (senaste 3)

### 2026-03-26 — Session #334 (klar)
Worker A: produktion_procent FIXAD (delta-logik fran #330 var fel — PLC ger momentan procent, inte kumulativ; aterställd till ravaarden med cap 100, 74.7% snitt verifierat mot prod DB). VD-dashboard + veckorapport fick admin-rollkontroll. PHP error handling OK (~90 controllers). 115 endpoints testade 0 st 500. Deployat.
Worker B: Hamburger-meny for mobil tillagd (menu.html/ts/css). Error handling i 96 Angular services OK. Rollbaserad nav OK. Internationalisering OK (inga engelska strangar). Deploy SSH nekad — dist redo. Lead: Oanvänd alertsService borttagen fran menu.ts.

### 2026-03-26 — Session #333 (klar)
Worker A: Autentisering OK (bcrypt, rate limiting, CSRF, session-timeout), PDF-export SQL matchar schema, 124 endpoints testade 0 st 500. Inga buggar.
Worker B: 10 fixar — 6 Escape-tangent pa modaler, 4 Chart.js maintainAspectRatio. Polling 76 filer OK, inga lackor. Byggt och deployat.

### 2026-03-26 — Session #332 (klar)
Worker A: Deploy #331 fixar till dev (lyckades), gamification/ranking SQL OK, skiftoverlamning backend OK, 114 endpoints testade 0 st 500, deploy-scripts uppdaterade med --exclude db_config.php. Inga nya buggar.
Worker B: 6 Chart.js-fixar (maintainAspectRatio: false), alla formular/tabeller verifierade OK, skiftoverlamning frontend OK, diakritikfixar i operatorsbonus. Byggt och deployat.
