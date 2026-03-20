# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #198):
- [x] **PHP classes/ authorization + file upload audit** — 3 buggar (Worker A)
- [x] **Angular form validation + subscription audit** — 5 buggar (Worker B)

### Nasta buggjakt-items (session #199+):
- [ ] **PHP classes/ SQL query performance audit** — saknade index, N+1-queries, onodiga JOINs
- [ ] **Angular HTTP error consistency audit** — inkonsistent felhantering i services
- [ ] **PHP classes/ transaction audit** — saknade transaktioner vid multi-query-operationer
- [ ] **Angular routing guard audit** — saknade guards, felaktig redirect-logik
- [ ] **PHP classes/ logging + audit trail audit** — saknad loggning av kritiska operationer

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
