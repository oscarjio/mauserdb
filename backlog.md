# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-17)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] PHP boundary/pagination validation [Worker A #138]
- [x] PHP error boundary audit [Worker A #138]
- [x] PHP race condition audit — 8 transaktioner tillagda [Worker A #138]
- [x] Angular router parameter validation — open redirect + whitelist [Worker B #138]
- [x] Angular memory profiling — 5 unused imports borttagna [Worker B #138]
- [x] Angular change detection audit — 18+ metodanrop dokumenterade [Worker B #138]
- [ ] **PHP file operation safety** — granska file_get_contents, fopen, fwrite for felhantering och path traversal
- [ ] **Angular HTTP interceptor audit** — granska error/auth interceptors for edge cases
- [ ] **Angular change detection optimering** — implementera computed properties/pipes for 18+ dokumenterade metodanrop
- [ ] **PHP unused variable cleanup** — ta bort oanvanda variabler ($ignored, saveLiveRankingSettings, $opRows)
- [ ] **Angular deprecated API migration** — HttpClientModule -> provideHttpClient()

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
