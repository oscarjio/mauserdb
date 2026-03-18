# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #171):
- [x] **PHP CORS/preflight audit** — 3 buggar fixade (Worker A)
- [x] **PHP logging consistency audit** — 39 buggar fixade (Worker A)
- [x] **PHP JSON response consistency** — 0 buggar, redan konsekvent (Worker A)
- [x] **Angular form validation audit** — 63 buggar fixade (Worker B)
- [x] **Angular chart destroy audit** — 163 buggar fixade (Worker B)

### Nasta buggjakt-items (session #172+):
- [ ] **PHP file upload security audit** — filtyp-validering, storlek, path traversal
- [ ] **Angular unsubscribe audit** — prenumerationer i services som inte avslutas
- [ ] **PHP SQL query optimization** — saknade INDEX-kolumner, oanvanda JOINs
- [ ] **Angular template type-safety** — any-typade variabler i templates, saknade null-checks
- [ ] **PHP rate limiting audit** — endpoints utan throttling (login, API-anrop)

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
