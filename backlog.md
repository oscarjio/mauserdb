# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-16)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [ ] **Endpoint-testning med curl** — testa ALLA API-endpoints, logga 500-fel, fixa root cause (Worker A #127)
- [ ] **Template null-safety** — granska .html-filer for saknad ?. navigation som kraschar vid undefined (Worker B #127)
- [ ] **Chart.js memory leaks** — verifiera att alla chart-instanser destroyas korrekt (Worker B #127)
- [ ] **SQL edge cases** — division by zero, NULL i AVG, LIMIT utan ORDER BY (Worker A #127)
- [ ] **Input validation** — controllers som saknar validering av required params (Worker A #127)
- [ ] **Date/timezone edge cases** — granska datum-hantering vid midnatt/skiftbyte
- [ ] **PHP type coercion** — granska loose comparisons (== vs ===) i PHP-controllers

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
