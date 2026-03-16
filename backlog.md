# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-16)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **Buggjakt: Frontend services (batch 1)** — Worker B #117 — 26 buggar fixade i 5 services
- [x] **Buggjakt: Produktion-controllers** — Worker A #117 — 25 buggar fixade i 8 controllers
- [ ] **Buggjakt: Frontend services (batch 2)** — 15 services: kvalitetstrend, kvalitetstrendanalys, kvalitets-trendbrott, leveransplanering, maskin-drifttid, maskinhistorik, maskin-oee, maskinunderhall, morgonrapport, my-stats, operator-portal, ranking-historik, rebotling, rebotling-analytics, tidrapport
- [ ] **Buggjakt: Kassation/Kvalitet-controllers** — 13 controllers: KassationsanalysController, KassationsDrilldown, KassationskvotAlarm, Kassationsorsak, KassationsorsakPerStation, KvalitetscertifikatController, Kvalitetstrendanalys, KvalitetstrendController
- [ ] **Buggjakt: Rebotling-controllers** — RebotlingAdmin, RebotlingAnalytics, RebotlingSammanfattning, RebotlingStationsdetalj, RebotlingTrendanalys, RebotlingProduct (EJ RebotlingController = live)
- [ ] **Buggjakt: Frontend components** — Granska Angular-components for template-fel, saknad trackBy, felaktiga bindings
- [ ] **Buggjakt: Stopporsak/Skift-controllers** — StopporsakController, StopporsakRegistrering, StopporsakTrend, StopptidsanalysController, SkiftrapportController, SkiftplaneringController

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
