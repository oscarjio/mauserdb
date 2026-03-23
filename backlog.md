# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #261):
- [x] **PHP error_log format consistency audit** — Worker A — rent
- [x] **PHP SQL transaction audit** — Worker A — rent
- [x] **PHP CORS/security headers consistency audit** — Worker A — rent
- [x] **Angular router parameter validation audit** — Worker B — rent
- [x] **Angular template expression complexity audit** — Worker B — rent

### Nasta buggjakt-items (session #262+):
- [ ] **PHP array key existence audit** — array_key_exists vs isset, saknade nyckelkontroller
- [ ] **PHP file upload validation audit** — saknade MIME/storlek/extension-kontroller
- [ ] **Angular HTTP retry/error recovery audit** — saknad retry-logik, felhantering i services
- [ ] **Angular form validation consistency audit** — saknade/inkonsistenta validatorer i formulr
- [ ] **PHP regex pattern safety audit** — ReDoS-risk, saknad input-sanering i preg_match

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
