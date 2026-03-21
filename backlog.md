# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Avklarade (session #224):
- [x] **PHP classes/ regex injection + preg_match audit** — rent, alla preg_ anvander hardkodade monster (Worker A)
- [x] **PHP classes/ session concurrency + race condition audit** — 3 TOCTOU-buggar fixade i 3 filer (Worker A)
- [x] **Angular pipe error handling audit** — rent, inga custom pipes finns (Worker B)
- [x] **Angular router resolve/guard data consistency audit** — 1 bugg fixad i adminGuard (Worker B)

### Nasta buggjakt-items (session #225+):
- [ ] **PHP classes/ HTTP header injection audit** — header() med ovaliderade varden
- [ ] **PHP classes/ JSON decode error handling audit** — json_decode utan json_last_error-kontroll
- [ ] **Angular service error propagation audit** — services som svaljer fel istallet for att propagera
- [ ] **PHP classes/ file path validation audit** — saknad basename/realpath-validering pa filsokvagar
- [ ] **Angular form dirty-state audit** — canDeactivate-guards som saknas pa formularsidor

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
