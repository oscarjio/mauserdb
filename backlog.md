# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-25)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #320):
- [ ] **PHP date/time handling audit** — timezone-problem, date()-format, strale comparisons (Worker A)
- [ ] **PHP numeric overflow/precision audit** — integer overflow, float-jamforelser (Worker A)
- [ ] **PHP array/null safety audit** — isset-check, null returns, json_decode (Worker A)
- [ ] **Angular routing guard audit** — canActivate, redirect-loopar, auth-kontroller (Worker B)
- [ ] **Angular form validation audit** — required-fallt, custom validators, error messages (Worker B)
- [ ] **Angular HTTP error handling edge cases** — catchError, loading states, race conditions (Worker B)

### Nasta buggjakt-items (session #321+):
- [ ] **PHP session/cookie security audit** — session fixation, cookie flags, expiry
- [ ] **Angular lazy loading performance audit** — chunk sizes, preloading strategies
- [ ] **PHP file I/O audit** — fopen/fwrite utan error check, temp-filer som inte rensas
- [ ] **Angular accessibility audit** — aria-labels, keyboard navigation, screen reader

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
