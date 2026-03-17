# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-17)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [ ] **PHP error handling consistency** — granska try/catch-block, saknade rollback vid exception (Worker A #145)
- [ ] **Angular HTTP error display** — granska att alla API-anrop visar felmeddelanden for anvandaren (Worker B #145)
- [ ] **PHP session security audit** — session fixation, cookie flags, session regeneration (Worker A #145)
- [ ] **Angular memory profiling** — granska komponenter for DOM-lackor, event listener cleanup (Worker B #145)
- [ ] **Angular change detection audit** — OnPush-strategi (stor refactor, lag prioritet)
- [ ] **PHP SQL injection re-audit** — granska prepared statements, dynamiska kolumnnamn, ORDER BY
- [ ] **Angular lazy loading performance** — granska bundle-storlekar, onodiga imports i components

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
