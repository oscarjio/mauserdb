# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Pagaende (session #215):
- [ ] **PHP classes/ integer overflow/bounds audit** — saknade range-checks pa intval/floatval (Worker A)
- [ ] **PHP classes/ array key existence audit** — saknade isset/array_key_exists fore access (Worker A)
- [ ] **Angular pipe/filter edge case audit** — felaktiga date/number pipes, null-inputs (Worker B)
- [ ] **Angular routing guard audit** — saknade canDeactivate, felaktiga redirects (Worker B)

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
