# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #305):
- [ ] **PHP SQL IFNULL/COALESCE consistency** — blandad anvandning kan ge null-relaterade buggar (Worker A)
- [ ] **PHP date() vs DateTime** — inkonsekvent datumhantering, potentiella timezone-problem (Worker A)
- [ ] **PHP SQL LEFT JOIN vs INNER JOIN** — felaktiga JOINs som filtrerar bort rader med NULL (Worker A)
- [ ] **Angular ChangeDetectorRef markForCheck** — saknade manuella CD-triggers vid async data (Worker B)
- [ ] **Angular template i18n hardcoded strings** — icke-svenska strangar i templates (Worker B)

### Nasta buggjakt-items (session #306+):
- [ ] **PHP SQL subquery correlation** — korrelerade subqueries som refererar fel tabell/alias
- [ ] **PHP $_GET/$_POST default values** — saknade default-varden vid tomma parametrar
- [ ] **Angular router param unsubscribe** — route.params/queryParams subscriptions utan cleanup
- [ ] **PHP SQL COUNT vs SUM confusion** — felaktig aggregering i rapporter
- [ ] **Angular HTTP error message display** — felmeddelanden fran backend visas ej for anvandaren

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
