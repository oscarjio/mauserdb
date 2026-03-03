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
- [ ] **My-Bonus realtidsvy**: Operatör ser eget bonusläge live (poäng, estimerat belopp, trend)
- [ ] **Bonus-Dashboard rankingkort**: Trendpilar (↑/↓ vs förra veckan), topplista med medaljer
- [ ] **Skiftmålsprediktor**: "Prognos: 87 IBC klart idag (mål: 100)" — live-beräkning på statistiksidan
- [ ] **Veckojämförelse-graf**: Denna vecka vs föregående vecka i rebotling-statistik

### 🟡 Medium prioritet
- [ ] **Rebotling-Skiftrapport sammanfattningskort**: Kvalitet%, OEE, Rastid, Vs. föregående skift
- [ ] **Operatörsprestanda-trend**: Graf per operatör — förbättring över tid (my-bonus eller bonus-dashboard)
- [ ] **OEE deep-dive**: Breakdown Tillgänglighet/Prestanda/Kvalitet i statistik
- [ ] **Stopporsaksanalys**: Visualisera stopp och raster i production-analysis
- [ ] **Admin: Mål per veckodag**: Måndag-fredag kan ha olika dagsmål
- [ ] **Förbättrad heatmap**: Tooltip med IBC/h + kvalitet%, val av KPI

### 🟢 Lägre prioritet
- [ ] **Systemstatus i admin**: Senaste PLC-ping, aktuellt löpnummer, DB-status
- [ ] **Bästa skift-topplista**: De 10 bästa historiska skiften
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
- StatusController.php: session_start(['read_and_close']) för att undvika PHP-session-låsning

---

## TEKNISKA OBSERVATIONER
- `rebotling_ibc` tabell har kumulativa fält per skift — aggregering med MAX() per skiftraknare
- BonusController har endpoints: operator, ranking, team, kpis, history, summary
- RebotlingController GET-endpoints: admin-settings, status, rast, statistics, day-stats, oee, cycle-trend, report, heatmap, getLiveStats
- rebotling-statistik.ts är ~1641 rader — mycket implementerat, läs noggrant innan du ändrar
- Angular routing finns i app.routes.ts — nya sidor måste registreras där

---

## BESLUTSDAGBOK
**2026-03-03**: Startade tre parallella worker-agenter. Ledaragent-system etablerat.
Nästa session: granska vad agenterna levererat, markera klara items i backlog, starta nästa omgång.

---

## NÄSTA SESSION — GÖR DETTA
1. Kör `git log --oneline -15` för att se vad som committats
2. Läs `dev-log.md` för uppdateringar från worker-agenter
3. Uppdatera backlog (markera klart, lägg till nya observationer)
4. Starta 2-3 nya worker-agenter på nästa prioriterade items
5. Uppdatera denna fil med nya beslut och observationer
