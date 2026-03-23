# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-23)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #276):
- [ ] **PHP rate limiting / brute force** — Worker A — verifiera login-endpoints har skydd
- [ ] **PHP file upload validering** — Worker A — MIME-type, storlek, path traversal
- [ ] **PHP session/cookie sakerhet** — Worker A — httponly, secure, samesite
- [ ] **Angular lazy loading korrekthet** — Worker B — verifiera lazy-loadade moduler
- [ ] **Angular form validation edge cases** — Worker B — required, min/max, pattern
- [ ] **Angular pipe/directive buggar** — Worker B — custom pipes, cleanup, edge cases

### Nasta buggjakt-items (session #277+):
- [ ] **PHP error logging konsistens** — verifiera error_log format och niva
- [ ] **PHP SQL transaction isolation** — verifiera korrekta isolation levels
- [ ] **Angular HTTP caching** — verifiera att GET-requests cachas korrekt
- [ ] **Angular change detection optimering** — OnPush strategi dar det saknas
- [ ] **PHP CSRF token rotation** — verifiera att tokens roteras korrekt

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
