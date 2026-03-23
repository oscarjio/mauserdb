# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #280):
- [ ] **PHP error_log format konsistens** — Worker A — controllers A-M, kansklig data, catch-blocks
- [ ] **PHP CSRF token validering** — Worker A — mutating endpoints, token-bypass
- [ ] **PHP SQL GROUP BY korrekthet** — Worker A — SELECT vs GROUP BY kolumner, HAVING
- [ ] **Angular router parameter parsing** — Worker B — ActivatedRoute, typ-konvertering, subscriptions
- [ ] **Angular async rendering** — Worker B — loading states, *ngIf guards, race conditions
- [ ] **Angular template type safety** — Worker B — null-checks, pipe-anvandning, event handlers

### Nasta buggjakt-items (session #281+):
- [ ] **PHP error_log format konsistens (N-Z)** — controllers N-Z, samma granskning som A-M
- [ ] **PHP SQL subquery korrekthet** — verifiera att subqueries returnerar forvantade resultat
- [ ] **Angular HTTP request cancellation** — switchMap vs mergeMap, avbryt vid navigation
- [ ] **Angular date/time rendering** — timezone-problem, locale-format, moment vs Date
- [ ] **PHP array_key_exists vs isset** — edge cases med null-varden i arrayer

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
