# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #251):
- [ ] **PHP switch/case fall-through audit** — Worker A
- [ ] **PHP DateTime immutability audit** — Worker A
- [ ] **PHP PDO lastInsertId race condition audit** — Worker A
- [ ] **Angular async pipe memory audit** — Worker B
- [ ] **Angular template i18n audit** — Worker B

### Nasta buggjakt-items (session #252+):
- [ ] **PHP array_splice/array_pop return value audit** — oanvanda returvarden
- [ ] **PHP preg_replace limit audit** — saknade limit-parametrar
- [ ] **Angular HTTP retry idempotency audit** — POST/PUT/DELETE som retries
- [ ] **PHP fopen/fclose resource leak audit** — oppnade filer utan stangning
- [ ] **Angular change detection OnPush audit** — komponenter som borde vara OnPush

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
