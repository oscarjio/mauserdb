# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #167):
- [x] **PHP SQL query optimization audit** — 9 buggar: 7 SELECT * + 2 N+1 queries (Worker A)
- [x] **PHP session/auth edge cases audit** — 3 buggar: inactive login + missing auth (Worker A)
- [x] **Angular template null-safety audit** — 3 buggar: slice/ngFor null-guards (Worker B)
- [x] **Angular route guard edge cases** — 0 buggar, solid implementation (Worker B)

### Nasta buggjakt-items (session #168+):
- [ ] **PHP response consistency audit** — saknade Content-Type headers, inkonsekvent JSON-format
- [ ] **PHP error logging completeness audit** — saknad error_log i catch-blocks, inkonsekvent loggformat
- [ ] **Angular HTTP error message audit** — felmeddelanden som visas for anvandare vid API-fel
- [ ] **PHP integer overflow/type coercion audit** — intval() pa stora tal, float-jamforelser
- [ ] **Angular form reset/dirty state audit** — formularsidor som inte aterställer state korrekt

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
