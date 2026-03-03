# Lead Agent Memory — MauserDB

*Detta är ledaragentens persistenta minne. Uppdateras varje session.*
*Senast uppdaterad: 2026-03-03*

---

## Projektöversikt — Affärskontext

**Företaget** återvinner och tvättar IBC-tankar (Intermediate Bulk Container, 1000-liters plasttankar i metallbur).
Tankarna tas in, inspekteras, tvättas/rebotlas och skickas tillbaka ut i cirkulation.

**Systemets syfte — två huvudmål:**
1. **VD-överblick**: VD har beställt systemet för att enkelt och i realtid kunna följa produktionen. Hen ska kunna se om linjen körs bra, om målen nås, vad som orsakar stopp — utan att behöva gå ut i produktionen.
2. **Rättvis individuell bonus**: Operatörerna arbetar i lag (2-3 per skift). Systemet mäter varje operatörs prestation objektivt (IBC/h, kvalitet%, effektivitet) och beräknar en rättvis bonus. Målet är att motivera operatörerna att hålla hög fart OCH hög kvalitet — inte bara kvantitet.

**Användarroller:**
- **VD / Chef**: Vill se KPI:er på hög nivå — nådde vi dagsmålet? Hur ser veckan ut? Vilken operatör presterar bäst? Executive dashboard, statistik, bonusöversikt.
- **Operatör**: Vill se sitt eget bonusläge live — "hur mycket bonus är jag på väg mot idag/denna vecka?" Motiverande, enkelt, mobilanpassat.
- **Admin**: Konfigurerar mål, bonusregler, skiftlängder. Kan se auditlogg.

**Produktionslinjer:**
- **Rebotling** (aktiv): Byter bottenventil + tvätt på IBC-tankar. Data från PLC i realtid.
- **Tvättlinje** (byggs): Högtryckstvätt av tankar.
- **Såglinje** (byggs): Sågar sönder trasiga burarna för återvinning.
- **Klassificeringslinje** (byggs): Sorterar inkommande tankar efter skick.

**Data-flöde**: PLC → `plcbackend/` → MySQL → PHP API → Angular frontend

**Design-filosofi för features:**
- VD ska kunna öppna dashboarden och på 10 sekunder förstå om produktionen går bra eller dåligt
- Operatören ska känna sig motiverad och rättvist behandlad — transparent bonusberäkning
- Allt ska vara enkelt, snabbt och fungera på både dator och surfplatta i produktionsmiljö

## Arkitektur
- Frontend: Angular 20+ standalone, `noreko-frontend/src/app/`
- Backend: PHP/PDO, `noreko-backend/classes/`, routing via `api.php?action=X&run=Y`
- DB: MySQL (EJ på denna server — ändringar via SQL-filer i `noreko-backend/migrations/`)
- PLC: `plcbackend/` — rör ALDRIG

## ABSOLUTA REGLER (bryt aldrig dessa)
1. Rör ALDRIG livesidorna: `rebotling-live`, `tvattlinje-live`, `saglinje-live`, `klassificeringslinje-live`
2. DB-ändringar → SQL-migreringsfil i `noreko-backend/migrations/YYYY-MM-DD_namn.sql` + `git add -f`
3. All UI-text på svenska
4. Commit + push när en feature är klar (inte halvfärdig kod)
5. Bygg alltid: `cd noreko-frontend && npx ng build` och fixa fel innan commit

## DRIFTSLÄGE — KONTINUERLIGT ARBETE
- **Agenterna stannar ALDRIG** — när en agent är klar startas en ny direkt på nästa backlog-item
- **Minst 2 worker-agenter ska alltid vara igång** parallellt
- **Ledaragenten** (huvudkonversationen) övervakar, uppdaterar backlog och startar nya agenter så fort gamla är klara
- När token-gränsen för huvudkonversationen närmar sig: commit lead-memory.md + push, cron-jobbet tar över

