# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #217):
- [x] **PHP classes/ session handling audit** — RENT, alla sakerhetsatgarder korrekt implementerade (Worker A)
- [x] **PHP classes/ error response consistency audit** — RENT, alla endpoints returnerar JSON med HTTP-statuskoder (Worker A)
- [x] **PHP classes/ SQL UNION injection audit** — RENT, alla 100+ UNION hardkodade (Worker A)
- [x] **Angular form validation audit** — 2 buggar fixade i underhallslogg (Worker B)
- [x] **Angular lazy loading + bundle size audit** — 2 XLSX tree-shaking fixar (Worker B)

### Nasta buggjakt-items (session #218+):
- [ ] **PHP classes/ date/time edge case audit** — felaktiga strtotime, DST-problem, midnight edge cases
- [ ] **Angular chart.js configuration audit** — felaktiga options, saknad responsivitet, minneslakor
- [ ] **PHP classes/ input sanitization completeness audit** — saknad trim/strip_tags/htmlspecialchars
- [ ] **Angular HTTP error UX audit** — saknade anvandardvandliga felmeddelanden vid 4xx/5xx
- [ ] **PHP classes/ file permission + path validation audit** — saknad kontroll av skrivbara kataloger

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
