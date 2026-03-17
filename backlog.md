# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-17)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [ ] **PHP error_log consistency audit** — granska att alla error_log()-anrop foljer samma format
- [ ] **Angular form validation audit** — granska alla reaktiva formuler for saknad validering
- [ ] **PHP SQL query optimization** — granska N+1 queries, saknade INDEX, ineffektiva JOINs
- [ ] **Angular lazy loading audit** — verifiera att alla feature-moduler laddas lazy
- [ ] **PHP CORS/headers audit** — granska alla endpoints for korrekta Content-Type och CORS-headers

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
