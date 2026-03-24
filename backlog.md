# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #300):
- [ ] **PHP array_combine/array_zip** — missmatch i array-langder som ger false (Worker A)
- [ ] **PHP exception message leakage** — felmeddelanden som exponerar interna detaljer (Worker A)
- [ ] **PHP SQL transaction isolation** — dirty reads vid concurrent batch-operationer (Worker A)
- [ ] **Angular memory profiling** — komponentstorlek, DOM-nodantal i tunga vyer (Worker B)
- [ ] **Angular form state persistence** — formularvarden som forsvinner vid navigation (Worker B)

### Nasta buggjakt-items (session #301+):
- [ ] **PHP header() + exit() consistency** — alla API-endpoints som gor redirect utan exit()
- [ ] **PHP array_unique type juggling** — SORT_REGULAR vs SORT_STRING i array_unique()
- [ ] **Angular zone.js performance** — template-uttryck som triggar onnodig change detection
- [ ] **Angular HTTP caching** — GET-anrop som borde cachas men gor nytt request varje gang
- [ ] **PHP SQL index usage** — fragor utan index som ger full table scan pa stora tabeller

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
