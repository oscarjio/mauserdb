# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **Angular error state UI audit** — 7 fixar i maskin-oee, stopptidsanalys, rebotling-sammanfattning, stationsdetalj (session #151)
- [x] **PHP unused vars kvar** — 3 fixade (non-capturing catch), $opRows var false positive (session #151)
- [x] **PHP response format audit** — alla controllers redan konsekvent JSON-format (session #151)
- [x] **Angular form validation audit** — alla formular redan korrekta (session #151)
- [x] **PHP SQL query audit** — 3 fixar i WeeklyReportController: kritisk JOIN-bugg, felaktig kolumn (session #151)
- [ ] **PHP transaction audit** — granska INSERT/UPDATE utan transaktioner i multi-step operations
- [ ] **Angular memory leak audit** — granska charts och polling for saknade unsubscribe/destroy
- [ ] **PHP edge case audit** — boundary conditions, tomma resultat, null-hantering
- [ ] **Angular template type safety audit** — granska templates for implicit any, felaktiga typer
- [ ] **PHP date/time audit** — granska DateTime-hantering i alla controllers, timezone-konsistens

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
