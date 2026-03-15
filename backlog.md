# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-15)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [ ] **Buggjakt: OperatorRanking streaks** — verifiera calcStreaks() med riktig data
- [ ] **Buggjakt: 6 endpoints med saknade tabeller** — prediktivt-underhall, skiftoverlamning, rebotling, operators, news, bonus
- [ ] **Buggjakt: Unused vars i 3 controllers** — SkiftjamforelseController ($today, $lagstStopp, $lagstStoppMin, IDEAL_CYCLE_SEC), GamificationController ($role), SkiftoverlamningController (deprecated nullable param)
- [ ] **Buggjakt: Nyare controllers (batch 2)** — 20+ ogranskade: Kassationsanalys, Veckorapport, Heatmap, Pareto, OeeWaterfall, Morgonrapport, DrifttidsTimeline, ProduktionsPuls, ForstaTimmeAnalys, MyStats
- [x] **Buggjakt: PHP catch($e) cleanup** — 119 oanvanda $e fixade i 49 filer
- [x] **Buggjakt: DST datum-buggar** — 2 buggar fixade (GamificationController streak, PrediktivtUnderhall MTBF)
- [x] **Buggjakt: SkiftoverlamningController auth** — GET-endpoints saknade requireLogin(), fixat
- [x] **Buggjakt: Frontend subscription leaks** — alla 42 components OK, inga lacker
- [x] **Buggjakt: Frontend trackBy** — ~270 ngFor utan trackBy fixade (prestandabugg vid polling)
- [x] **Buggjakt: Chart.js memory leaks** — alla 32 components med Chart.js har chart?.destroy()

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
