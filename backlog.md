# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #253):
- [ ] **PHP header() location redirect audit** — Worker A
- [ ] **PHP json_encode UTF-8 audit** — Worker A
- [ ] **PHP PDO transaction nesting audit** — Worker A
- [ ] **Angular HttpParams encoding audit** — Worker B
- [ ] **Angular template pipe chain audit** — Worker B
- [ ] **Angular template string interpolation null-safety audit** — Worker B

### Nasta buggjakt-items (session #254+):
- [ ] **PHP array_merge overwrite audit** — numeriska vs associativa nycklar
- [ ] **PHP date() vs DateTime::format() consistency audit** — blandad anvandning
- [ ] **Angular ngOnChanges input mutation audit** — muterade inputs i child-komponenter
- [ ] **PHP PDO closeCursor audit** — saknade closeCursor efter SELECT
- [ ] **Angular ViewChild undefined timing audit** — ViewChild fore AfterViewInit

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
