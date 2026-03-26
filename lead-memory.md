# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-26 (session #331)*
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
Session #256-#330: BUGGJAKT — Se dev-log.md for detaljer.

## OPPEN BACKLOG (prioritetsordning)

GRUNDLIG GENOMGANG + FORBATTRING — vi har nu prod_db_schema.sql och deploy-pipeline.

### Session #327+ (NYA VERKTYG):
- prod_db_schema.sql i projektroten = facit for SQL
- Deploy: rsync till dev.mauserdb.com (se feedback_deploy_workflow.md i memory/)
- Prod DB: ssh -p 32546 user@mauserdb.com + mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb
- mb_string polyfill i api.php (servern saknar php-mbstring)
- VIKTIGT: rsync --exclude='db_config.php' for backend deploy (fixat session #329)

### Nasta:
- [x] Forbattra rebotling-grafer (adaptiv granularitet) — session #331
- [x] Granska error handling i Angular services — session #331 (OK, 508 anrop alla har catchError)
- [x] Optimera polling-intervall — session #331
- [x] Verifiera operatorsbonus-berakningar mot prod DB — session #331 (OK)
- [ ] Granska Chart.js dark theme + svenska tooltips
- [ ] Testa formularsidor end-to-end

## BESLUTSDAGBOK (senaste 3)

### 2026-03-26 — Session #331 (klar)
Worker A: 3 buggar — AndonController dagmal fallback 100->1000 (4 stallen), getBoardStatus shift_plan felaktiga kolumner (shift_date->datum), SkiftrapportExport multi-day SQL crash (datum undefined). Operatorsbonus + PDF verifierade OK.
Worker B: Adaptiv granularitet i statistik-dashboard (veckoaggregering for 90d), 7d/14d perioder i trendanalys, polling optimerat pa 8 sidor (60s->120-300s), 2 engelska termer fixade. Services error handling OK (508 HTTP-anrop alla har catchError).

### 2026-03-25 — Session #330 (klar)
Worker A: 8 buggar — AndonController 5 SQL-fixar, OperatorDashboard 3 PDO-param-fixar, RebotlingAnalytics 2 fixar (produktion_procent kumulativ). 70+ endpoints testade, 0 st 500.
Worker B: 85+ UX-fixar — 59 filer diakritiker, 12 skiftoverlamning-fixar, engelska titlar oversatta. Lifecycle rent (41/41). Byggd och deployad.

### 2026-03-25 — Session #328 (klar)
Worker A: 3 buggar — PHP authorization. 107 endpoints, 0 st 500.
Worker B: 21 buggar — svenska diakritiker + engelska rubriker.
