# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-17)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **PHP error response consistency** — 19 controllers fixade (Worker A #133)
- [x] **PHP session/auth timeout audit** — OK, inga problem (Worker A #133)
- [x] **Angular route guard audit** — 3 fixar (Worker B #133)
- [x] **Angular HTTP error interceptor** — 2 fixar + clearSession (Worker B #133)
- [ ] **PHP SQL prepared statement audit** — granska att alla queries anvander prepared statements korrekt
- [ ] **Angular form validation audit** — granska formularsvalidering och felmeddelanden
- [ ] **PHP unused variables cleanup** — VpnController, RebotlingController, RebotlingAnalyticsController, TvattlinjeController (fran diagnostics)
- [ ] **Angular unused declarations cleanup** — developerGuard, alertsService, event (fran diagnostics)
- [ ] **PHP input sanitization audit** — granska att user-input saneras korrekt i alla controllers

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
