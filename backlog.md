# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #177):
- [ ] **PHP file permission audit** — Worker A granskar filskrivning (loggar, uploads) for permissions + path traversal
- [ ] **PHP SQL injection re-audit** — Worker A granskar alla controllers for osaker input i SQL
- [ ] **Angular HTTP interceptor audit** — Worker B granskar interceptor for edge cases
- [ ] **Angular chart memory audit** — Worker B granskar Chart.js-instanser for minnesläckor

### Nasta buggjakt-items (session #178+):
- [ ] **PHP error response consistency** — kontrollera att alla endpoints returnerar enhetligt JSON-format vid fel
- [ ] **Angular form reset audit** — verifiera att formulär nollställs korrekt efter submit/cancel
- [ ] **PHP date/timezone edge cases** — granska date-beräkningar runt midnatt, DST, årsskiften
- [ ] **Angular route param validation** — kontrollera att route-params (id, period) valideras före API-anrop
- [ ] **PHP array key existence** — granska $_GET/$_POST for saknade isset/array_key_exists-kontroller

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