### Om backlogen tar slut — gör detta i ordning:
1. **Analysera koden** — läs igenom alla sidor och controllers, identifiera förbättringsmöjligheter (UX, prestanda, kodkvalitet, saknade edge cases). Lägg nya items i backlogen.
2. **Starta en bug hunting-agent** — se nedan
3. **Generera nya feature-idéer** baserat på affärskontexten (IBC-tvätteri, VD-översikt, operatörsbonus)

### Bug Hunting — starta regelbundet
- **Var 3:e session** (ungefär) ska en dedikerad bug hunting-agent startas parallellt med övriga workers
- Bug hunting-agentens uppdrag:
  - Läs igenom alla TypeScript-komponenter och leta efter: minnesläckor (saknat ngOnDestroy/clearInterval), race conditions i polling, felhantering som saknas, edge cases (null/undefined, tomma arrayer)
  - Kolla PHP-controllers: saknade auth-checks, SQL utan prepared statements, felaktiga HTTP-statuskoder
  - Kolla att alla nya features faktiskt bygger (`npx ng build`) utan fel
  - Fixa buggar direkt, commita med prefix "Bugfix: ..."
  - Rapportera alla fynd i lead-memory.md under "BUGGAR/TEKNISK SKULD"

## KOMMUNIKATION MED ÄGAREN
- **Dokumentera ALLT ägaren säger** i denna fil direkt — så att varken ledaragent eller workers behöver fråga om samma sak igen
- Ägaren ska aldrig behöva upprepa sig
- Om ägaren ger en ny instruktion: uppdatera lead-memory.md + commit inom samma svar

## ÄGARENS INSTRUKTIONER (kronologisk logg)
- *2026-03-03*: Fokus på rebotling. Övriga linjer ej igång.
- *2026-03-03*: Systemet är för VD som vill ha övergripande koll + ge rättvis individuell bonus åt operatörerna.
- *2026-03-03*: Databas ligger INTE på denna server — allt deployas manuellt. DB-ändringar via SQL-migreringsfiler.
- *2026-03-03*: Rör aldrig livesidorna (i produktion).
- *2026-03-03*: Agenterna ska aldrig stanna — alltid minst 2 igång, ledaragenten håller dom i arbete hela tiden.
- *2026-03-03*: Dokumentera allt som sägs i minnet — ägaren ska aldrig behöva upprepa sig.
- *2026-03-03*: Om backlogen tar slut → analysera koden och hitta förbättringar. Starta bug hunting-agent regelbundet (var 3:e session) som letar buggar och fixar dem.

## BUGGAR / TEKNISK SKULD
*(Uppdateras av bug hunting-agenter och workers som hittar problem)*
- Inget känt just nu — bug hunting-agent har ej körts ännu

---

## BACKLOG (prioritetsordning)

### 🔴 Hög prioritet
- [x] **My-Bonus realtidsvy**: Levererat `82173ec` — motiverande statusbricka (Rekordnivå!/Uppåt mot toppen!/etc.), IBC/h-trendgraf senaste 7 skiften med glidande snitt, skiftprognos-banner (förväntad bonus + IBC/vecka), inkluderat i PDF-export
- [x] **Bonus-Dashboard rankingkort**: Levererat `82173ec` — trendpilar ↑↓→ vs föregående period, veckobonusmål-progressbar för hela teamet, mini-progressbar per operatör i rankingtabellen
- [x] **Skiftmålsprediktor**: Levererat `c7faa1b` — live-prognos baserat på takt sedan 06:00 → 22:00
- [x] **Veckojämförelse-graf**: Levererat `c7faa1b` — bar chart denna/förra veckan, summakort med diff

