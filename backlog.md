# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #236):
- [ ] **PHP classes/ SQL transaction rollback audit** — Worker A
- [ ] **PHP classes/ rate limiting audit (dokumentera, ej fixa)** — Worker A
- [ ] **PHP classes/ CORS preflight OPTIONS handling audit** — Worker A
- [ ] **Angular template strict null-check audit (?. vs !)** — Worker B
- [ ] **Angular lazy-loaded module dependency audit** — Worker B

### Nasta buggjakt-items (session #237+):
- [ ] **PHP classes/ file locking audit** — saknad flock() pa delade filer
- [ ] **PHP classes/ PDO error mode audit** — ERRMODE_EXCEPTION vs ERRMODE_SILENT
- [ ] **Angular HTTP retry idempotency audit** — POST/DELETE som inte bor retryas
- [ ] **PHP classes/ timezone consistency audit** — date_default_timezone vs per-query
- [ ] **Angular form dirty-state warning audit** — canDeactivate-guards pa formularsidor

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
