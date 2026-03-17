# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-17)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **PHP SQL prepared statement audit** — alla queries OK, inga sarbarheter (Worker A #134)
- [x] **PHP input sanitization audit** — 1 XSS-risk fixad i createAnnotation (Worker A #134)
- [x] **PHP unused variables cleanup** — 4 fixar i VpnController, RebotlingAnalyticsController, TvattlinjeController (Worker A #134)
- [x] **Angular form validation audit** — 8 formulaer fixade med disabled submit (Worker B #134)
- [x] **Angular unused declarations cleanup** — developerGuard borttagen, event-param fixad (Worker B #134)
- [x] **Angular subscription/observable audit** — 5 fixar: takeUntil + clearTimeout (Worker B #134)
- [ ] **PHP date/time handling audit** — granska tidszoner, date()-format, strtotime()-edge cases
- [ ] **Angular error state UI audit** — granska att alla komponenter visar felmeddelanden vid API-fel
- [ ] **Angular auth.guard unused route params** — diagnostics visar unused 'route' param pa rad 6 och 25
- [ ] **PHP RebotlingAnalyticsController unused vars** — diagnostics: $shift (rad 4531-4532), $opRows (rad 6616-6617)
- [ ] **PHP null/edge case audit** — granska saknade null-checks, tomma arrays, division-by-zero

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
