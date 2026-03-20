# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #195):
- [x] **PHP file I/O + array key audit** — 0 buggar, controllers ar proxys (Worker A)
- [x] **Angular HTTP retry + change detection audit** — 3 buggar (Worker B)

### Nasta buggjakt-items (session #196+):
- [ ] **Angular memory profiling** — kora memory profiling pa tunga sidor
- [ ] **PHP classes/ file I/O + array key audit** — logiken finns i classes/, inte controllers/
- [ ] **PHP classes/ numeric input validation audit** — saknade is_numeric/intval
- [ ] **Angular template null-safety audit** — saknade ?. och *ngIf-guards
- [ ] **PHP classes/ SQL injection deep audit** — string-interpolation i SQL

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
