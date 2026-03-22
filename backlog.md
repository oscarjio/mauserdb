# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #256):
- [x] **PHP sprintf format string mismatch audit** — Worker A — rent
- [x] **PHP usort stability audit** — Worker A — rent
- [x] **PHP array_push vs []= performance audit i loopar** — Worker A — rent
- [x] **Angular HostListener memory leak audit** — Worker B — rent
- [x] **Angular async validator timing audit** — Worker B — rent
- [x] **Angular Renderer2/ElementRef.nativeElement audit** — Worker B — rent

### Nasta buggjakt-items (session #257+):
- [ ] **PHP foreach by-reference audit** — &$val utan unset efter loop
- [ ] **PHP static method state leakage audit** — statiska variabler mellan requests
- [ ] **Angular ngAfterViewChecked performance audit** — tunga operationer i change detection
- [ ] **Angular HTTP interceptor error propagation audit** — swallowed errors
- [ ] **PHP PDO::ATTR_EMULATE_PREPARES audit** — saknad eller felaktig konfiguration

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
