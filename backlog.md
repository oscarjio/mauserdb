# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #286):
- [x] **PHP header/redirect consistency** — rent (inga redirects anvands)
- [x] **Angular Router navigation edge cases** — rent (alla subscriptions korrekt)
- [x] **PHP SQL date range queries** — rent (alla BETWEEN korrekt, datum valideras)
- [x] **Angular HttpClient response handling** — rent (alla catchError, inga subscribe-i-subscribe)
- [x] **PHP password_hash/token timing** — 1 bugg fixad (hash_equals i RegisterController)

### Nasta buggjakt-items (session #287+):
- [ ] **PHP array type coercion** — in_array() utan strict mode, array_search() returvarde-check
- [ ] **Angular ngOnChanges edge cases** — SimpleChanges null-check, firstChange-hantering
- [ ] **PHP file path traversal** — saknad basename/realpath-validering i filoperationer
- [ ] **Angular RxJS operator ordering** — takeUntil sist i pipe, switchMap vs mergeMap val
- [ ] **PHP SQL GROUP BY strictness** — kolumner i SELECT utan GROUP BY/aggregering

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
