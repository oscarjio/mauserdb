# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-17)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [ ] **PHP date/time handling audit** — granska timezone-hantering, date()-format, strtotime()-edge cases
- [ ] **Angular HTTP retry/timeout audit** — verifiera att alla HTTP-anrop har timeout och retry-logik
- [ ] **PHP file upload validation** — granska MIME-type, filstorlek, sokvag-validering
- [ ] **Angular memory profiling** — granska komponenter for event listeners som inte tas bort
- [ ] **PHP session handling audit** — granska session_start, session_regenerate_id, session timeout
- [ ] **PHP unused variable cleanup** — ta bort oanvanda variabler ($ignored, $days, $multiplier)
- [ ] **Angular unused imports/declarations** — ta bort oanvanda TypeScript-importer och deklarationer

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
