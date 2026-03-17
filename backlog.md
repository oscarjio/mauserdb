# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-17)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **PHP date/time handling audit** — 1 fix: date('o') for ISO-veckor (Worker A #135)
- [x] **Angular error state UI audit** — 4 maintenance-log-komponenter fixade (Worker B #135)
- [x] **Angular auth.guard unused route params** — 2 fixar: route -> _route (Worker B #135)
- [x] **PHP RebotlingAnalyticsController unused vars** — $shift fixad, $opRows false positive (Worker A #135)
- [x] **PHP null/edge case audit** — 7 fixar: json_decode guards, null-checks, empty arrays (Worker A #135)
- [ ] **PHP response format consistency audit** — granska att alla controllers returnerar konsekvent JSON-struktur
- [ ] **Angular chart destroy audit** — verifiera att alla Chart.js-instanser destroyas i ngOnDestroy
- [ ] **PHP file upload validation audit** — granska filuppladdning for storlek, typ, path traversal
- [ ] **PHP error_log format consistency** — granska att error_log() anvander konsekvent format och nivaser
- [ ] **Angular lazy loading route audit** — verifiera att alla lazy-loaded routes har korrekt preloading-strategi

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
