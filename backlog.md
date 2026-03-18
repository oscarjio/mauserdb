# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [ ] **PHP SQL ORDER BY injection audit** — verifiera att ORDER BY-kolumner whitelist-valideras (Worker A #157)
- [ ] **Angular route param validation audit** — verifiera att route params valideras fore anvandning (Worker B #157)
- [ ] **PHP error response format audit** — verifiera konsekvent JSON-felformat i alla controllers (Worker A #157)
- [ ] **Angular loading state audit** — verifiera att alla HTTP-anrop visar laddningsindikator (Worker B #157)
- [ ] **PHP unused method audit** — identifiera och ta bort oanvanda metoder i controllers (Worker A #157)
- [ ] **Angular HTTP retry/timeout audit** — verifiera att alla HTTP-anrop har timeout och retry-logik
- [ ] **PHP input sanitization audit** — verifiera att all anvandarinput saneras (trim, strip_tags, etc)
- [ ] **Angular change detection audit** — granska OnPush-strategi och markForCheck-anvandning

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
