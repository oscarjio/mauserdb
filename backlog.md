# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #205):
- [x] **PHP date/timezone consistency audit** — 1 bugg: update-weather.php saknade timezone (Worker A)
- [x] **PHP file upload validation audit** — 0 buggar, inga upload-endpoints finns (Worker A)
- [x] **Angular i18n/hardcoded string audit** — 11 buggar: engelska UI-strangar i gamification, operator-ranking, produktions-sla (Worker B)
- [x] **Angular change detection audit** — 0 buggar, trackBy korrekt, inga tunga template-berakningar (Worker B)

### Nasta buggjakt-items (session #206+):
- [ ] **PHP classes/ header injection audit** — CRLF i Location/Set-Cookie-headers
- [ ] **PHP classes/ error handling consistency audit** — blandning av throw/return-false/die, saknade try-catch
- [ ] **Angular HTTP error UX audit** — visar komponenter ratt felmeddelanden for anvandaren vid 4xx/5xx?
- [ ] **PHP classes/ SQL column name verification** — kolumnnamn i queries matchar faktiska DB-kolumner?
- [ ] **Angular form accessibility audit** — saknade for/id-par, aria-labels pa inputs, required-attribut

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
