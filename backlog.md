# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #189):
- [x] **PHP SQL query + try-catch audit** — 4 buggar fixade (Worker A)
- [x] **Angular template null-safety + subscription audit** — 1 bugg fixad (Worker B)

### Nasta buggjakt-items (session #190+):
- [ ] **PHP file upload validation audit** — granska filuppladdning for saknad validering
- [ ] **Angular HTTP interceptor error handling** — granska centraliserad felhantering
- [ ] **PHP session/cookie security audit** — granska session-hantering, cookie flags, CSRF
- [ ] **Angular memory profiling** — kora memory profiling pa tunga sidor
- [ ] **Angular lazy-loading optimization audit** — granska att alla routes lazy-loadar korrekt

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
