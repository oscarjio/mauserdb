# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #250):
- [ ] **PHP mb_substr/mb_strlen consistency audit** — Worker A
- [ ] **PHP array_unique/array_values audit** — Worker A
- [ ] **PHP header() Content-Type consistency audit** — Worker A
- [ ] **PHP static method side-effects audit** — Worker A
- [ ] **Angular pipe chain null-safety audit** — Worker B
- [ ] **Angular FormControl validators audit** — Worker B
- [ ] **Angular route resolver error handling audit** — Worker B

### Nasta buggjakt-items (session #251+):
- [ ] **PHP switch/case fall-through audit** — saknade break-satser
- [ ] **PHP DateTime immutability audit** — modify() pa delade DateTime-objekt
- [ ] **Angular async pipe memory audit** — async pipes utan unsubscribe
- [ ] **PHP PDO lastInsertId race condition audit** — concurrent inserts
- [ ] **Angular template i18n audit** — engelska strangar kvar i templates

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
