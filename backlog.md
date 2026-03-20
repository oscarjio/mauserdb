# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #209):
- [ ] **PHP classes/ integer overflow audit** — intval/floatval bounds-checks, division by zero, is_numeric (Worker A)
- [ ] **PHP classes/ password policy audit** — minlangd, lockout, rate limiting (Worker A)
- [ ] **PHP classes/ SQL UNION/subquery audit** — felaktiga JOINs, saknade GROUP BY, N+1 (Worker A)
- [ ] **Angular change detection audit** — onPush, trackBy, funktionsanrop i templates (Worker B)
- [ ] **PHP classes/ error logging audit** — saknade error_log, inkonsekvent format, kanslig data (Worker B)

### Nasta buggjakt-items (session #210+):
- [ ] **Angular lazy loading verification** — verifiera att alla routes lazy-loadar korrekt, inga eager imports
- [ ] **PHP classes/ date/time edge case audit** — midnight, DST, timezone, date overflow
- [ ] **Angular HTTP retry logic audit** — verifiera retry-strategier, exponential backoff
- [ ] **PHP classes/ concurrent access audit** — race conditions, optimistic locking, deadlocks
- [ ] **Angular memory profiling** — identifiera komponenter med hog minnesanvandning

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
