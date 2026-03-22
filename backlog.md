# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #254):
- [ ] **PHP array_merge overwrite audit** — Worker A
- [ ] **PHP date() vs DateTime::format() consistency audit** — Worker A
- [ ] **PHP PDO closeCursor audit** — Worker A
- [ ] **Angular ngOnChanges input mutation audit** — Worker B
- [ ] **Angular ViewChild undefined timing audit** — Worker B
- [ ] **Angular template expression side-effects audit** — Worker B

### Nasta buggjakt-items (session #255+):
- [ ] **PHP str_pad/substr truncation audit** — data som klipps utan varning
- [ ] **PHP array_column type coercion audit** — felaktig nyckeltyp
- [ ] **Angular HTTP race condition audit** — switchMap vs mergeMap i services
- [ ] **Angular template arithmetic overflow audit** — division by zero i templates
- [ ] **PHP preg_match return value audit** — kontrollera === false vs === 0

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
