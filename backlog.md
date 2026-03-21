# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Avklarade (session #221):
- [x] **PHP classes/ type coercion + strict comparison audit** — rent (Worker A)
- [x] **PHP classes/ SQL injection via dynamic ORDER BY/LIMIT audit** — rent (Worker A)
- [x] **Angular HTTP error retry + timeout consistency audit** — rent (Worker B)
- [x] **Angular template i18n completeness audit** — 47 buggar fixade (Worker B)

### Nasta buggjakt-items (session #222+):
- [ ] **PHP classes/ numeric overflow + boundary value audit** — intval/floatval pa extremvarden
- [ ] **PHP classes/ date/time edge case audit** — leap year, DST, midnight, month boundaries
- [ ] **Angular reactive forms validation sync audit** — template vs component validering ur synk
- [ ] **PHP classes/ file upload + MIME type validation audit** — saknad content-type-kontroll
- [ ] **Angular memory profiling audit** — stora dataset i tabeller/grafer utan pagination/virtualisering

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
