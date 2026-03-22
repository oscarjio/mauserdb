# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #260):
- [x] **PHP date/time timezone handling audit** — Worker A — rent
- [x] **PHP JSON encode/decode error handling** — Worker A — rent
- [x] **PHP integer overflow/boundary audit** — Worker A — rent
- [x] **Angular HTTP timeout consistency audit** — Worker B — 1 bugg fixad
- [x] **Angular memory leak audit (setInterval)** — Worker B — rent

### Nasta buggjakt-items (session #261+):
- [ ] **PHP error_log format consistency audit** — inkonsistenta loggformat, saknade kontextvariabler
- [ ] **PHP SQL transaction audit** — saknade BEGIN/COMMIT/ROLLBACK i multi-query-operationer
- [ ] **Angular router parameter validation audit** — route params som anvands utan validering/parseInt
- [ ] **Angular template expression complexity audit** — tunga berakningar i templates utan pipe/memo
- [ ] **PHP CORS/security headers consistency audit** — inkonsistenta headers mellan endpoints

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
