# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-13 (session #98)*
*Fullständig historik: lead-memory-archive.md*

---

## Projektöversikt

IBC-tvätteri (1000L plasttankar i metallbur). Systemet ger VD realtidsöverblick + rättvis operatörsbonus.

**Roller:** VD (KPI:er, 10-sek överblick), Operatör (bonusläge live, motiverande), Admin (mål, regler, skift).
**Linjer:** Rebotling (AKTIV, bra data), Tvättlinje/Såglinje/Klassificeringslinje (EJ igång).
**Stack:** Angular 20+ → PHP/PDO → MySQL. PLC → plcbackend (rör ALDRIG) → DB.

## ÄGARENS DIREKTIV (2026-03-09)

- **FOKUS: Rebotling** — enda linjen med bra data
- Statistiksidan — enkel överblick hur produktionen går över tid
- VD ska förstå läget på 10 sekunder
- Buggjakt löpande
- Övriga rebotling-sidor — utveckla och förbättra

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
Session #98: Operatörs-tidrapport + OEE-trendanalys förbättrad — pågår.

## ÖPPEN BACKLOG (prioritetsordning)

- [ ] **Operatörs-tidrapport** — automatisk tidrapport baserat på skiftschema + aktivitet (PÅGÅR #98)
- [ ] **Rebotling OEE-trendanalys förbättrad** — jämför OEE mellan stationer, flaskhalsar, prediktion (PÅGÅR #98)
- [ ] **Dashboards favoritlayout** — VD:s anpassningsbara startsida
- [ ] **Realtids-notifikationer** — push-notiser vid kritiska händelser
- [ ] **Rebotling energi/resursförbrukning** — vatten/el/kemikalier per IBC
- [ ] **Rebotling skiftjämförelse-rapport** — jämför FM/EM/natt-produktivitet
- [ ] **Rebotling operatörs-ranking med bonus** — gamifierad ranking + poängsystem
- [ ] **Rebotling historisk sammanfattning** — auto-genererad månads-/kvartalsrapport

## BESLUTSDAGBOK (senaste 3)

### 2026-03-13 — Session #96 (klar)
Worker 1 (Rebotling underhållslogg): Ny sida — registrera underhåll per station, planerat/oplanerat, varaktighet, stopporsak-koppling, KPI-kort, bar chart. Backend: UnderhallsloggController + ny DB-tabell.
Worker 2 (Buggjakt #96): Systematisk granskning av kassationsanalys, skiftöverlämning, stationsdetalj, VD-veckorapport — memory leaks, SQL injection, UX, dark theme.

### 2026-03-13 — Session #97 (klar)
Worker 1 (Rebotling produktionsmål-uppföljning): Ny sida — dagliga/veckovisa mål vs utfall, progress-bar per skift, vecko-chart, historik, målhantering. Backend: ProduktionsmalController + ny DB-tabell.
Worker 2 (Stopporsak-dashboard): Ny sida — Pareto stopp-frekvens, stopptid per station, trend, per-orsak-tabell, detaljlista. Backend: StopporsakController.

### 2026-03-13 — Session #98 (pågår)
Worker 1 (Operatörs-tidrapport): Ny sida — automatisk tidrapport från skiftschema, per-operatör-sammanfattning, arbetstid-chart, detaljlista, CSV-export. Backend: TidrapportController.
Worker 2 (OEE-trendanalys förbättrad): Ny sida — OEE per station med breakdown, trendlinje med rullande snitt, flaskhalsidentifiering, period-jämförelse, prediktion. Backend: OeeTrendanalysController.
