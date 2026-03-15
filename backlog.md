# Worker Backlog — MauserDB

*Lead-agenten fyller på. Workers plockar uppgifter härifrån.*
*Håll 5-10 öppna items. Markera med [x] när klart.*

## PRIORITET: BUGGJAKT (2026-03-15)

Ägaren har bett oss fokusera på att hitta och fixa buggar. Inga nya features.

- [x] **Buggjakt: ibc_ok-kolumn i remaining controllers** — 7 controllers granskade, 4 hade riktiga SQL-buggar (RankingHistorik, OperatorRanking, Produktionsmal, VdDashboard) — fixade
- [x] **Buggjakt: Frontend subscription-läckor** — 41 components auditerade, inga läckor. 3 error-handling-buggar fixade (vd-dashboard, gamification, skiftoverlamning)
- [ ] **Buggjakt: Auth & session** — granska session-hantering, rate limiting, CORS-regler
- [ ] **Buggjakt: OEE-beräkningar verifiering** — verifiera att fixade controllers räknar OEE korrekt
- [ ] **Buggjakt: API-endpoints manuell test** — testa endpoints med curl, verifiera JSON-svar
- [ ] **Buggjakt: Unused variables cleanup** — RankingHistorikController, OperatorRankingController, ProduktionsmalController, ProduktionskalenderController har oanvända variabler
- [ ] **Buggjakt: vd-dashboard unused imports** — of, catchError, timeout, isFetching importerade men oanvända

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursförbrukning
- [ ] Rebotling leveransplanering
- [ ] Rebotling avvikelsehantering
- [ ] Rebotling batch-spårning
