# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-19)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #184):
- [ ] **PHP session timeout/regeneration audit** — granska att sessions regenereras korrekt och har timeout (Worker A)
- [ ] **PHP SQL string concatenation audit** — granska att inga queries byggs med string concat (Worker A)
- [ ] **PHP array key existence audit** — granska att isset/array_key_exists anvands fore array-access (Worker A)
- [ ] **Angular setInterval/setTimeout cleanup audit** — granska att alla timers rensas i OnDestroy (Worker B)
- [ ] **Angular HTTP error message i18n audit** — granska att alla felmeddelanden ar pa svenska (Worker B)

### Nasta buggjakt-items (session #185+):
- [ ] **PHP date/time format consistency audit** — granska att alla datum formateras konsistent (Y-m-d H:i:s)
- [ ] **PHP unused variable audit** — hitta oanvanda variabler och dod kod i controllers
- [ ] **Angular template expression complexity audit** — flytta komplex logik fran templates till components
- [ ] **PHP numeric input validation audit** — granska att numeriska inputs valideras (intval/floatval/is_numeric)
- [ ] **Angular router subscription cleanup audit** — granska att route-params-subscriptions rensas

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
