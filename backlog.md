# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #166):
- [x] **PHP file upload validation audit** — 0 buggar, inga file uploads i projektet (Worker A)
- [x] **PHP CORS/security headers audit** — 7 buggar fixade: CSP, HSTS, XSS-protection, CSV filename injection (Worker A)
- [x] **Angular memory leak deep audit** — 0 buggar, alla chart/subscription cleanups korrekta (Worker B)
- [x] **Angular error boundary audit** — 2 buggar fixade: saknad catch i pdf-export (Worker B)

### Nasta buggjakt-items (session #167+):
- [ ] **PHP SQL query optimization audit** — N+1 queries, saknade index, onodiga JOINs
- [ ] **PHP session/auth edge cases audit** — token expiry, concurrent login, session fixation
- [ ] **Angular template null-safety audit** — saknade ?. operators, undefined i *ngFor
- [ ] **PHP response consistency audit** — saknade Content-Type headers, inkonsekvent JSON-format
- [ ] **Angular route guard edge cases** — redirect-loopar, auth-race vid page refresh

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
