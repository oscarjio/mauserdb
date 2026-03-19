# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #186):
- [ ] **PHP numeric input validation audit** — granska att numeriska inputs valideras (intval/floatval) (Worker A)
- [ ] **PHP SQL LIMIT/OFFSET injection audit** — granska att LIMIT/OFFSET-varden ar validerade integers (Worker A)
- [ ] **Angular change detection audit** — granska att OnPush anvands dar det ar lampligt (Worker B)
- [ ] **Angular error response consistency audit** — granska felhantering i services (Worker B)

### Nasta buggjakt-items (session #187+):
- [ ] **PHP error response consistency audit** — granska att alla error responses har konsekvent format
- [ ] **Angular lazy-loading optimization audit** — granska att alla routes lazy-loadar korrekt
- [ ] **PHP file upload validation audit** — granska filuppladdning for saknad validering
- [ ] **Angular HTTP interceptor error handling** — granska centraliserad felhantering
- [ ] **PHP controller return type consistency** — granska att alla endpoints returnerar JSON konsekvent

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
