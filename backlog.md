# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-24)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Nasta buggjakt-items (session #294+):
- [ ] **PHP array_key_exists vs isset** — controllers N-Z (resterande fran #292)
- [ ] **PHP PDO lastInsertId** — controllers N-Z (resterande fran #292)
- [ ] **PHP SQL GROUP BY strict mode** — controllers N-Z (resterande fran #292)
- [ ] **PHP intval/floatval pa $_GET/$_POST** — user input som inte castas korrekt
- [ ] **PHP SQL ORDER BY injection** — ORDER BY/LIMIT fran user input utan whitelist
- [ ] **Angular FormControl validators** — asynkrona validators, required pa dolda falt
- [ ] **Angular router param type safety** — params.get() utan validering/parseInt

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
