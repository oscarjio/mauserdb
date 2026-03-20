# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #202):
- [x] **PHP classes/ session/cookie security audit** — 1 bugg: saknad session timeout-check i api.php (Worker A)
- [x] **PHP classes/ file path traversal audit** — inga buggar, alla 114 PHP-filer anvander hardkodade sokvagar (Worker A)
- [x] **Angular accessibility audit** — 15 buggar: saknade role="alert" i 14 templates (Worker B)
- [x] **Angular memory leak audit** — inga buggar, alla 169 komponenter har korrekt cleanup (Worker B)

### Nasta buggjakt-items (session #203+):
- [ ] **Angular HTTP retry/timeout audit** — saknade retries, for langa timeouts
- [ ] **PHP classes/ integer overflow/type juggling audit** — PHP loose comparison, intval overflow
- [ ] **PHP classes/ error disclosure audit** — stack traces, DB-schema i felmeddelanden
- [ ] **Angular form XSS audit** — innerHTML, bypassSecurityTrust, user-input i templates

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
