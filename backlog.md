# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #291):
- [x] **PHP numeric string comparison** — rent (alla anvander redan === och !==)
- [x] **Angular HTTP timeout** — rent (alla HTTP-anrop har timeout() i pipe)
- [x] **PHP mb_string consistency** — 6 buggar fixade (strtoupper+strlen pa UTF-8)
- [x] **Angular ViewChild timing** — rent (inga ViewChild i ngOnInit, alla har null-checks)
- [x] **PHP SQL COALESCE** — rent (alla LEFT JOIN NULL hanteras i PHP)

### Nasta buggjakt-items (session #292+):
- [ ] **PHP array_key_exists vs isset** — isset returnerar false for null-varden, array_key_exists gor inte det
- [ ] **Angular zone.js change detection** — onPush-komponenter som muterar state istallet for nya referenser
- [ ] **PHP PDO lastInsertId** — kontrollera att INSERT lyckades fore lastInsertId-anrop
- [ ] **Angular template pipe chaining** — osakra pipe-kedjor som kan krascha vid null/undefined
- [ ] **PHP SQL GROUP BY strict mode** — kolumner i SELECT som inte ar i GROUP BY eller aggregerade

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
