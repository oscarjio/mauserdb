# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #185):
- [ ] **PHP date/time format consistency audit** — granska att alla datum formateras konsistent Y-m-d H:i:s (Worker A)
- [ ] **PHP unused variable audit** — hitta oanvanda variabler och dod kod i controllers (Worker A)
- [ ] **Angular template expression complexity audit** — flytta komplex logik fran templates till components (Worker B)
- [ ] **Angular router subscription cleanup audit** — granska att route-params-subscriptions rensas (Worker B)

### Nasta buggjakt-items (session #186+):
- [ ] **PHP numeric input validation audit** — granska att numeriska inputs valideras (intval/floatval/is_numeric)
- [ ] **PHP error response consistency audit** — granska att alla error responses har konsekvent format
- [ ] **Angular lazy-loading optimization audit** — granska att alla routes lazy-loadar korrekt
- [ ] **PHP SQL LIMIT/OFFSET injection audit** — granska att LIMIT/OFFSET-varden ar validerade integers
- [ ] **Angular change detection audit** — granska att OnPush anvands dar det ar lampligt

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