### 🟡 Medium prioritet
- [x] **Rebotling-Skiftrapport sammanfattningskort**: Levererat — 6 KPI-kort (Total IBC, Kvalitet%, OEE, Drifttid, Rasttid, Delta vs. föregående skift), sorterbar tabell, skiftväljare, textsök, förbättrad PDF+Excel-export
- [ ] **Operatörsprestanda-trend**: Graf per operatör — förbättring över tid — ÖPPEN (nästa omgång)
- [x] **OEE deep-dive**: Levererat `c7faa1b` — Tillgänglighet/Prestanda/Kvalitet-bars + 30-dagars trendgraf
- [ ] **Stopporsaksanalys**: Visualisera stopp och raster i production-analysis — ÖPPEN
- [x] **Admin: Mål per veckodag**: Levererat — mån–sön individuella mål, `rebotling_weekday_goals` tabell. Skifttider konfigurerbart. Systemstatus-sektion med PLC-ping, löpnummer, DB-status.
- [ ] **Förbättrad heatmap**: Tooltip med IBC/h + kvalitet%, val av KPI — ÖPPEN

### 🟢 Lägre prioritet
- [x] **Systemstatus i admin**: Levererat — PLC-ping ålder, löpnummer, DB-status
- [x] **Bästa skift-topplista**: Levererat `c7faa1b` — guld/silver/brons i production-analysis
- [x] **Skift-sammanfattning vid export**: Levererat — PDF + Excel inkluderar sammanfattningskort
- [ ] **Executive dashboard förbättringar**: KPI-kort med trendpilar, jämförelse vs förra veckan

---

## NÄSTA BATCH (session 2 — startas av lead-agent via cron)

### 🔴 Hög prioritet
- [x] **Operatörsprestanda-trend**: Levererat `ef505e6` — stapeldiagram per ISO-vecka (8 veckor), grön/orange vs snitt, streckad referenslinje, lagsjämförelse (IBC/h, Kvalitet%, Bonus) i my-bonus. Ny endpoint `bonus&run=weekly_history`.
- [x] **Stopporsaksanalys**: Levererat `ef505e6` — ny flik i production-analysis: horisontell tidslinje (grön=kör, gul=rast), summering-pills, 14-dagars bar chart, KPI-kort. Proxy-data med tydlig kommentar om PLC-integration.
- [x] **Executive dashboard — VD-vy**: Levererat `fb05cce` — SVG-cirkulär progress, prognos, OEE-trendpil, 7-dagars bar chart (grön/röd vs mål), veckokort, operatörstabell. Enda HTTP-anrop via ny endpoint `exec-dashboard`.

### 🟡 Medium prioritet
- [ ] **Förbättrad heatmap i statistik**: Interaktiv hover-tooltip, val av KPI (IBC/h, kvalitet%, OEE)
- [ ] **Mobilanpassning**: Rebotling-live och my-bonus ska fungera bra på surfplatta i produktionsmiljön
- [ ] **Bonushistorik-graf i my-bonus**: Stapeldiagram per vecka de senaste 8 veckorna — "min bonusutveckling"
- [ ] **Skiftjämförelse**: Välj två datum och jämför nyckeltal sida vid sida

### 🟢 Lägre prioritet
- [ ] **Notifikation/varning i admin**: Om linjen inte rapporterat data på >15 min → varningsvisning
- [ ] **Tvättlinje/Såglinje förberedelsearbete**: Sätt upp grundstruktur för när de linjerna startar
- [ ] **Audit-log förbättring**: Filtrerbar, sökbar, exporterbar

---

## AKTIVA AGENTER (senaste session 2026-03-03)
Tre agenter startades parallellt:
- **Bonus-agent** (aba3e1e2b4c1f1692): bonus-dashboard, my-bonus, bonus-admin, BonusController
- **Statistik-agent** (a9ebe78f439b80657): rebotling-statistik, production-analysis, RebotlingController
- **Skiftrapport-agent** (a016503aaac3d553c): rebotling-skiftrapport, rebotling-admin, SkiftrapportController

---

