# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **PHP date/time audit** — 26 fixar, explicit timezone i DateTime (session #153)
- [x] **Angular HTTP retry audit** — alla OK, retry korrekt (session #153)
- [x] **PHP file upload audit** — inga uploads finns, inget att fixa (session #153)
- [x] **Angular route guard audit** — alla OK, authGuard/adminGuard korrekt (session #153)
- [x] **Angular unused import cleanup** — 57 duplicate imports sammanfogade (session #153)
- [ ] **PHP response header audit** — Content-Type konsistens, charset=utf-8, cache headers
- [ ] **Angular form validation audit** — required-falts markering, min/max validators, felmeddelanden
- [ ] **PHP SQL column name audit** — verifiera att alla SELECT-kolumner matchar DB-schema
- [ ] **PHP unused variable cleanup** — ta bort oanvanda variabler (diagnostics visar 7+ st)
- [ ] **Angular template expression audit** — nullable uttryck, async pipe, safe navigation

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
