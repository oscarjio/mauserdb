# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #212):
- [x] **PHP classes/ file path traversal re-audit** — RENT, inga user-controlled paths (Worker A)
- [x] **PHP classes/ session handling audit** — RENT, korrekt konfigurerat (Worker A)
- [x] **PHP classes/ SQL query parameter binding audit** — RENT, alla prepared statements (Worker A)
- [x] **Angular change detection audit** — RENT, inga OnPush-problem (Worker B)
- [x] **Angular a11y audit** — 20 buggar fixade: aria-labels, visually-hidden, role="alert" (Worker B)

### Nasta buggjakt-items (session #213+):
- [ ] **PHP classes/ error logging audit** — konsekvent error_log() + saknade try/catch
- [ ] **Angular HTTP interceptor audit** — felhantering, retry-logik, token refresh
- [ ] **PHP classes/ date/time edge case re-audit** — DST, midnight, month boundaries
- [ ] **Angular template strict null check** — safe navigation (?.) i alla bindings
- [ ] **PHP classes/ CORS/headers audit** — saknade Content-Type, CORS-policyer

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
