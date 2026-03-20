# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-20 (session #196)*
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
Session #105-#131: BUGGJAKT — ~700 buggar totalt. Se lead-memory-archive.md.
Session #132-#154: BUGGJAKT — ~800+ buggar. Se lead-memory-archive.md.
Session #155-#170: BUGGJAKT — ~500+ buggar. Se lead-memory-archive.md.
Session #190-#194: BUGGJAKT — 40 buggar. Se lead-memory-archive.md.
Session #195: BUGGJAKT — 3 buggar (0 Worker A + 3 Worker B). Controllers ar tunna proxys — logik i classes/.
Session #196: BUGGJAKT — 6 buggar (5 Worker A + 1 Worker B). SQL injection i BonusController simulate(), IBC/h berakning 60x fel i OperatorDashboardController (4 stallen), setTimeout-lacka i stationsdetalj.

## OPPEN BACKLOG (prioritetsordning)

BUGGJAKT-FOKUS — inga nya features tills vidare.

### Kvarstaende buggjakt-items (session #197+):
- [ ] Angular memory profiling — tunga sidor
- [ ] PHP classes/ date/time edge cases — DST, timezone, datum-validering
- [ ] PHP classes/ error response audit — saknade HTTP-statuskoder
- [ ] Angular HTTP error handling audit — saknade catchError/timeout
- [ ] PHP classes/ authorization audit — saknade auth-kontroller

## BESLUTSDAGBOK (senaste 3)

### 2026-03-20 — Session #195 (klar)
Worker A: 0 buggar — alla 18 controllers ar tunna proxy-filer. Framtida PHP-granskning mot classes/.
Worker B: 3 buggar — produktionstakt + batch-sparning saknade timeout+catchError.

### 2026-03-20 — Session #196 (klar)
Worker A: 5 buggar — BonusController simulate() SQL injection (string-interpolation -> prepared stmt). OperatorDashboardController getMittTempo()+getMinBonus() IBC/h berakning 60x fel (runtime_plc ar minuter, inte sekunder, 4 stallen).
Worker B: 1 bugg — stationsdetalj setTimeout i laddaOeeTrend() sparade inte timer-referens, kunde inte rensas i ngOnDestroy. VIKTIGT: ovriga 9 komponenter var rena — null-safety och lifecycle korrekt.
