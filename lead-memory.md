# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-26 (session #348)*
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
Session #256-#348: BUGGJAKT — Se dev-log.md for detaljer.

## OPPEN BACKLOG (prioritetsordning)

GRUNDLIG GENOMGANG + FORBATTRING — vi har nu prod_db_schema.sql och deploy-pipeline.

### Session #327+ (NYA VERKTYG):
- prod_db_schema.sql i projektroten = facit for SQL
- Deploy: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- Prod DB: ssh -p 32546 user@mauserdb.com + mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb
- mb_string polyfill i api.php (servern saknar php-mbstring)
- VIKTIGT: rsync --exclude='db_config.php' for backend deploy (fixat session #329)

### Nasta:
- [~] Operator-controllers djupgranskning (7st) + 10 operator-UI-sidor (session #348)
- [~] Kvalitet-controllers (4st) + kvalitet/analytics UI (session #348)
- [~] Analytics-controllers (7st) + admin-sidor UI (session #348)
- [ ] End-to-end testning rebotling-flodet
- [ ] Responsiv design-sweep
- [ ] Resterande ogranskade controllers (13st)

## BESLUTSDAGBOK (senaste 3)

### 2026-03-26 — Session #348 (pagaende)
Worker A: Djupgranskning 18 controllers — 7 operator (OperatorController, OperatorDashboard, OperatorRanking, OperatorsPrestanda, OperatorJamforelse, OperatorCompare, OperatorOnboarding) + 4 kvalitet (KvalitetstrendanalysController, KvalitetscertifikatController, KvalitetstrendController, KvalitetsTrendbrottController) + 7 analytics (HeatmapController, ParetoController, KassationsanalysController, KassationsDrilldownController, ForstaTimmeAnalysController, StopptidsanalysController, DrifttidsTimelineController). SQL+auth+curl-test+deploy.
Worker B: 19+ frontend-sidor — 10 operator-UI + 4 admin-sidor + 5+ kvalitet/analytics/rebotling-UI. Dark theme, svenska, diakritik, lifecycle, data. Build+deploy.

### 2026-03-26 — Session #347 (klar)
Worker A: Alert-system 4 controllers (22 ep). 5 auth-buggar fixade. 2 prestandaopt (-27%, -30%). RebotlingAdmin 27 ep OK. 100+ ep sweep.
Worker B: Alert/alarm UI 4 komp OK. Rebotling-admin OK. 10 statistik-sidor OK. 17 diakritikfixar.

### 2026-03-26 — Session #346 (klar)
Worker A: Skiftrapport 9.5x snabbare (63→3 queries). Maskin-admin 1 auth-bugg fixad. 27 ep testade.
Worker B: Gamification/notifikation/maskin-admin/energi UI OK. 8 diakritikfixar.
