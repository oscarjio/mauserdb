# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-16)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [ ] **PHP type coercion** — granska loose comparisons (== vs ===) i PHP-controllers (Worker A #128)
- [ ] **Input validation** — controllers som saknar validering av required params (Worker A #128)
- [ ] **SQL edge cases** — division by zero, NULL i AVG, LIMIT utan ORDER BY (Worker A #128)
- [ ] **Template null-safety** — granska .html-filer for saknad ?. navigation som kraschar vid undefined (Worker B #128)
- [ ] **Chart.js memory leaks** — verifiera att alla chart-instanser destroyas korrekt (Worker B #128)
- [ ] **Date/timezone edge cases** — granska datum-hantering, new Date() vs parseLocalDate (Worker B #128)
- [ ] **Error response audit** — leta efter controllers som exponerar PDOException-meddelanden till klienten
- [ ] **HTTP error handling** — granska Angular services for saknad catchError i HTTP-anrop

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
