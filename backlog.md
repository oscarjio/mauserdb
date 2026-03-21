# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Avklarade (session #220):
- [x] **PHP classes/ SQL transaction consistency audit** — rent (Worker A)
- [x] **PHP classes/ error message information disclosure audit** — rent (Worker A)
- [x] **Angular form validation completeness audit** — 4 buggar fixade (Worker B)
- [x] **Angular route guard + lazy loading consistency audit** — rent (Worker B)

### Nasta buggjakt-items (session #221+):
- [ ] **PHP classes/ type coercion + strict comparison audit** — == vs === dar typen spelar roll
- [ ] **PHP classes/ numeric overflow + boundary value audit** — intval/floatval pa extremvarden
- [ ] **Angular HTTP error retry + timeout consistency audit** — saknade retry-strategier
- [ ] **PHP classes/ SQL injection via dynamic ORDER BY/LIMIT audit** — user-input i ORDER BY
- [ ] **Angular template i18n completeness audit** — kvarstaende engelska strangar

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
