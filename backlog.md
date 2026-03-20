# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #197):
- [x] **PHP classes/ date/time + error response audit** — 6 buggar (Worker A)
- [x] **Angular statistik/ HTTP + lifecycle audit** — 8 buggar (Worker B)

### Nasta buggjakt-items (session #198+):
- [ ] **PHP classes/ authorization audit** — saknade auth-kontroller pa endpoints
- [ ] **Angular memory profiling** — kora memory profiling pa tunga sidor
- [ ] **PHP classes/ file upload + path traversal audit** — saknad validering pa filuppladdning
- [ ] **Angular form validation audit** — saknad/inkonsistent input-validering i formularsidor
- [ ] **PHP classes/ SQL query performance audit** — saknade index, N+1-queries, onodiga JOINs

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
