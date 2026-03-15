# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-15 (session #107)*
*Fullständig historik: lead-memory-archive.md*

---

## Projektöversikt

IBC-tvätteri (1000L plasttankar i metallbur). Systemet ger VD realtidsöverblick + rättvis operatörsbonus.

**Roller:** VD (KPI:er, 10-sek överblick), Operatör (bonusläge live, motiverande), Admin (mål, regler, skift).
**Linjer:** Rebotling (AKTIV, bra data), Tvättlinje/Såglinje/Klassificeringslinje (EJ igång).
**Stack:** Angular 20+ → PHP/PDO → MySQL. PLC → plcbackend (rör ALDRIG) → DB.

## ÄGARENS DIREKTIV (2026-03-15)

- **FOKUS: BUGGJAKT** — koncentrera er på att hitta och fixa buggar
- **Rebotling** — enda linjen med bra data
- **INGA NYA FEATURES** — prioritera kvalitet och stabilitet
- Granska controllers, services, templates systematiskt
- VD ska förstå läget på 10 sekunder

## ABSOLUTA REGLER (bryt ALDRIG)

1. **Rör ALDRIG livesidorna**: `rebotling-live`, `tvattlinje-live`, `saglinje-live`, `klassificeringslinje-live`
2. **Rör ALDRIG plcbackend/** — PLC-datainsamling i produktion
3. **ALLTID bcrypt** — AuthHelper använder password_hash/password_verify. Ändra ALDRIG till sha1/md5
4. **ALDRIG röra dist/** — borttagen från git, ska aldrig tillbaka
5. DB-ändringar → SQL-fil i `noreko-backend/migrations/YYYY-MM-DD_namn.sql` + `git add -f`
6. All UI-text på **svenska**
7. Dark theme: `#1a202c` bg, `#2d3748` cards, `#e2e8f0` text, Bootstrap 5
8. Commit + push bara när feature är klar och bygger
9. Bygg: `cd noreko-frontend && npx ng build`

## ÄGARENS INSTRUKTIONER (dokumentera allt — ägaren ska aldrig behöva upprepa sig)

- Fokus rebotling. Övriga linjer ej igång.
- Systemet är för VD (övergripande koll) + rättvis individuell operatörsbonus.
- DB ligger INTE på denna server — deployas manuellt. DB-ändringar via SQL-migrering.
- Agenterna stannar aldrig — håll arbete igång.
- Ledaragenten driver projektet självständigt — sök internet, granska kod, uppfinn features.
- Lägg till nya funktioner i navigationsmenyn direkt.
- Kunden utvärderar efteråt — jobba fritt och kreativt.
- Graferna behöver detaljerade datapunkter, adaptiv granularitet.

## Tekniska mönster

- **AuthService**: `loggedIn$` och `user$` BehaviorSubjects
- **Lifecycle**: `implements OnInit, OnDestroy` + `destroy$ = new Subject<void>()` + `takeUntil(this.destroy$)` + `clearInterval/clearTimeout` + `chart?.destroy()`
- **HTTP polling**: `setInterval` + `timeout(5000)` + `catchError` + `isFetching` guard
- **APP_INITIALIZER**: `firstValueFrom(auth.fetchStatus())`
- **Math i templates**: `Math = Math;` som class property

## Bug Hunt Status

Bug Hunts #1-#50 genomförda. Kodbasen har genomgått systematisk granskning.
Session #57-#101: Feature-utveckling löpande. Se lead-memory-archive.md för detaljer.
Session #102-#104: Features (statistik-överblick, daglig briefing, gamification, prediktivt underhåll).
Session #105: BUGGJAKT — 4 SQL-buggar + 3 error-handling-buggar fixade. 41 frontend components auditerade.
Session #106: BUGGJAKT — 8 backend-buggar (2 säkerhet, 1 OEE, 5 query/unused) + 4 frontend-buggar + 12 API-endpoints testade + 3 unused vars fixade av lead.
Session #107: BUGGJAKT — Worker A: 119 catch($e) cleanup, 2 DST-buggar, 1 auth-bugg (SkiftoverlamningController). Worker B: ~270 trackBy fixade, subscription/Chart.js audit OK. Totalt: 122 fixar + 270 trackBy.
Session #108: BUGGJAKT — Worker A: Batch 2 controllers (10 st) + unused vars (3 controllers). Worker B: 6 endpoint-verifieringar + frontend logikbuggar.

## ÖPPEN BACKLOG (prioritetsordning)

BUGGJAKT-FOKUS — inga nya features tills vidare.
- [WIP] **Buggjakt: Batch 2 controllers (10 st) + unused vars (3 controllers)**
- [WIP] **Buggjakt: 6 endpoints + frontend logikbuggar**
- [ ] **Buggjakt: OperatorRanking streaks — verifiera med riktig data**
- [ ] **Buggjakt: Batch 3 controllers (18 st)**
- [ ] **Buggjakt: Frontend beräkningar vs backend konsistens**
- [ ] **Buggjakt: API-routes audit (404-risk)**

## BESLUTSDAGBOK (senaste 3)

### 2026-03-15 — Session #106 (klar)
Worker A (Backend auth+OEE+unused): 2 säkerhetsbuggar, 1 OEE-bugg, 2 query-buggar, 1 saknad return, 2 unused vars.
Worker B (Frontend templates+API-test): 1 template-bugg, 3 unused imports. 12 endpoints testade. db_config.php + migration.

### 2026-03-15 — Session #107 (klar)
Worker A: 119 catch($e) -> catch(\Exception) i 49 filer. 2 DST-buggar (streak/MTBF). 1 auth-bugg (SkiftoverlamningController GET utan requireLogin).
Worker B: ~270 ngFor trackBy fixade (prestandabugg). Subscription/Chart.js audit OK (inga lacker). Services OK.

### 2026-03-15 — Session #108 (pågår)
Worker A: Granskar 10 batch-2-controllers + fixar unused vars i 3 controllers.
Worker B: Curl-testar 6 endpoints + granskar frontend-beräkningar i 8 components.
