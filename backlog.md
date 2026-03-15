# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-15)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [ ] **Buggjakt: OperatorRanking streaks** — calcStreaks() anvande user_id pa rebotling_ibc, fixat till UNION ALL — verifiera med riktig data
- [ ] **Buggjakt: 6 endpoints med saknade tabeller** — prediktivt-underhall, skiftoverlamning, rebotling, operators, news, bonus — kraver DB-tabeller
- [>] **Buggjakt: PHP catch($e) cleanup** — 12 catch-block med oanvanda $e i controllers (Worker A #107)
- [>] **Buggjakt: Edge cases i datum-hantering** — tidszoner, DST, arsskiften i controllers (Worker A #107)
- [>] **Buggjakt: Frontend subscription leaks** — audit alla components for takeUntil/destroy$ (Worker B #107)
- [>] **Buggjakt: Frontend template null-safety + responsivitet** — saknad ?. navigation, overflow (Worker B #107)
- [ ] **Buggjakt: Nyare controllers (batch 2)** — 20+ controllers ogranskade: Kassationsanalys, Veckorapport, Heatmap, Pareto, OeeWaterfall, Morgonrapport, DrifttidsTimeline, ProduktionsPuls, ForstaTimmeAnalys, MyStats
- [ ] **Buggjakt: Chart.js memory leaks** — verifiera chart?.destroy() i alla components med grafer

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
