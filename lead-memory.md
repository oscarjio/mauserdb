# Lead Agent Memory — MauserDB

*Detta är ledaragentens persistenta minne. Uppdateras varje session.*
*Senast uppdaterad: 2026-03-03*

---

## Projektöversikt
IBC-tvätteri (Intermediate Bulk Container) produktionssystem.
- **Aktiv linje**: Rebotling (rebotling av IBC-tankar)
- **Inaktiva linjer**: Tvättlinje, Såglinje, Klassificeringslinje — byggs men EJ i drift
- **Användare**: Operatörer (bonus/live-data), Produktionschefer (statistik/rapporter), Admins

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
- [ ] **My-Bonus realtidsvy**: Operatör ser eget bonusläge live (poäng, estimerat belopp, trend) — bonus-agent jobbar på detta
- [ ] **Bonus-Dashboard rankingkort**: Trendpilar (↑/↓ vs förra veckan), topplista med medaljer — bonus-agent jobbar på detta
- [x] **Skiftmålsprediktor**: Levererat `c7faa1b` — live-prognos baserat på takt sedan 06:00 → 22:00
- [x] **Veckojämförelse-graf**: Levererat `c7faa1b` — bar chart denna/förra veckan, summakort med diff

### 🟡 Medium prioritet
- [ ] **Rebotling-Skiftrapport sammanfattningskort**: Kvalitet%, OEE, Rastid, Vs. föregående skift — skiftrapport-agent jobbar på detta
- [ ] **Operatörsprestanda-trend**: Graf per operatör — förbättring över tid (my-bonus/bonus-dashboard)
- [x] **OEE deep-dive**: Levererat `c7faa1b` — Tillgänglighet/Prestanda/Kvalitet-bars + 30-dagars trendgraf
- [ ] **Stopporsaksanalys**: Visualisera stopp och raster i production-analysis — ÖPPEN
- [ ] **Admin: Mål per veckodag**: Måndag-fredag kan ha olika dagsmål — skiftrapport-agent jobbar på detta
- [ ] **Förbättrad heatmap**: Tooltip med IBC/h + kvalitet%, val av KPI — ÖPPEN

### 🟢 Lägre prioritet
- [ ] **Systemstatus i admin**: Senaste PLC-ping, aktuellt löpnummer, DB-status
- [x] **Bästa skift-topplista**: Levererat `c7faa1b` — ny flik i production-analysis med guld/silver/brons + detailtabell
- [ ] **Skift-sammanfattning vid export**: PDF-export inkluderar sammanfattningskort
- [ ] **Executive dashboard förbättringar**: Bättre KPI-kort med trender

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
- `c7faa1b` — **Statistik-agent**: Veckojämförelse, Skiftmålsprediktor, OEE deep-dive (30-dagars trend), Bästa skift-topplista i production-analysis. Nya endpoints: week-comparison, oee-trend, best-shifts. Nya TypeScript-interfaces i RebotlingService.
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
**2026-03-03 — Statistik-agent klar**: Levererat veckojämförelse, skiftmålsprediktor, OEE deep-dive, bästa skift-topplista. 4 av 10 backlog-items klara. Bonus-agent och skiftrapport-agent fortfarande aktiva.
Nästa session: Granska bonus-agent och skiftrapport-agent. Starta ny omgång på återstående öppna items (stopporsaksanalys, heatmap-förbättring, operatörsprestanda-trend).

---

## NÄSTA SESSION — GÖR DETTA
1. Kör `git log --oneline -15` för att se vad som committats
2. Läs `dev-log.md` för uppdateringar från worker-agenter
3. Uppdatera backlog (markera klart, lägg till nya observationer)
4. Starta 2-3 nya worker-agenter på nästa prioriterade items
5. Uppdatera denna fil med nya beslut och observationer
