# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #235):
- [ ] **PHP classes/ error logging completeness audit** — Worker A
- [ ] **PHP classes/ session fixation/regeneration audit** — Worker A
- [ ] **Angular HTTP interceptor error normalization audit** — Worker B
- [ ] **Angular component memory profiling (detached DOM, stora objekt)** — Worker B

### Nasta buggjakt-items (session #236+):
- [ ] **PHP classes/ SQL transaction rollback audit** — saknade rollback i catch-block efter beginTransaction
- [ ] **PHP classes/ rate limiting audit** — saknad rate limiting pa login/API-endpoints
- [ ] **Angular template strict null-check audit** — ?. vs ! operatorer, saknade null-guards i templates
- [ ] **PHP classes/ CORS preflight OPTIONS handling audit** — OPTIONS-requests som inte hanteras korrekt
- [ ] **Angular lazy-loaded module dependency audit** — saknade providers i lazy-loaded modules

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
