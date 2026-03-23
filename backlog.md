# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #267):
- [x] **PHP file I/O safety** — Worker A — 2 buggar fixade (fopen felkontroll)
- [x] **PHP session fixation/regeneration** — Worker A — rent
- [x] **PHP CORS/preflight consistency** — Worker A — 2 buggar fixade (X-CSRF-Token headers)
- [x] **Angular route parameter validation** — Worker B — rent
- [x] **Angular environment config audit** — Worker B — rent
- [x] **Angular lazy loading chunk error handling** — Worker B — 1 bugg fixad (GlobalErrorHandler)

### Nasta buggjakt-items (session #268+):
- [ ] **PHP timezone consistency** — date_default_timezone_set(), DateTime vs strtotime blandning
- [ ] **Angular HTTP interceptor error handling** — globala 401/403/500 handlers
- [ ] **PHP array key validation** — saknade array_key_exists/isset vid extern data
- [ ] **Angular memory profiling** — stora dataset i tabeller, pagination utan limit
- [ ] **PHP PDO error mode consistency** — ERRMODE_EXCEPTION vs ERRMODE_SILENT

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
