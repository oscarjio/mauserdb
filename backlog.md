# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-17)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [ ] **PHP HTTP method enforcement** — granska att controllers avvisar felaktiga HTTP-metoder (Worker A #132)
- [ ] **Angular memory profiling** — granska stora komponenter for minneslakor (Worker B #132)
- [ ] **PHP unused variables cleanup** — ta bort oanvanda variabler (Worker A #132)
- [ ] **PHP CORS/headers audit** — granska att ratt CORS-headers satts i alla endpoints (Worker A #132)
- [ ] **Angular accessibility audit** — granska aria-attribut, keyboard navigation (Worker B #132)
- [ ] **Angular template null-safety** — optional chaining, null-guards i templates (Worker B #132)
- [ ] **PHP error response consistency** — granska att alla error-svar har samma JSON-format
- [ ] **Angular route guard audit** — granska att alla admin-routes har AuthGuard

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
