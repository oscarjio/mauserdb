# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Avklarade (session #225):
- [x] **PHP classes/ HTTP header injection audit** — rent, alla header() anvander statiska varden (Worker A)
- [x] **PHP classes/ JSON decode error handling audit** — 2 buggar fixade i NewsController (Worker A)
- [x] **Angular service error propagation audit** — 18 buggar fixade i 10 services (Worker B)
- [x] **Angular form dirty-state audit** — rent, alla formuler i modals (Worker B)

### Nasta buggjakt-items (session #226+):
- [ ] **PHP classes/ file path validation audit** — saknad basename/realpath-validering pa filsokvagar
- [ ] **PHP classes/ SQL COALESCE/IFNULL audit** — nullable kolumner utan fallback i queries
- [ ] **Angular HTTP interceptor error handling audit** — interceptor som svaljer/transformerar fel felaktigt
- [ ] **PHP classes/ array access without isset audit** — direkt array-access utan isset/array_key_exists
- [ ] **Angular template async pipe audit** — saknad async pipe eller dubbla subscriptions i templates

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
