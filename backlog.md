# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #247):
- [ ] **PHP intval/floatval range validation audit (N-Z)** — Worker A
- [ ] **PHP header() redirect validation audit** — Worker A
- [ ] **PHP SQL ORDER BY injection audit** — Worker A
- [ ] **Angular canDeactivate guard audit** — Worker B
- [ ] **Angular change detection OnPush audit** — Worker B

### Nasta buggjakt-items (session #248+):
- [ ] **PHP array_map/array_filter callback type-safety audit** — callbacks utan typkontroll
- [ ] **PHP str_contains/strpos falsy-check audit** — strpos() === false vs == false
- [ ] **Angular HTTP timeout audit** — anrop utan timeout som kan hanga forever
- [ ] **PHP preg_match return value audit** — saknad kontroll av preg_match returvarde
- [ ] **Angular form validation UX audit** — felmeddelanden som saknas eller visas vid fel tillfalle

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
