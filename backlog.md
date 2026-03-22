# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #258):
- [ ] **PHP type juggling audit** — Worker A — == vs === med mixed types
- [ ] **PHP error_reporting/display_errors audit** — Worker A — produktionssakerhet
- [ ] **PHP SQL LIMIT/OFFSET injection audit** — Worker A — osanerade numeriska varden
- [ ] **Angular template null-check audit** — Worker B — saknade ?. i templates
- [ ] **Angular Router guard return type audit** — Worker B — felaktiga guard-returvarden
- [ ] **Angular service URL consistency audit** — Worker B — hardkodade/felaktiga URL:er

### Nasta buggjakt-items (session #259+):
- [ ] **PHP file_get_contents/curl error handling audit** — saknad felhantering vid externa anrop
- [ ] **PHP session handling audit** — session fixation, regenerate_id, timeout
- [ ] **Angular change detection strategy audit** — OnPush vs Default, oanvanda ChangeDetectorRef
- [ ] **Angular lazy loading route audit** — felaktiga loadChildren/import-sokvagar

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
