# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-16)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **PHP boundary validation** — 5 fixar: limit min/max, offset cap, trim() (Worker A #131)
- [x] **Angular form validation** — 4 fixar: required/min/max pa leveransplanering-formuler (Worker B #131)
- [x] **PHP SQL injection re-audit** — 7 fixar: period/granularity whitelists (Worker A #131)
- [x] **Angular error state UI** — 26 fixar: felmeddelanden i 6 komponenter (Worker B #131)
- [x] **PHP date range validation** — 10 fixar: from<=to swap, 365-dagars cap (Worker A #131)
- [ ] **PHP HTTP method enforcement** — granska att controllers avvisar felaktiga HTTP-metoder
- [ ] **Angular memory profiling** — granska stora komponenter for minneslakor
- [ ] **PHP unused variables cleanup** — ta bort oanvanda variabler (intelephense-varningar)
- [ ] **PHP CORS/headers audit** — granska att ratt CORS-headers satts i alla endpoints
- [ ] **Angular accessibility audit** — granska aria-attribut, keyboard navigation, kontrastnivaer

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
