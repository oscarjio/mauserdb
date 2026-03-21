# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Avklarade (session #219):
- [x] **PHP classes/ file permission + path validation audit** — rent (Worker A)
- [x] **PHP classes/ array bounds + isset audit** — 5 buggar fixade (Worker A)
- [x] **Angular template strict null check audit** — rent (Worker B)
- [x] **Angular reactive polling cleanup audit** — rent (Worker B)

### Nasta buggjakt-items (session #220+):
- [ ] **PHP classes/ SQL transaction consistency audit** — saknade beginTransaction/commit/rollback vid multi-query
- [ ] **PHP classes/ error message information disclosure audit** — felmeddelanden som lacker DB-struktur/sokvagar
- [ ] **Angular form validation completeness audit** — saknade required/min/max/pattern-attribut pa input-falt
- [ ] **PHP classes/ type coercion + strict comparison audit** — == vs === dar typen spelar roll
- [ ] **Angular route guard + lazy loading consistency audit** — saknade guards pa skyddade rutter

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
