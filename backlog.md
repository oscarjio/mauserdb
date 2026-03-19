# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #187):
- [ ] **PHP error response consistency audit** — granska felhantering i 16 controllers (Worker A)
- [ ] **PHP controller return type consistency** — granska att alla endpoints returnerar JSON konsekvent (Worker A)
- [ ] **Angular service HTTP error handling audit** — granska catchError/timeout i services (Worker B)
- [ ] **Angular component null safety audit** — granska template-buggar i 13+ components (Worker B)

### Nasta buggjakt-items (session #188+):
- [ ] **PHP file upload validation audit** — granska filuppladdning for saknad validering
- [ ] **Angular lazy-loading optimization audit** — granska att alla routes lazy-loadar korrekt
- [ ] **Angular HTTP interceptor error handling** — granska centraliserad felhantering
- [ ] **PHP deprecated function usage audit** — granska anvandning av deprecated PHP-funktioner
- [ ] **Angular memory profiling** — kora memory profiling pa tunga sidor (exec-dashboard, vd-dashboard)

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
