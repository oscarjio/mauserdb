# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #196):
- [x] **PHP classes/ SQL injection + validation audit** — 5 buggar (Worker A)
- [x] **Angular template null-safety + subscription audit** — 1 bugg (Worker B)

### Nasta buggjakt-items (session #197+):
- [ ] **Angular memory profiling** — kora memory profiling pa tunga sidor
- [ ] **PHP classes/ date/time edge cases** — DST, timezone, datum-validering i classes/
- [ ] **PHP classes/ error response audit** — saknade HTTP-statuskoder, inkonsistent JSON-format
- [ ] **Angular HTTP error handling audit** — saknade catchError/timeout pa HTTP-anrop
- [ ] **PHP classes/ authorization audit** — saknade auth-kontroller pa endpoints

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
