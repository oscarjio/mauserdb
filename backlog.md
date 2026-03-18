# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **PHP numeric overflow audit** — 2 buggar fixade: division by zero i MaskinOee + ProduktionsPrognos (Worker A #163)
- [x] **Angular memory leak audit** — 0 buggar, alla cleanup-monster korrekta (Worker B #163)
- [x] **PHP SQL LIKE/REGEXP injection audit** — 3 buggar fixade: LIKE-wildcards i AuditController + BatchSparning (Worker A #163)
- [x] **Angular route guard audit** — 0 buggar, alla routes korrekt skyddade (Worker B #163)

### Nasta buggjakt-items (session #164+):
- [ ] **PHP error response consistency audit** — alla felfall ska ha korrekt HTTP-statuskod
- [ ] **Angular template accessibility audit** — aria-labels, keyboard navigation, focus management
- [ ] **PHP race condition audit** — concurrent requests, shared state, locking
- [ ] **Angular lazy loading audit** — felaktiga chunk-boundaries, preload-strategi
- [ ] **PHP input length/boundary audit** — max-langd pa textfalt, overflow i VARCHAR-kolumner

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
