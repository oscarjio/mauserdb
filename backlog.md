# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #209):
- [x] **PHP classes/ integer overflow audit** — intval bounds-check i MaintenanceController (Worker A)
- [x] **PHP classes/ password policy audit** — losenordskomplexitet + username-baserad lockout (Worker A)
- [x] **PHP classes/ SQL UNION/subquery audit** — N+1 query refaktoriserad i MaintenanceController (Worker A)
- [x] **Angular change detection audit** — cachad todayStr + borttagen HostListener i drifttids-timeline (Worker B)
- [x] **PHP classes/ error logging audit** — error_log i 12 catch-block over 10 PHP-filer (Worker B)

### Nasta buggjakt-items (session #210+):
- [ ] **Angular lazy loading verification** — verifiera att alla routes lazy-loadar korrekt
- [ ] **PHP classes/ date/time edge case audit** — midnight, DST, timezone, date overflow
- [ ] **Angular HTTP retry logic audit** — verifiera retry-strategier, exponential backoff
- [ ] **PHP classes/ concurrent access audit** — race conditions, optimistic locking, deadlocks
- [ ] **Angular memory profiling** — identifiera komponenter med hog minnesanvandning

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
