# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **PHP error logging consistency** — 15 fixar (session #150)
- [x] **PHP unused variables cleanup** — 6 unused $e fixade (session #150)
- [x] **PHP input validation audit** — 7 trim()-fixar (session #150)
- [x] **Angular lazy loading audit** — OK, alla routes lazy-loaded korrekt (session #150)
- [x] **Angular unused imports cleanup** — OK, alla imports anvands (session #150)
- [x] **Angular template accessibility** — 49 fixar i 12 filer (session #150)
- [ ] **Angular error state UI audit** — visa felmeddelanden i template vid HTTP-fel
- [ ] **PHP unused vars kvar** — $ignored (RebotlingController:2789), $opRows (RebotlingAnalyticsController:6671/6673), $dtEx (NewsController:579), $multiplier (BonusAdminController:1795)
- [ ] **PHP response format audit** — granska att alla controllers returnerar konsekvent JSON-format
- [ ] **Angular form validation audit** — granska alla formulär for saknad validering, min/max, required
- [ ] **PHP SQL query audit** — granska komplexa queries for korrekthet, saknade JOINs, felaktiga GROUP BY

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
