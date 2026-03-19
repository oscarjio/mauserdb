# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #180+):
- [ ] **PHP logging completeness** — granska att alla error-paths loggar tillrackligt for felsok
- [ ] **Angular memory leak audit** — granska att alla subscriptions avregistreras i ngOnDestroy
- [ ] **PHP response code audit** — granska att HTTP-statuskoder matchar faktiskt resultat (200 vs 404 vs 500)
- [ ] **Angular loading state audit** — kontrollera att alla async-operationer visar loading-indikator
- [ ] **PHP SQL column name audit** — granska att alla SQL-fragor refererar korrekta kolumnnamn

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
