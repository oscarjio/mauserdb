# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-20 (session #202)*
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
Session #196: BUGGJAKT — 6 buggar (5 Worker A + 1 Worker B). SQL injection, IBC/h 60x-fel, setTimeout-lacka.
Session #197: BUGGJAKT — 14 buggar (6 Worker A + 8 Worker B). DST/timezone i 4 PHP-klasser, timeout/catchError i 8 Angular-komponenter.
Session #198: BUGGJAKT — 8 buggar (3 Worker A + 5 Worker B). Auth-luckor, XSS i operator-compare, maxlength i 4 komponenter.
Session #199: BUGGJAKT — 7 buggar (5 Worker A + 2 Worker B). N+1 queries (2), saknade LIMIT (3), hardkodade API-URLer (2). Transaction audit + routing guard audit: inga buggar.
Session #200: BUGGJAKT — 10 buggar (8 Worker A + 2 Worker B). Saknade audit trails (7), XSS i category (1), saknade error-nycklar (2). Angular template audit: rent.
Session #201: BUGGJAKT — 6 buggar (5 Worker A + 1 Worker B). N+1 queries (2), redundanta DB-anrop (2), midnight edge case (1), saknad losenordsvalidering (1). Lazy loading audit: rent.
Session #202: BUGGJAKT — 16 buggar (1 Worker A + 15 Worker B). Saknad session timeout-check i api.php (1), saknade role="alert" pa 14 templates (15). File path traversal audit: rent. Memory leak audit: rent.

## OPPEN BACKLOG (prioritetsordning)

BUGGJAKT-FOKUS — inga nya features tills vidare.

### Kvarstaende buggjakt-items (session #203+):
- [ ] Angular HTTP retry/timeout audit
- [ ] PHP classes/ integer overflow/type juggling audit
- [ ] PHP classes/ error disclosure audit
- [ ] Angular form XSS audit

## BESLUTSDAGBOK (senaste 3)

### 2026-03-20 — Session #201 (klar)
Worker A: 5 buggar — midnight edge case i NarvaroController, redundant SHOW TABLES i ProduktionspulsController, N+1 (730+ queries) i MaskinhistorikController getStationDrifttid(), N+1 (1095+ queries) i getStationOeeTrend(), redundanta COUNT i StopporsakRegistreringController.
Worker B: 1 bugg — saknad losenordslangd/komplexitetsvalidering i menu.ts. Lazy loading audit: alla 100+ routes OK. Form validation audit: 20+ komponenter OK.

### 2026-03-20 — Session #202 (klar)
Worker A: 1 bugg — saknad centraliserad session timeout-check i api.php for POST/PUT/DELETE (34 controllers exponerade). Session fixation, cookie flags, CSRF, path traversal: allt rent. 114 PHP-filer granskade.
Worker B: 15 buggar — saknade role="alert" pa dynamiska meddelanden i 14 HTML-templates. Memory leak audit: alla 169 komponenter har korrekt destroy$/takeUntil/clearInterval/Chart.destroy.
