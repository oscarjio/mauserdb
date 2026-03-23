# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #266):
- [x] **PHP error handling consistency** — Worker A — 15 buggar fixade
- [x] **PHP SQL transaction consistency** — Worker A — rent
- [x] **PHP password/token handling** — Worker A — rent
- [x] **Angular HTTP response type audit** — Worker B — rent
- [x] **Angular form reset audit** — Worker B — rent
- [x] **Angular Observable error handling audit** — Worker B — 7 buggar fixade

### Nasta buggjakt-items (session #267+):
- [ ] **PHP file I/O safety** — saknade flock(), felhantering vid fopen/fwrite/fclose
- [ ] **Angular route parameter validation** — saknad parseInt/validering av route params
- [ ] **PHP session fixation/regeneration** — session_regenerate_id() vid login
- [ ] **Angular environment config audit** — dev vs prod skillnader, saknade variabler
- [ ] **PHP CORS/preflight consistency** — OPTIONS-hantering, Access-Control headers
- [ ] **Angular lazy loading chunk error handling** — ChunkLoadError retry/redirect

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
