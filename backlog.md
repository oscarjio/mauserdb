# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #203):
- [x] **Angular HTTP retry/timeout audit** — 0 buggar, alla services har timeout/catchError/retry (Worker B)
- [x] **PHP classes/ integer overflow/type juggling audit** — 5 buggar: saknade bounds i 4 controllers (Worker A)
- [x] **PHP classes/ error disclosure audit** — 1 bugg: json_last_error_msg exponerad (Worker A)
- [x] **Angular form XSS audit** — 0 buggar, inga innerHTML/bypassSecurityTrust (Worker B)

### Nasta buggjakt-items (session #204+):
- [ ] **PHP classes/ race condition audit** — concurrent requests, TOCTOU, saknade DB-transaktioner
- [ ] **Angular router guard audit** — saknade guards pa skyddade routes, felaktig rollkontroll
- [ ] **PHP classes/ SQL LIKE injection audit** — saknad escapning av %, _, wildcard i LIKE-queries
- [ ] **Angular environment config audit** — hardkodade API-URLer, dev-flaggor i produktion
- [ ] **PHP classes/ date/timezone consistency audit** — blandning av UTC/lokal tid, sommartid-buggar

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
