# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-15)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [WIP] **Buggjakt: Batch 2 controllers (10 st)** — Worker A #108 granskar Kassationsanalys, Veckorapport, Heatmap, Pareto, OeeWaterfall, Morgonrapport, DrifttidsTimeline, ProduktionsPuls, ForstaTimmeAnalys, MyStats
- [WIP] **Buggjakt: 6 endpoints saknade tabeller + frontend logikbuggar** — Worker B #108 curl-testar + granskar berakningar i components
- [WIP] **Buggjakt: Unused vars i 3 controllers** — Worker A #108 fixar SkiftjamforelseController, GamificationController, SkiftoverlamningController
- [ ] **Buggjakt: OperatorRanking streaks** — verifiera calcStreaks() med riktig data
- [ ] **Buggjakt: Batch 3 controllers (18 st)** — AlarmHistorik, DagligBriefing, Favoriter, HistoriskSammanfattning, KassationsDrilldown, KvalitetsTrendbrott, OeeTrendanalys, OperatorDashboard, OperatorOnboarding, ProduktionsPrognos, RebotlingStationsdetalj, Skiftplanering, StatistikDashboard, StatistikOverblick, Stopporsak, StopporsakOperator, Stopptidsanalys, Underhallslogg
- [ ] **Buggjakt: Frontend berakningar** — verifiera OEE/bonus/procent-formler i alla components mot backend-formler (konsistens)
- [ ] **Buggjakt: API-routes audit** — verifiera att alla frontend-anrop matchar backend-routes (404-risk)
- [ ] **Buggjakt: PHP error_reporting + logging** — granska att alla controllers loggar fel konsekvent

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
