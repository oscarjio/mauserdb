# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-25)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #313+):
- [ ] **PHP dead code audit** — oanvanda metoder/routes i controllers som kan tas bort
- [ ] **Angular unused imports/variables** — TypeScript-diagnostik visar oanvanda deklarationer i flera filer
- [ ] **PHP SQL prepared statement parameter count** — antal ? i query vs antal bind-parametrar
- [ ] **Angular ngOnDestroy completeness** — komponenter med subscriptions men utan OnDestroy
- [ ] **PHP error response consistency** — alla endpoints ska returnera JSON med samma felformat

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
