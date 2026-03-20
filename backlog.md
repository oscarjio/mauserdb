# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #216):
- [x] **PHP classes/ SQL ORDER BY injection audit** — RENT, alla redan whitelist-skyddade (Worker A)
- [x] **PHP classes/ file_get_contents/curl SSRF audit** — RENT, ingen user-input till URL (Worker A)
- [x] **Angular HTTP retry logic audit** — RENT, redan fixat i session #208 (Worker B)
- [x] **Angular memory leak audit (re-audit)** — 4 setTimeout-lackor fixade (Worker B)

### Nasta buggjakt-items (session #217+):
- [ ] **PHP classes/ session handling audit** — session fixation, saknad regenerate_id
- [ ] **PHP classes/ error response consistency audit** — blandade JSON/text-svar, saknade HTTP-statuskoder
- [ ] **Angular form validation audit** — saknad client-side validering pa formuler
- [ ] **PHP classes/ SQL UNION injection audit** — dynamiska UNION-klausuler utan sanitering
- [ ] **Angular lazy loading + bundle size audit** — onodigt stora imports, saknad tree-shaking

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
