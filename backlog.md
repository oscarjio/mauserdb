# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #277):
- [ ] **PHP error logging konsistens** — Worker A — verifiera error_log format, saknade catch-logs, log injection
- [ ] **PHP SQL transaction isolation** — Worker A — beginTransaction/commit/rollback, nested, saknade transaktioner
- [ ] **PHP CSRF token rotation** — Worker A — generering, validering, rotation, dubbelklick-skydd
- [ ] **Angular HTTP caching** — Worker B — GET-cache, invalidering, duplicerade anrop
- [ ] **Angular change detection optimering** — Worker B — OnPush, trackBy, template-funktioner
- [ ] **Angular service singleton audit** — Worker B — providedIn, state, circular deps

### Nasta buggjakt-items (session #278+):
- [ ] **PHP date/timezone konsistens** — verifiera att alla date()-anrop anvander samma timezone
- [ ] **PHP array bounds/key access** — verifiera isset/array_key_exists fore access
- [ ] **Angular memory leak regressionstest** — kora profiling pa tunga komponenter
- [ ] **Angular router lazy chunk felhantering** — verifiera att chunk-ladningsfel hanteras
- [ ] **PHP SQL injection i dynamiska ORDER BY** — verifiera vitlistning av kolumnnamn

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
