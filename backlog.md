# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-18)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **PHP error logging audit** — 10 buggar (Worker A #161)
- [x] **Angular change detection audit** — 1 bugg (Worker B #161)
- [x] **PHP CORS/headers audit** — inkluderad i error logging (Worker A #161)
- [x] **Angular observable completion audit** — 0 buggar (Worker B #161)
- [x] **PHP response format audit** — inkluderad i error logging (Worker A #161)
- [x] **Angular i18n/hardcoded strings audit** — 0 buggar (Worker B #161)

### Nasta buggjakt-items (session #162+):
- [ ] **PHP session/cookie audit** — session_start, cookie-flaggor, session fixation
- [ ] **Angular form validation audit** — saknad validering, felaktig error display
- [ ] **PHP file I/O audit** — file_get_contents/file_put_contents felhantering, filsokvagar
- [ ] **Angular HTTP retry/timeout audit** — saknade timeouts, retry-strategier
- [ ] **PHP numeric overflow audit** — intval pa stora tal, float-precision i berakningar

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
