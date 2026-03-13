# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-13 (session #94)*
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

## ÖPPEN BACKLOG (prioritetsordning)

- [x] **Kassationsorsak-analys** — Pareto-diagram top-5 kassationsorsaker (klar #94)
- [x] **Rebotling skiftöverlämning** — digital checklista vid skiftbyte (klar #94)
- [ ] **Operatörs-tidrapport** — automatisk tidrapport baserat på skiftschema + aktivitet
- [ ] **Realtids-notifikationer** — push-notiser vid kritiska händelser
- [ ] **Dashboards favoritlayout** — VD:s anpassningsbara startsida
- [ ] **Operatörs-schemaöversikt** — veckovis schemavy med bemanningsgrad
- [ ] **Rebotling underhållslogg** — registrera underhåll per station

## BESLUTSDAGBOK (senaste 3)

### 2026-03-13 — Session #92 (klar)
Worker 1 (Rebotling stationsdetalj-dashboard): Klickbar stationsvy — drill-down per station med realtids-OEE, senaste IBCer, stopphistorik, KPI-kort, trendgraf 30d. Backend: RebotlingStationsdetaljController.
Worker 2 (VD veckorapport + buggjakt): Veckosammanfattning med KPI-jämförelse vecka-för-vecka, trender, anomalier, top/bottom operatörer, stopporsaker, utskriftsvänlig.

### 2026-03-13 — Session #93 (klar)
Rebotling stationsdetalj-dashboard rebotling — duplicerat/förbättrat från #92.

### 2026-03-13 — Session #94 (klar)
Worker 1 (Kassationsorsak-analys): Pareto-diagram top-5/10 kassationsorsaker, drill-down per station/operatör, trendgraf, KPI-kort. Backend: KassationsanalysController.
Worker 2 (Rebotling skiftöverlämning): Digital checklista vid skiftbyte — skift-sammanfattning, öppna problem, interaktiv checklista, fritextnoteringar, historik. Backend: SkiftoverlamningController + ny DB-tabell.
