# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **PHP date/time edge case audit** — 7 strtotime + 2 DateTime + 1 preg_match (session #156)
- [x] **Angular memory leak audit** — 15 setTimeout guards i 12 komponenter (session #156)
- [x] **PHP file path traversal audit** — alla sokvagar sakra, inga fixar kravdes (session #156)
- [x] **PHP transaction consistency audit** — 3 transaction wraps (session #156)
- [x] **Angular form reset audit** — alla formuler redan korrekta (session #156)
- [ ] **PHP SQL ORDER BY injection audit** — verifiera att ORDER BY-kolumner whitelist-valideras
- [ ] **Angular route param validation audit** — verifiera att route params valideras fore anvandning
- [ ] **PHP error response format audit** — verifiera konsekvent JSON-felformat i alla controllers
- [ ] **Angular loading state audit** — verifiera att alla HTTP-anrop visar laddningsindikator
- [ ] **PHP unused method audit** — identifiera och ta bort oanvanda metoder i controllers

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
