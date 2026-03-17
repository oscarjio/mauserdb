# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-17)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [ ] **Angular lazy loading audit** — onodiga imports, SharedModule-bloat, bundle-storlekar
- [ ] **PHP error logging consistency** — error_log format, loggniva, PII-kontroll
- [ ] **Angular template accessibility** — aria-labels, tab-index, screen reader
- [ ] **Angular unused imports cleanup** — diagnostics visar oanvanda of, timeout, catchError imports i flera komponenter
- [ ] **PHP unused variables cleanup** — $e i catch-block, $opRows, $dtEx etc (intelephense-varningar)
- [ ] **PHP input validation audit** — isset/empty-kontroller pa $_GET/$_POST parametrar
- [ ] **Angular error state UI audit** — visa felmeddelanden i template vid HTTP-fel

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
