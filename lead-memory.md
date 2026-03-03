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
- [ ] **Operatörsprestanda-trend**: Graf per operatör — förbättring/försämring vecka för vecka (bonus-dashboard eller my-bonus)
- [ ] **Stopporsaksanalys**: Kategorisera och visualisera stopp/raster i production-analysis — varför stannar linjen?
- [ ] **Executive dashboard — VD-vy**: Ombygg till ren chefsöversikt: idag vs mål, veckostatus, topp-operatör, senaste skift KPIs. Ska ge svar på 10 sekunder.

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
- `82173ec` — **Bonus-agent**: My-bonus: statusbricka (rekordnivå/uppåt/etc.), IBC/h-trendgraf 7 skift med glidande snitt, skiftprognos-banner, PDF-export. Bonus-dashboard: trendpilar ↑↓→ vs föregående period, veckobonusmål-progressbar för team + per operatör. Bonus-admin: ny Prognos-flik (sök operatör → snittbonus/tier/IBC/h), veckobonusmål-konfiguration. Backend: operator_forecast endpoint, set_weekly_goal endpoint. Migration: `2026-03-03_bonus_weekly_goal.sql`.
- StatusController.php: session_start(['read_and_close']) för att undvika PHP-session-låsning

---

## TEKNISKA OBSERVATIONER
- `rebotling_ibc` tabell har kumulativa fält per skift — aggregering med MAX() per skiftraknare
- BonusController endpoints: operator, ranking, team, kpis, history, summary
- RebotlingController GET-endpoints: admin-settings, status, rast, statistics, day-stats, oee, cycle-trend, report, heatmap, getLiveStats, **week-comparison**, **oee-trend**, **best-shifts** (tillagda av statistik-agent)
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
