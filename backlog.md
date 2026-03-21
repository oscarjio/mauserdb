# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #237):
- [ ] **PHP classes/ file locking audit** — Worker A
- [ ] **PHP classes/ PDO error mode audit** — Worker A
- [ ] **PHP classes/ timezone consistency audit** — Worker A
- [ ] **Angular HTTP retry idempotency audit** — Worker B
- [ ] **Angular form dirty-state warning audit** — Worker B

### Nasta buggjakt-items (session #238+):
- [ ] **PHP classes/ output buffering audit** — saknad ob_clean/ob_end_clean fore JSON-output
- [ ] **PHP classes/ SQL prepared statement reuse audit** — prepare() inuti loopar
- [ ] **Angular trackBy audit** — saknad trackBy i *ngFor pa dynamiska listor
- [ ] **PHP classes/ header injection audit** — header() med osaniterad input
- [ ] **Angular environment config audit** — hardkodade dev-URLer i prod

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
