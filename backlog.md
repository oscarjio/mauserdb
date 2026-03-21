# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-21)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #218):
- [ ] **PHP classes/ date/time edge case audit** — felaktiga strtotime, DST-problem, midnight edge cases (Worker A)
- [ ] **PHP classes/ input sanitization completeness audit** — saknad trim/strip_tags/htmlspecialchars (Worker A)
- [ ] **Angular chart.js configuration audit** — felaktiga options, saknad responsivitet, minneslakor (Worker B)
- [ ] **Angular HTTP error UX audit** — saknade anvandardvandliga felmeddelanden vid 4xx/5xx (Worker B)

### Nasta buggjakt-items (session #219+):
- [ ] **PHP classes/ file permission + path validation audit** — saknad kontroll av skrivbara kataloger
- [ ] **Angular template strict null check audit** — saknade ?. eller *ngIf-guards pa potentiellt null-data
- [ ] **PHP classes/ array bounds + isset audit** — saknade isset/array_key_exists fore array-access
- [ ] **Angular reactive polling cleanup audit** — setInterval utan clearInterval, polling som fortsatter efter route-byte
- [ ] **PHP classes/ SQL transaction consistency audit** — saknade beginTransaction/commit/rollback vid multi-query

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
