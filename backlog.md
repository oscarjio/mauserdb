# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-25)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #319):
- [ ] **PHP raw SQL string concatenation audit N-Z** — fortsattning fran A-M (Worker A)
- [ ] **PHP error logging audit** — tysta catch-block, saknad loggning (Worker A)
- [ ] **PHP response consistency audit** — JSON-format, HTTP-koder, headers (Worker A)
- [ ] **Angular pipe/directive audit** — pure pipes, null-hantering, imports (Worker B)
- [ ] **Angular memory leak audit** — DOM-refs, observers, event listeners (Worker B)
- [ ] **Angular template type safety audit** — optional chaining, trackBy, innerHTML (Worker B)

### Nasta buggjakt-items (session #320+):
- [ ] **PHP date/time handling audit** — timezone-problem, date()-format, strale comparisons
- [ ] **Angular routing guard audit** — canActivate, redirect-loopar, auth-kontroller
- [ ] **PHP numeric overflow/precision audit** — integer overflow, float-jamforelser
- [ ] **Angular form validation audit** — required-fallt, custom validators, error messages

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
