# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #214):
- [x] **PHP classes/ date/time edge case re-audit** — 3 buggar fixade: felaktiga "last monday" (Worker A)
- [x] **PHP classes/ SQL JOIN audit** — RENT, inga JOIN-buggar (Worker A)
- [x] **Angular service URL consistency audit** — 8 buggar fixade: saknade withCredentials (Worker B)
- [x] **Angular form validation audit** — 13 buggar fixade: saknade maxlength (Worker B)

### Nasta buggjakt-items (session #215+):
- [ ] **PHP classes/ integer overflow/bounds audit** — saknade range-checks pa intval/floatval
- [ ] **Angular pipe/filter edge case audit** — felaktiga date/number pipes, null-inputs
- [ ] **PHP classes/ array key existence audit** — saknade isset/array_key_exists fore access
- [ ] **Angular routing guard audit** — saknade canDeactivate, felaktiga redirects
- [ ] **PHP classes/ SQL ORDER BY injection audit** — dynamiska ORDER BY utan whitelist

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
