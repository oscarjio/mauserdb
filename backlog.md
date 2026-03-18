# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **PHP transaction audit** — 19 fixar i 9 controllers, beginTransaction/commit/rollBack (session #152)
- [x] **Angular memory leak audit** — alla OK, inga lackor (session #152)
- [x] **PHP edge case audit** — 3 fixar, fetch() null-check, division-by-zero guards (session #152)
- [x] **Angular catchError audit** — 37 fixar i 8 komponenter, saknade catchError i subscribe (session #152)
- [x] **Angular template type safety audit** — alla OK, trackBy+ngIf korrekt (session #152)
- [ ] **PHP date/time audit** — granska DateTime-hantering i alla controllers, timezone-konsistens
- [ ] **Angular HTTP retry audit** — granska retry-logik i services, exponential backoff, max retries
- [ ] **PHP file upload audit** — validera MIME-types, filstorlek, path traversal i upload-endpoints
- [ ] **Angular route guard audit** — verifiera att alla skyddade routes har authGuard, rollbaserad access

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
