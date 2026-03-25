# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-25)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #312+):
- [ ] **PHP array_key_exists vs isset deep audit** — isset returnerar false for null-varden, kan missa data
- [ ] **PHP SQL UNION type mismatch** — kolumntyper i UNION-delar som inte matchar
- [ ] **Angular HTTP polling interval drift** — setInterval+HTTP kan orsaka request-stacking vid lang responstid
- [ ] **Angular template string interpolation XSS** — innerHTML med dynamisk data utan sanitering
- [ ] **PHP PDO transaction nesting** — beginTransaction inuti annan transaktion kastar exception

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
