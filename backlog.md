# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #188):
- [ ] **PHP deprecated function + null/array safety audit** — alla 33 controllers (Worker A)
- [ ] **Angular data flow + race condition audit** — alla 38 components (Worker B)

### Nasta buggjakt-items (session #189+):
- [ ] **PHP file upload validation audit** — granska filuppladdning for saknad validering
- [ ] **Angular HTTP interceptor error handling** — granska centraliserad felhantering
- [ ] **Angular memory profiling** — kora memory profiling pa tunga sidor (exec-dashboard, vd-dashboard)
- [ ] **PHP session/cookie security audit** — granska session-hantering, cookie flags, CSRF
- [ ] **Angular lazy-loading optimization audit** — granska att alla routes lazy-loadar korrekt

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
