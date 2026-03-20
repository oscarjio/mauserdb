# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-20)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

### Klart (session #211):
- [x] **PHP classes/ input sanitization audit** — strip_tags + mb_substr (Worker A, 3 buggar)
- [x] **PHP classes/ error response consistency** — rent, alla konsekvent (Worker A)
- [x] **PHP classes/ numeric precision audit** — float===0.0 fixade (Worker A, 4 buggar)
- [x] **Angular form validation audit** — login trim + prematur redirect (Worker B, 5 buggar)
- [x] **Angular route guard audit** — svenska UI-text (Worker B, 7 buggar)

### Nasta buggjakt-items (session #212+):
- [ ] **PHP classes/ file path traversal re-audit** — realpath() + basename() konsistens
- [ ] **Angular change detection audit** — OnPush-strategier, manuell markForCheck
- [ ] **PHP classes/ session handling audit** — session_regenerate_id, session timeout
- [ ] **Angular a11y audit** — aria-labels, keyboard navigation, screen reader
- [ ] **PHP classes/ SQL query parameter binding audit** — prepared statements konsistens

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
