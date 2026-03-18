# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **PHP SQL query edge case audit** — 0 buggar (Worker A #160)
- [x] **Angular template null-safety audit** — 0 buggar (Worker B #160)
- [x] **PHP date/time parsing audit** — 0 buggar (Worker A #160)
- [x] **Angular HTTP interceptor audit** — 0 buggar (Worker B #160)
- [x] **PHP array access audit** — 0 buggar (Worker A #160)
- [x] **Angular router guard audit** — 0 buggar (Worker B #160)

### Nasta buggjakt-items (session #161+):
- [ ] **PHP error logging audit** — granska error_log/trigger_error, saknade loggningar vid fel
- [ ] **Angular change detection audit** — onPush-strategier, onatta rerenders, performance
- [ ] **PHP CORS/headers audit** — granska Access-Control-headers, Content-Type, Cache-Control
- [ ] **Angular observable completion audit** — forkJoin/combineLatest med ofullstandiga streams
- [ ] **PHP response format audit** — inkonsekvent JSON-format, saknade statuskoder
- [ ] **Angular i18n/hardcoded strings audit** — engelska strängar kvar, saknade oversattningar

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
