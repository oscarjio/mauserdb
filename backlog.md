# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #173):
- [x] **PHP rate limiting audit** — redan OK, inga buggar (Worker A)
- [x] **PHP error response standardization** — 5 buggar fixade (Worker A)
- [x] **PHP session security audit** — 2 buggar fixade (Worker A)
- [x] **Angular lazy-loading completeness** — redan OK, inga buggar (Worker B)
- [x] **Angular accessibility audit** — 813 buggar fixade (Worker B)

### Nasta buggjakt-items (session #174+):
- [ ] **PHP input validation completeness** — saknad validering pa POST/PUT-data i controllers
- [ ] **Angular HTTP error retry logic** — saknad retry/backoff pa kritiska API-anrop
- [ ] **PHP SQL injection review** — prepared statements, parameterisering i alla queries
- [ ] **Angular route guard completeness** — sidor utan korrekt auth-guard
- [ ] **PHP logging audit** — saknade loggningar vid kritiska operationer

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