## GENOMFÖRT (commit-historik)
- `ecc6b40` — Auth fix: APP_INITIALIZER väntar på fetchStatus() via firstValueFrom()
- `771e128` — auto-develop.sh och dev-log.md tillagda
- `d4db30b` — lead-agent.sh + lead-memory.md: orchestrator-system etablerat
- `c7faa1b` — **Statistik-agent**: Veckojämförelse, Skiftmålsprediktor, OEE deep-dive (30-dagars trend), Bästa skift-topplista. Nya endpoints: week-comparison, oee-trend, best-shifts.
- **Skiftrapport-agent**: Sammanfattningskort (6 KPIs), sorterbar tabell, skiftväljare, textsök, bonus-estimat i detaljvy, veckodagsmål mån–sön i admin, systemstatus (PLC-ping, löpnummer, DB). Nya tabeller: `rebotling_weekday_goals`, `rebotling_shift_times`. Migration: `2026-03-03_rebotling_settings_weekday_goals.sql`.
- `ef505e6` — **Operatörstrend-agent**: My-bonus veckoutvecklingsgraf (8v), lagsjämförelse. Production-analysis stoppanalys-flik med tidslinje + 14-dagars chart. Ny endpoint weekly_history i BonusController.
- `fb05cce` — **VD-dashboard-agent**: Executive dashboard ombyggd — SVG-cirkulär progress, prognos, OEE-trendpil vs igår, 7-dagars bar chart (grön/röd), veckokort, operatörstabell. Ny endpoint exec-dashboard (ett anrop för allt).
- `82173ec` — **Bonus-agent**: My-bonus: statusbricka (rekordnivå/uppåt/etc.), IBC/h-trendgraf 7 skift med glidande snitt, skiftprognos-banner, PDF-export. Bonus-dashboard: trendpilar ↑↓→ vs föregående period, veckobonusmål-progressbar för team + per operatör. Bonus-admin: ny Prognos-flik (sök operatör → snittbonus/tier/IBC/h), veckobonusmål-konfiguration. Backend: operator_forecast endpoint, set_weekly_goal endpoint. Migration: `2026-03-03_bonus_weekly_goal.sql`.
- StatusController.php: session_start(['read_and_close']) för att undvika PHP-session-låsning

---

## TEKNISKA OBSERVATIONER
- `rebotling_ibc` tabell har kumulativa fält per skift — aggregering med MAX() per skiftraknare
- BonusController endpoints: operator, ranking, team, kpis, history, summary
- RebotlingController GET-endpoints: admin-settings, status, rast, statistics, day-stats, oee, cycle-trend, report, heatmap, getLiveStats, week-comparison, oee-trend, best-shifts, **exec-dashboard**, **weekday-goals**, **shift-times**, **system-status**
- rebotling-statistik.ts är nu **ännu längre** (veckojämförelse, prediktor, OEE deep-dive tillagda) — läs noggrant
- RebotlingService har nya interfaces: WeekComparisonResponse, OEETrendResponse, BestShiftsResponse
- Angular routing finns i app.routes.ts — nya sidor måste registreras där
- Skiftmålsprediktor beräknar takt från 06:00 → projicerar till 22:00 (produktionsdag)

---

## BESLUTSDAGBOK
**2026-03-03 — Session 1**: Startade tre parallella worker-agenter. Ledaragent-system etablerat.
**2026-03-03 — Statistik-agent klar**: Veckojämförelse, skiftmålsprediktor, OEE deep-dive, bästa skift-topplista.
**2026-03-03 — Skiftrapport-agent klar**: Sammanfattningskort, sorterbar tabell, skiftväljare, textsök, bonus-estimat, veckodagsmål i admin, systemstatus.
**2026-03-03 — Bonus-agent klar**: My-bonus statusbricka + trendgraf + prognos. Bonus-dashboard trendpilar + veckobonusmål. Bonus-admin prognos-flik. Alla tre agenter klara. **10 av 12 backlog-items levererade session 1.**
Nästa session (cron ~3h): Starta ny omgång på återstående öppna items + ny batch features.

---

## NÄSTA SESSION — GÖR DETTA
1. Kör `git log --oneline -15` för att se vad som committats
2. Läs `dev-log.md` för uppdateringar från worker-agenter
3. Uppdatera backlog (markera klart, lägg till nya observationer)
4. Starta 2-3 nya worker-agenter på nästa prioriterade items
5. Uppdatera denna fil med nya beslut och observationer
