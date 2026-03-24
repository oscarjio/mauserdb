# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #301):
- [ ] **PHP header() + exit() consistency** — alla API-endpoints som gor redirect utan exit() (Worker A)
- [ ] **PHP array_unique type juggling** — SORT_REGULAR vs SORT_STRING i array_unique() (Worker A)
- [ ] **PHP SQL index usage** — fragor utan index som ger full table scan (Worker A)
- [ ] **Angular zone.js performance** — template-uttryck som triggar onnodig change detection (Worker B)
- [ ] **Angular HTTP caching** — GET-anrop som borde cachas men gor nytt request varje gang (Worker B)

### Nasta buggjakt-items (session #302+):
- [ ] **PHP date() timezone consistency** — explicit timezone i alla date()/strtotime() vs server default
- [ ] **PHP SQL COUNT vs EXISTS** — ineffektiva COUNT(*) dar EXISTS racker
- [ ] **Angular renderer security** — innerHTML/bypassSecurityTrust utan sanitering
- [ ] **Angular lazy loading chunk errors** — saknad felhantering vid chunk-laddningsfel
- [ ] **PHP mb_string consistency** — blandning av strlen/substr och mb_strlen/mb_substr

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
