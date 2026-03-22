# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #243+):
- [ ] **PHP str_replace/preg_replace edge case audit** — felaktiga regex, saknad preg_quote, tomma patterns
- [ ] **PHP array_merge i loopar performance audit** — array_merge() i foreach som kan vara +=/$result[]
- [ ] **Angular trackBy audit** — *ngFor utan trackBy pa listor med HTTP-data
- [ ] **PHP PDO fetch mode consistency audit** — blandade FETCH_ASSOC/FETCH_BOTH/FETCH_NUM
- [ ] **Angular template safe navigation audit** — saknade ?. i kedjeanrop pa nullable objekt

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
