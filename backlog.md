# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #278):
- [ ] **PHP date/timezone konsistens** — Worker A — verifiera date()/strtotime()/DateTime, timezone-installningar
- [ ] **PHP array bounds/key access** — Worker A — isset/array_key_exists fore access, tomma resultatset
- [ ] **PHP SQL injection i dynamiska ORDER BY** — Worker A — vitlistning av kolumnnamn, (int)-cast LIMIT
- [ ] **Angular memory leak regressionstest** — Worker B — subscriptions, timers, Chart.js destroy
- [ ] **Angular router lazy chunk felhantering** — Worker B — chunk-laddningsfel, GlobalErrorHandler
- [ ] **Angular HTTP felhantering konsistens** — Worker B — catchError, timeout, error callbacks

### Nasta buggjakt-items (session #279+):
- [ ] **PHP response header konsistens** — Content-Type, Cache-Control, X-Content-Type-Options
- [ ] **PHP numeric precision** — float-jamforelser, round() konsistens, division-resultat
- [ ] **Angular form state management** — dirty/pristine, reset efter submit, validering
- [ ] **Angular environment-specifik konfiguration** — API URL, feature flags, build-env
- [ ] **PHP SQL JOIN konsistens** — verifiera att alla JOINs matchar ratt kolumner

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
