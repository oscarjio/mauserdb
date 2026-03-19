# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #179+):
- [ ] **PHP transaction rollback audit** — granska att DB-transaktioner har korrekt rollback vid fel
- [ ] **Angular HTTP timeout audit** — kontrollera att alla HTTP-anrop har rimlig timeout
- [ ] **PHP numeric input validation** — granska att numeriska inputs (id, limit, offset) valideras som int
- [ ] **Angular error message display** — kontrollera att felinformation visas korrekt for anvandaren
- [ ] **PHP logging completeness** — granska att alla error-paths loggar tillrackligt for felsok

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
