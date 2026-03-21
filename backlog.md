# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Avklarade (session #222):
- [x] **PHP classes/ numeric overflow + boundary value audit** — 8 buggar fixade, floatval NAN/INF bypass (Worker A)
- [x] **PHP classes/ date/time edge case audit** — rent (Worker A)
- [x] **Angular reactive forms validation sync audit** — rent, alla anvander template-driven (Worker B)
- [x] **Angular memory profiling audit** — 3 buggar fixade, chartTimers minneslackor (Worker B)

### Nasta buggjakt-items (session #223+):
- [ ] **PHP classes/ file upload + MIME type validation audit** — saknad content-type-kontroll
- [ ] **PHP classes/ array key existence audit** — isset/array_key_exists fore access
- [ ] **Angular HTTP interceptor error normalization audit** — inkonsekvent felhantering mellan interceptor och komponenter
- [ ] **PHP classes/ string encoding + multibyte audit** — strlen vs mb_strlen, substr vs mb_substr
- [ ] **Angular template null-safe navigation audit** — saknade ?. i asynkrona data-bindings

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
