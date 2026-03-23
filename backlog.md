# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #268):
- [ ] **PHP timezone consistency** — Worker A — date_default_timezone_set(), DateTime vs strtotime
- [ ] **PHP array key validation** — Worker A — saknade isset/array_key_exists vid extern data
- [ ] **PHP PDO error mode consistency** — Worker A — ERRMODE_EXCEPTION vs SILENT
- [ ] **Angular HTTP interceptor error handling** — Worker B — globala 401/403/500 handlers
- [ ] **Angular memory profiling** — Worker B — stora dataset, pagination, subscription leaks

### Nasta buggjakt-items (session #269+):
- [ ] **PHP header injection** — saknad validering av user input i header()-anrop
- [ ] **Angular form dirty state** — canDeactivate guards for osparade andringar
- [ ] **PHP numeric validation** — is_numeric vs ctype_digit vs intval vid ID-parametrar
- [ ] **Angular accessibility** — saknade aria-labels, keyboard navigation, screen reader
- [ ] **PHP mail/SMTP safety** — header injection i mail()-anrop, saknad sanitering

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
