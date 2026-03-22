# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #255):
- [ ] **PHP str_pad/substr truncation audit** — Worker A
- [ ] **PHP array_column type coercion audit** — Worker A
- [ ] **PHP preg_match return value audit** — Worker A
- [ ] **Angular HTTP race condition audit (switchMap vs mergeMap)** — Worker B
- [ ] **Angular template arithmetic overflow (division by zero)** — Worker B
- [ ] **Angular FormControl/ngModel conflict audit** — Worker B

### Nasta buggjakt-items (session #256+):
- [ ] **PHP sprintf format string mismatch audit** — %s/%d vs argument-typer
- [ ] **PHP array_push vs []= performance audit** — i loopar med stora arrayer
- [ ] **Angular HostListener memory leak audit** — saknade removeEventListener
- [ ] **Angular async validator timing audit** — debounce + race conditions
- [ ] **PHP usort stability audit** — sorteringsordning vid lika varden

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
