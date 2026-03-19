# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #180):
- [ ] **PHP logging completeness** — granska att alla error-paths loggar tillrackligt (Worker A)
- [ ] **PHP response code audit** — granska att HTTP-statuskoder matchar faktiskt resultat (Worker A)
- [ ] **Angular memory leak audit** — granska att alla subscriptions avregistreras i ngOnDestroy (Worker B)
- [ ] **Angular loading state audit** — kontrollera att alla async-operationer visar loading-indikator (Worker B)

### Nasta buggjakt-items (session #181+):
- [ ] **PHP SQL column name audit** — granska att alla SQL-fragor refererar korrekta kolumnnamn
- [ ] **PHP input sanitization audit** — granska att alla $_GET/$_POST valideras/saniteras
- [ ] **Angular error boundary audit** — granska att alla HTTP-fel visar anvandardvandligt meddelande
- [ ] **PHP date/timezone edge cases** — granska DST-hantering i alla datum-berakningar
- [ ] **Angular template null-safety** — granska att alla async-data hanterar null/undefined i templates

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
