# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #227):
- [ ] **PHP classes/ SQL prepared statement audit (A-M)** — strankkonkatenering i SQL, saknad parameterbindning (Worker A)
- [ ] **PHP classes/ type juggling audit (A-M)** — == istallet for === i sakerhetsrelaterade jamforelser (Worker A)
- [ ] **Angular component memory leak audit** — saknad unsubscribe, interval/timeout utan cleanup (Worker B)
- [ ] **Angular route resolver error handling audit** — resolvers utan catchError som blockerar navigation (Worker B)

### Nasta buggjakt-items (session #228+):
- [ ] **PHP classes/ SQL prepared statement audit (N-Z)** — samma som ovan, resterande controllers
- [ ] **PHP classes/ type juggling audit (N-Z)** — resterande controllers
- [ ] **PHP classes/ error response consistency audit** — inkonsistenta error JSON-format mellan controllers
- [ ] **Angular HTTP error message consistency audit** — felmeddelanden som inte visas for anvandaren
- [ ] **PHP classes/ unused variable/dead code audit** — oanvanda variabler, naabar kod efter return

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
