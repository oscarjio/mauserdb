# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #274):
- [ ] **PHP router/api.php audit** — Worker A — verifiera alla routes matchar controllers, hitta doda routes
- [ ] **PHP controllers A-M djupgranskning** — Worker A — boundary values, encoding, timezone, SQL
- [ ] **Angular template null safety** — Worker B — osaker property access utan ?. eller *ngIf
- [ ] **Angular @Input/@Output audit** — Worker B — korrekthet, default-varden, typning

### Nasta buggjakt-items (session #275+):
- [ ] **Angular environment config** — verifiera att environment.ts och environment.prod.ts har korrekta API-URL:er
- [ ] **PHP controllers N-Z djupgranskning** — boundary values, encoding, timezone (uppfoljning fran #273)
- [ ] **Angular HTTP interceptor edge cases** — retry-logik, timeout-hantering, offline-beteende
- [ ] **PHP SQL UNION/subquery audit** — verifiera korrekthet i komplexa fragor
- [ ] **Angular routing guards** — canActivate/canDeactivate korrekthet, auth-kontroll

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
