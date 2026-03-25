# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-25)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #314+):
- [ ] **PHP sendError() vs echo inkonsistens** — ~33 controllers blandar sendError() och direkt echo for error responses
- [ ] **Angular NG8107/NG8102 template warnings** — 125 kosmetiska varningar (onodvandiga ?. och ?? pa non-nullable typer i 23 filer)
- [ ] **PHP SQL query performance** — hitta N+1 queries (loopar med SQL inuti) i controllers
- [ ] **Angular strict template type checking** — aktivera strictTemplates och fixa eventuella fel
- [ ] **PHP input sanitization audit** — granska att alla $_GET/$_POST valideras korrekt (range, format, whitelist)

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
