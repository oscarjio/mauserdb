# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Avklarade (session #226):
- [x] **PHP classes/ file path validation audit** — rent, alla sokvagar hardkodade med __DIR__ (Worker A)
- [x] **PHP classes/ SQL COALESCE/IFNULL audit** — 19 buggar fixade i 16 filer (Worker A)
- [x] **PHP classes/ array access without isset audit** — 1 bugg fixad i SkiftoverlamningController (Worker A)
- [x] **Angular HTTP interceptor error handling audit** — rent, bada interceptors korrekta (Worker B)
- [x] **Angular template async pipe audit** — rent, alla anvander imperativ subscription (Worker B)

### Nasta buggjakt-items (session #227+):
- [ ] **PHP classes/ SQL prepared statement audit** — saknade parametriserade queries, strankkonkatenering i SQL
- [ ] **Angular component memory leak audit** — saknad unsubscribe, interval/timeout utan cleanup
- [ ] **PHP classes/ type juggling audit** — == istallet for === i sakerhetsrelaterade jamforelser
- [ ] **Angular route resolver error handling audit** — resolvers utan catchError som blockerar navigation
- [ ] **PHP classes/ error response consistency audit** — inkonsistenta error JSON-format mellan controllers

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
