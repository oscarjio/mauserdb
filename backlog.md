# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #210):
- [ ] **PHP classes/ date/time edge case audit** — midnight, DST, timezone, date overflow (Worker A)
- [ ] **PHP classes/ concurrent access audit** — race conditions, optimistic locking, deadlocks (Worker A)
- [ ] **Angular lazy loading verification** — verifiera att alla routes lazy-loadar korrekt (Worker B)
- [ ] **Angular HTTP retry logic audit** — verifiera retry-strategier i services (Worker B)
- [ ] **Angular memory profiling** — identifiera komponenter med subscription/timer-lackor (Worker B)

### Nasta buggjakt-items (session #211+):
- [ ] **PHP classes/ input sanitization audit** — htmlspecialchars, strip_tags, filter_var konsistens
- [ ] **PHP classes/ error response consistency** — enhetliga JSON-felformat over alla endpoints
- [ ] **Angular form validation audit** — saknade validators, felmeddelanden, edge cases
- [ ] **PHP classes/ numeric precision audit** — float-jamforelser, avrundning, penningbelopp
- [ ] **Angular route guard audit** — verifiera att skyddade routes kraver inloggning

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
