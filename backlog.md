# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-17)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **PHP HTTP method enforcement** — 2 fixar: login + alerts POST-krav (Worker A #132)
- [x] **Angular memory profiling** — alla komponenter OK, inga lakor (Worker B #132)
- [x] **PHP unused variables cleanup** — 6 fixar i 6 controllers (Worker A #132)
- [x] **PHP CORS/headers audit** — 3 fixar: redundant header, JSON_UNESCAPED_UNICODE (Worker A #132)
- [x] **Angular accessibility audit** — 13 fixar: aria-label pa knappar/selects/inputs (Worker B #132)
- [x] **Angular template null-safety** — 9 fixar: optional chaining, nullish coalescing (Worker B #132)
- [ ] **PHP error response consistency** — granska att alla error-svar har samma JSON-format
- [ ] **Angular route guard audit** — granska att alla admin-routes har AuthGuard
- [ ] **PHP session/auth timeout audit** — granska session-hantering och timeout-logik
- [ ] **Angular HTTP error interceptor** — granska att alla HTTP-fel hanteras konsistent
- [ ] **PHP file upload validation** — granska att filuppladdningar valideras korrekt

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
