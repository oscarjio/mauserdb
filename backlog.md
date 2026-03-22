# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #257):
- [ ] **PHP foreach by-reference audit** — Worker A — &$val utan unset efter loop
- [ ] **PHP static method state leakage audit** — Worker A — statiska variabler mellan requests
- [ ] **PHP PDO::ATTR_EMULATE_PREPARES audit** — Worker A — saknad eller felaktig konfiguration
- [ ] **Angular ngAfterViewChecked performance audit** — Worker B — tunga operationer i change detection
- [ ] **Angular HTTP interceptor error propagation audit** — Worker B — swallowed errors
- [ ] **Angular forkJoin/combineLatest completion audit** — Worker B — icke-completande observables

### Nasta buggjakt-items (session #258+):
- [ ] **PHP type juggling audit** — == vs === jamforelser med mixed types
- [ ] **PHP error_reporting/display_errors audit** — produktions-sakerhet
- [ ] **Angular template null-check audit** — saknade ?. i templates
- [ ] **Angular Router guard return type audit** — felaktiga guard-returvarden
- [ ] **PHP SQL LIMIT/OFFSET injection audit** — osanerade numeriska varden

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
