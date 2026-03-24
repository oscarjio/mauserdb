# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #304):
- [ ] **PHP SQL implicit type conversion** — WHERE string_col = int kan ge oforutsagbara resultat (Worker A)
- [ ] **PHP array_splice off-by-one** — kontrollera offset/length i alla array_splice-anrop (Worker A)
- [ ] **PHP SQL HAVING without GROUP BY** — ogiltig SQL som MySQL tillater i lax-lage (Worker A)
- [ ] **Angular ViewChild static timing** — static:true vs static:false vid dynamic content (Worker B)
- [ ] **Angular httpClient memory on navigation** — ofullstandiga HTTP-anrop vid route-byte (Worker B)

### Nasta buggjakt-items (session #305+):
- [ ] **PHP SQL IFNULL/COALESCE consistency** — blandad anvandning kan ge null-relaterade buggar
- [ ] **PHP date() vs DateTime** — inkonsekvent datumhantering, potentiella timezone-problem
- [ ] **Angular ChangeDetectorRef markForCheck** — saknade manuella CD-triggers vid async data
- [ ] **PHP SQL LEFT JOIN vs INNER JOIN** — felaktiga JOINs som filtrerar bort rader med NULL
- [ ] **Angular template i18n hardcoded strings** — icke-svenska strängar i templates

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
