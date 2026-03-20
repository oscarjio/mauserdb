# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #206):
- [x] **PHP classes/ header injection audit** — 3 buggar: CRLF i CORS-headers (Worker A)
- [x] **PHP classes/ error handling consistency audit** — 4 buggar: catch Exception->Throwable (Worker A)
- [x] **Angular HTTP error UX audit** — 3 buggar: tysta fel i leveransplanering/batch-sparning (Worker B)
- [x] **Angular form accessibility audit** — 11 buggar: saknade for/id-par i 9 komponenter (Worker B)

### Nasta buggjakt-items (session #207+):
- [ ] **PHP classes/ SQL column name verification** — kolumnnamn i queries matchar faktiska DB-kolumner?
- [ ] **PHP classes/ session fixation audit** — session_regenerate_id vid login, secure/httponly pa cookies
- [ ] **Angular pipe/transform audit** — felaktiga DecimalPipe/DatePipe-format, saknade locale-settings
- [ ] **PHP classes/ CSRF token audit** — saknade CSRF-tokens pa state-changing endpoints
- [ ] **Angular lazy loading + route preload audit** — moduler som borde lazy-loadas men inte gor det

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
