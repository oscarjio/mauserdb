# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-17)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **PHP SQL query optimization** — granska N+1 queries, saknade INDEX, ineffektiva JOINs
- [x] **PHP CORS/headers audit** — granska alla endpoints for korrekta Content-Type och CORS-headers
- [x] **PHP error_log consistency audit** — granska att alla error_log()-anrop foljer samma format
- [x] **Angular form validation audit** — granska alla reaktiva formuler for saknad validering
- [x] **Angular lazy loading audit** — verifiera att alla feature-moduler laddas lazy
- [ ] **Angular template null-safety audit** — saknade ?. och *ngIf guards i templates
- [ ] **PHP race condition audit** — granska concurrent requests, locking, DB transactions
- [ ] **Angular change detection audit** — OnPush-strategi, unnecessary re-renders
- [ ] **PHP input length/boundary audit** — granska max-langder, overflow, edge cases
- [ ] **Angular router guard audit** — saknade guards pa admin/auth-routes

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
