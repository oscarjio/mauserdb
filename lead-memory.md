# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-15 (session #105)*
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
Session #57-#93: Feature-utveckling löpande. Se lead-memory-archive.md för detaljer.
Session #92: Rebotling stationsdetalj-dashboard + VD veckorapport + buggjakt — klara.
Session #93: Rebotling stationsdetalj-dashboard rebotling klar.
Session #94: Kassationsorsak-analys + Rebotling skiftöverlämning — klara.
Session #96: Rebotling underhållslogg + Buggjakt — klara.
Session #97: Rebotling produktionsmål-uppföljning + Stopporsak-dashboard — klara.
Session #98: Operatörs-tidrapport + OEE-trendanalys förbättrad — klara.
Session #99: Rebotling skiftjämförelse-rapport + Operatörs-ranking med bonus — klara.
Session #100: VD Executive Dashboard + Rebotling historisk sammanfattning — klara.
Session #101: Rebotling kvalitetstrend-analys + Rebotling kapacitetsplanering — klara.
Session #102: Statistiksida sammanslagen överblick + Rebotling operatörs-dashboard — klara.
Session #103: Rebotling daglig briefing-rapport + Rebotling skiftöverlämningsprotokoll — klara.
Session #104: Rebotling operatörs-gamification + Rebotling prediktivt underhåll — klara.
Session #105: BUGGJAKT — backend ibc_ok-kolumnfix i remaining controllers + frontend subscription-läckor.

## ÖPPEN BACKLOG (prioritetsordning)

BUGGJAKT-FOKUS — inga nya features tills vidare.
- [ ] **Buggjakt: ibc_ok-kolumn remaining controllers** (pågår #105)
- [ ] **Buggjakt: Frontend subscription-läckor** (pågår #105)
- [ ] **Buggjakt: Auth & session**
- [ ] **Buggjakt: OEE-beräkningar verifiering**
- [ ] **Buggjakt: API-endpoints manuell test**

## BESLUTSDAGBOK (senaste 3)

### 2026-03-13 — Session #103 (klar)
Worker 1 (Daglig briefing-rapport): Morgonrapport med gårdagens resultat, stopporsaker, stationsstatus. Backend: DagligBriefingController.
Worker 2 (Skiftöverlämningsprotokoll): Digital checklista vid skiftbyte. Backend: SkiftoverlamningController.

### 2026-03-13 — Session #104 (klar)
Worker 1 (Operatörs-gamification): Poängsystem, badges, leaderboard. Backend: GamificationController.
Worker 2 (Prediktivt underhåll): MTBF, stopporsaks-heatmap, riskbedömning. Backend: PrediktivtUnderhallController.

### 2026-03-15 — Session #105 (pågår)
Worker A (Backend buggjakt): Fixa ibc_ok-kolumnfel i 7 remaining controllers (MaskinhistorikController, RebotlingStationsdetaljController, KapacitetsplaneringController, SkiftrapportController, DagligBriefingController, StatistikOverblickController, GamificationController) + övriga SQL-buggar.
Worker B (Frontend buggjakt): Granska subscription-läckor, template-buggar, felaktig datahantering i nya components (gamification, prediktivt-underhall, daglig-briefing, skiftoverlamning, operator-dashboard, vd-dashboard).
