# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #292):
- [ ] **PHP array_key_exists vs isset** — isset returnerar false for null-varden (Worker A: controllers A-M)
- [ ] **Angular OnPush change detection** — muterar state istallet for nya referenser (Worker B)
- [ ] **PHP PDO lastInsertId** — kontrollera INSERT lyckades fore lastInsertId (Worker A: controllers A-M)
- [ ] **Angular template pipe chaining** — null/undefined i pipe-kedjor (Worker B)
- [ ] **PHP SQL GROUP BY strict mode** — kolumner i SELECT ej aggregerade (Worker A: controllers A-M)

### Nasta buggjakt-items (session #293+):
- [ ] **PHP array_key_exists vs isset** — controllers N-Z (resterande)
- [ ] **PHP PDO lastInsertId** — controllers N-Z (resterande)
- [ ] **PHP SQL GROUP BY strict mode** — controllers N-Z (resterande)
- [ ] **Angular HTTP retry pa POST/PUT/DELETE** — retry() ska bara finnas pa GET
- [ ] **PHP empty() gotchas** — empty("0") ar true, empty([]) ar true — farligt for validering

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
