# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #190):
- [ ] **PHP file upload validation + session/cookie security audit** (Worker A)
- [ ] **Angular HTTP interceptor + error handling audit** (Worker B)

### Nasta buggjakt-items (session #191+):
- [ ] **Angular memory profiling** — kora memory profiling pa tunga sidor
- [ ] **Angular lazy-loading optimization audit** — granska att alla routes lazy-loadar korrekt
- [ ] **PHP SQL performance audit** — granska fler controllers for SELECT *, N+1 queries, saknade index
- [ ] **PHP input validation audit** — granska $_GET/$_POST for saknad validering/sanitering
- [ ] **Angular chart cleanup audit** — granska att alla Chart.js-instanser destroyas korrekt

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
