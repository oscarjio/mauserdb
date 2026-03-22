# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #246+):
- [ ] **PHP file_exists/is_readable audit** — saknade kontroller innan file-operationer
- [ ] **PHP PDO::beginTransaction rollback audit** — saknade rollback i catch-block
- [ ] **Angular HTTP error message i18n audit** — engelska felmeddelanden i catch-block
- [ ] **PHP intval/floatval range validation audit** — saknade min/max-granser pa numeriska inputs
- [ ] **Angular template method call performance audit** — tunga metoder i templates utan caching

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
