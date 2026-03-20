# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #210):
- [x] **PHP classes/ date/time edge case audit** — strtotime month-overflow + DST timberakning (Worker A)
- [x] **PHP classes/ concurrent access audit** — duplikat-kontroll serviceintervall + skiftprotokoll (Worker A)
- [x] **Angular lazy loading verification** — alla 130+ routes korrekt lazy-loadade (Worker B, rent)
- [x] **Angular HTTP retry logic audit** — alla services har timeout+retry+catchError (Worker B, rent)
- [x] **Angular memory profiling + svenska UI-text** — oanvand Subject + 34 accentfel (Worker B)

### Nasta buggjakt-items (session #211+):
- [ ] **PHP classes/ input sanitization audit** — htmlspecialchars, strip_tags, filter_var konsistens
- [ ] **PHP classes/ error response consistency** — enhetliga JSON-felformat over alla endpoints
- [ ] **Angular form validation audit** — saknade validators, felmeddelanden, edge cases
- [ ] **PHP classes/ numeric precision audit** — float-jamforelser, avrundning, penningbelopp
- [ ] **Angular route guard audit** — verifiera att skyddade routes kraver inloggning

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
