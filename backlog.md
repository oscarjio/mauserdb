# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-16)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **SQL edge cases** — 3x LIMIT utan ORDER BY, 3x NULL-safe aggregering (Worker A #130)
- [x] **Template null-safety deep audit** — 21x .toFixed() pa null/undefined (Worker B #130)
- [x] **PHP return type consistency** — 18x saknad success-nyckel i JSON-svar (Worker A #130)
- [x] **PHP error_log audit** — 3x catch utan error_log (Worker A #130)
- [x] **Angular lazy loading** — verifierat OK, alla routes lazy-loadar (Worker B #130)
- [x] **Angular service URL audit** — verifierat OK, alla URLs relativa (Worker B #130)
- [ ] **PHP boundary validation** — granska att alla query-params (limit, offset, dates) valideras
- [ ] **Angular form validation** — granska att alla formuler validerar input korrekt
- [ ] **PHP SQL injection re-audit** — dubbelkolla prepared statements i nyare controllers
- [ ] **Angular error state UI** — granska att felmeddelanden visas korrekt for anvandaren
- [ ] **PHP date range validation** — verifiera att from/to-datum i queries ar logiskt korrekta

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
