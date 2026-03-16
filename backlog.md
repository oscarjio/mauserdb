# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-16)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [ ] **Buggjakt: Oanvanda variabler** — RebotlingAnalyticsController (~10 st), BonusAdminController (~6 st), KassationsanalysController (~1 st), RebotlingController (~2 st)
- [ ] **Buggjakt: Tomma catch-block** — 16 filer har catch-block som svaljer fel tyst (BonusAdmin, RebotlingAnalytics, Gamification, StatistikOverblick, VdDashboard, DagligBriefing, m.fl.)
- [ ] **Buggjakt: Rebotling-controllers djupgranskning** — RebotlingAnalyticsController (6769 rader!), RebotlingSammanfattningController, RebotlingTrendanalysController, RebotlingStationsdetaljController
- [ ] **Buggjakt: Maskin-controllers audit** — MaskinDrifttidController, MaskinOeeController, MaskinhistorikController, MaskinunderhallController
- [ ] **Buggjakt: Prognos/planering-controllers** — PrediktivtUnderhallController, LeveransplaneringController, ProduktionsPrognosController, KapacitetsplaneringController
- [ ] **Buggjakt: JSON_UNESCAPED_UNICODE audit** — session #113 hittade 2 controllers utan flaggan. Granska alla controllers for konsekvent unicode-hantering
- [ ] **Buggjakt: Frontend setTimeout/setInterval audit del 2** — session #113 hittade 3 sidor med lackor. Granska resterande sidor

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
