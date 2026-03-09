# Lead Agent Memory — MauserDB

*Senast uppdaterad: 2026-03-09 (session #45)*
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

Bug Hunts #1-#48 genomförda. Kodbasen har genomgått systematisk granskning:
formularvalidering, error states, subscribe-läckor, responsiv design, timezone, dead code,
chart.js lifecycle, export, PHP-robusthet, auth/session, data-konsistens, CSS/UX,
race conditions, accessibility, null safety, HTTP timeout/catchError.
Session #44: Workers slutförde ej. Session #45: Pareto redan implementerat. Bug Hunt #49 klar (dbc7b1a).
Bug Hunt #49: 12 console.error borttagna i rebotling-admin + rebotling-statistik. 25+ filer granskade.

## ÖPPEN BACKLOG (prioritetsordning)

### Rebotling-fokus (ägarens prioritet)
- [x] **Statistiksidan överblick** — KLAR (e708fc3): produktionsöverblick-panel med dagens prod, takt, OEE, veckotrend
- [x] **Pareto-diagram stopporsaker** — KLAR: redan implementerat (statistik-pareto-stopp komponent + backend)
- [x] **Cykeltid per operatör** — KLAR (3327f20): grouped bar chart + ranking-tabell + backend stddev
- [ ] **Annotationer i grafer** — markera driftstopp, helgdagar, nya operatörer i tidslinjen
- [ ] **Skiftrapport per operatör** — filtrerbar per specifik operatör

### Förbättringar
- [ ] **Bonus "What-if"-simulator** — admin justerar parametrar, ser effekt i realtid
- [ ] **Skiftbyte-PDF automatgenerering** — PDF vid skiftslut, länk i UI
- [ ] **Operatörsnärvaro-tracker** — kalendervy från rebotling_ibc-data
- [ ] **Live-ranking admin-konfig** — konfigurera KPI:er på TV-skärmen
- [ ] **IBC-kvalitets deep-dive** — bryt ner ej-godkända per avvisningsorsak

### Nya sidor
- [ ] **Månadsrapport** (`/rapporter/manad`) — auto-genererad sammanfattning, PDF-export
- [ ] **Skiftplaneringsvy** (`/admin/skiftplan`) — kalendervy, operatörer per skift

## BESLUTSDAGBOK (senaste 3)

### 2026-03-09 — Session #45 (slutresultat)
Worker 1 (Pareto): Horisontellt 80/20-diagram, kumulativ linje, 80%-markering, orange/grå färgdelning, CSV-export. Commit d8c4356.
Worker 2 (Cykeltid): Grouped bar chart (min/median/max), referenslinje, ranking-tabell med bästa operatör. Backend: stddev. Commits 3327f20 + 3ba6a5a.
Båda features klara och pushade.

### 2026-03-09 — Session #44
Statistiksidan överblick markerad klar. Workers misslyckades (inga commits).

### 2026-03-09 — Utveckling återupptagen
Ägaren godkänt stabil version i prod. Utvecklingsstopp upphävt. Fokus: rebotling-statistik + buggjakt.
