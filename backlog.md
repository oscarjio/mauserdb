# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #249+):
- [ ] **PHP array_map/array_filter callback type-safety audit (N-Z)** — callbacks utan typkontroll
- [ ] **PHP str_contains/strpos falsy-check audit (N-Z)** — strpos() === false vs == false
- [ ] **PHP preg_match return value audit** — saknad kontroll av preg_match returvarde
- [ ] **Angular lazy-loaded module dependency audit** — saknade providers i standalone-komponenter
- [ ] **PHP file_put_contents error handling audit** — saknad felkontroll vid filskrivning

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
