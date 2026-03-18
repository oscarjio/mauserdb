# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #172):
- [x] **PHP file upload security audit** — 0 buggar, ingen filuppladdningskod (Worker A)
- [x] **Angular unsubscribe audit** — 7 buggar fixade (Worker B)
- [x] **PHP SQL query optimization** — 8 buggar fixade (Worker A)
- [x] **Angular template type-safety** — 40 buggar fixade (Worker B)

### Nasta buggjakt-items (session #173+):
- [ ] **PHP rate limiting audit** — endpoints utan throttling (login, API-anrop)
- [ ] **Angular lazy-loading completeness** — komponenter som inte lazy-loadas korrekt
- [ ] **PHP error response standardization** — inkonsistenta HTTP-statuskoder och felformat
- [ ] **Angular accessibility audit** — ARIA-attribut, keyboard-navigering, kontrast
- [ ] **PHP session security audit** — session fixation, regeneration, cookie-flaggor

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
