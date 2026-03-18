# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **PHP SQL query edge case audit** — NULL-hantering, LIMIT/OFFSET, JOINs — 0 buggar (Worker A #160)
- [ ] **Angular template null-safety audit** — ?. och *ngIf-guards i templates (Worker B #160)
- [x] **PHP date/time parsing audit** — strtotime/DateTime ogiltiga input — 0 buggar (Worker A #160)
- [ ] **Angular HTTP interceptor audit** — retry-logik, token refresh, error mapping (Worker B #160)
- [x] **PHP array access audit** — isset/array_key_exists, foreach null — 0 buggar (Worker A #160)
- [ ] **Angular router guard audit** — auth-guards pa skyddade routes (Worker B #160)

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
