# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-22)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #252):
- [ ] **PHP array_splice/array_pop return value audit** — Worker A
- [ ] **PHP preg_replace limit/error audit** — Worker A
- [ ] **PHP fopen/fclose resource leak audit** — Worker A
- [ ] **Angular HTTP retry idempotency audit** — Worker B
- [ ] **Angular change detection OnPush audit** — Worker B
- [ ] **Angular template arithmetic/logic complexity audit** — Worker B

### Nasta buggjakt-items (session #253+):
- [ ] **PHP header() location redirect audit** — oppna redirect-sarbarheter
- [ ] **PHP json_encode UTF-8 audit** — saknade JSON_UNESCAPED_UNICODE flaggor
- [ ] **Angular HttpParams encoding audit** — specialtecken i query-parametrar
- [ ] **PHP PDO transaction nesting audit** — beginTransaction inuti aktiv transaktion
- [ ] **Angular template pipe chain audit** — pipes som ger NaN/undefined vid null-input

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
