# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #279):
- [ ] **PHP response header konsistens** — Worker A — Content-Type, Cache-Control, X-Content-Type-Options
- [ ] **PHP numeric precision** — Worker A — float-jamforelser, round() konsistens, division-by-zero
- [ ] **PHP SQL JOIN konsistens** — Worker A — verifiera att alla JOINs matchar ratt kolumner
- [ ] **Angular form state management** — Worker B — dirty/pristine, reset efter submit, dubbel-submit
- [ ] **Angular environment-specifik konfiguration** — Worker B — hardkodade URLer, feature flags
- [ ] **Angular component communication** — Worker B — @Input/@Output, ViewChild timing, race conditions

### Nasta buggjakt-items (session #280+):
- [ ] **PHP error_log format konsistens** — verifiera att loggmeddelanden ar konsistenta och informativa
- [ ] **PHP CSRF token validering** — granska att alla mutating endpoints validerar CSRF-token
- [ ] **Angular router parameter parsing** — verifiera att route params hanteras korrekt i alla sidor
- [ ] **Angular async rendering** — *ngIf med async data, loading states, race conditions
- [ ] **PHP SQL GROUP BY korrekthet** — verifiera att SELECT-kolumner matchar GROUP BY

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
