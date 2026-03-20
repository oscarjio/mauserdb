# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #215):
- [x] **PHP classes/ integer overflow/bounds audit** — 4 bounds-buggar fixade (Worker A)
- [x] **PHP classes/ array key existence audit** — 1 null-check bugg fixad (Worker A)
- [x] **Angular pipe/filter edge case audit** — 7 date pipe null-check buggar fixade (Worker B)
- [x] **Angular routing guard audit** — RENT, inga buggar (Worker B)

### Nasta buggjakt-items (session #216+):
- [ ] **PHP classes/ SQL ORDER BY injection audit** — dynamiska ORDER BY utan whitelist
- [ ] **PHP classes/ file_get_contents/curl audit** — SSRF-risk, saknad URL-validering
- [ ] **Angular HTTP retry logic audit** — saknade retry/timeout pa kritiska anrop
- [ ] **PHP classes/ session handling audit** — session fixation, saknad regenerate_id
- [ ] **Angular memory leak audit (re-audit)** — chart.js instances, event listeners

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
