# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #246):
- [ ] **PHP file_exists/is_readable audit** — Worker A
- [ ] **PHP PDO::beginTransaction rollback audit** — Worker A
- [ ] **PHP intval/floatval range validation audit (A-M)** — Worker A
- [ ] **Angular HTTP error message i18n audit** — Worker B
- [ ] **Angular template method call performance audit** — Worker B

### Nasta buggjakt-items (session #247+):
- [ ] **PHP intval/floatval range validation audit (N-Z)** — resterande PHP-klasser
- [ ] **PHP header() redirect validation audit** — saknade exit() efter header("Location:")
- [ ] **Angular canDeactivate guard audit** — formularsidor utan unsaved-changes-skydd
- [ ] **PHP SQL ORDER BY injection audit** — dynamisk ORDER BY utan vitlista
- [ ] **Angular change detection OnPush audit** — komponenter utan OnPush som borde ha det

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
