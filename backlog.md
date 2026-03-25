# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-25)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #322):
- [ ] **PHP SQL query builder audit** — prepared statements, parameter binding, query concatenation (Worker A)
- [ ] **PHP CORS/header security audit** — Access-Control headers, content-type validation (Worker A)
- [ ] **PHP input sanitization audit** — $_GET/$_POST validering, filter_input, typ-casting (Worker A)
- [ ] **Angular state management audit** — BehaviorSubject race conditions, stale state (Worker B)
- [ ] **Angular environment config audit** — hardcoded URLs, missing env variables (Worker B)
- [ ] **Angular HTTP interceptor audit** — error interceptors, token refresh, timeout (Worker B)

### Nasta buggjakt-items (session #323+):
- [ ] **PHP logging/audit trail audit** — saknad loggning av viktiga handelser, loggniva-konsistens
- [ ] **Angular memory profiling audit** — DOM-lackor, detached elements, stora arrayer i minnet
- [ ] **PHP race condition audit** — concurrent requests, DB-lasning, optimistic locking
- [ ] **Angular router guard audit** — auth guards, canActivate/canDeactivate, redirect-logik

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
