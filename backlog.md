# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-17)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] PHP session/cookie security audit [Worker A #137]
- [x] Angular template strict null-check audit [Worker B #137]
- [x] PHP SQL column name verification [Worker A #137] — inga kolumnnamnsfel
- [x] PHP date range validation audit [Worker A #137]
- [x] Angular form input sanitization audit [Worker B #137]
- [x] Angular HTTP retry/timeout audit [Worker B #137]
- [ ] **PHP boundary/pagination validation** — granska LIMIT/OFFSET-parametrar, max-granser, negativa varden
- [ ] **PHP error boundary audit** — granska try/catch-block i controllers, saknade exception-hanterare
- [ ] **Angular router parameter validation** — granska att route params valideras (typ, format, existens)
- [ ] **PHP race condition audit** — granska parallella requests, UPDATE utan locking, dubbletter
- [ ] **Angular memory profiling — komponent-storlek** — granska stora komponenter for onodiga imports/data

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
