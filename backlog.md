# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #241+):
- [ ] **PHP classes/ array_map/array_filter callback audit** — felaktiga callbacks
- [ ] **PHP classes/ header() call consistency audit** — Content-Type, charset, caching
- [ ] **Angular NgOnChanges null-check audit** — saknade null-guards i ngOnChanges
- [ ] **PHP classes/ SQL subquery performance audit** — korrelerade subqueries som kan vara JOINs
- [ ] **Angular template expression complexity audit** — tunga berakningar i templates utan memoization

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
