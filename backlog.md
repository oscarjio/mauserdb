# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #265):
- [x] **PHP arithmetic overflow/division audit** — Worker A — rent
- [x] **PHP SQL injection i dynamiska ORDER BY/GROUP BY** — Worker A — rent
- [x] **PHP include/require path audit** — Worker A — rent
- [x] **Angular router guard consistency audit** — Worker B — rent
- [x] **Angular service singleton audit** — Worker B — rent
- [x] **Angular template type safety audit** — Worker B — rent

### Nasta buggjakt-items (session #266+):
- [ ] **PHP error handling consistency** — try/catch vs die() vs exit, saknade catch-block
- [ ] **Angular HTTP response type audit** — saknade typningar pa HTTP-responses, any-typer
- [ ] **PHP SQL transaction consistency** — saknade BEGIN/COMMIT/ROLLBACK vid multi-query
- [ ] **Angular form reset audit** — formuler som inte rensas efter submit/navigation
- [ ] **PHP password/token handling** — timing-safe comparison, token expiry

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
