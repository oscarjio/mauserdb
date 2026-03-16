# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-16)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [ ] **Buggjakt: Frontend services (batch 1)** — Granska 15 services: auth, bonus, bonus-admin, effektivitet, favoriter, feedback-analys, forsta-timme-analys, heatmap, historisk-produktion, historisk-sammanfattning, kapacitetsplanering, kassationsanalys, kassations-drilldown, kassationskvot-alarm, kassationsorsak-statistik
- [ ] **Buggjakt: Frontend services (batch 2)** — Granska 15 services: kvalitetstrend, kvalitetstrendanalys, kvalitets-trendbrott, leveransplanering, maskin-drifttid, maskinhistorik, maskin-oee, maskinunderhall, morgonrapport, my-stats, operator-portal, ranking-historik, rebotling, rebotling-analytics, tidrapport
- [ ] **Buggjakt: Produktion-controllers** — ProduktionsDashboard, Produktionseffektivitet, Produktionsflode, Produktionskalender, Produktionskostnad, Produktionsmal, Produktionspuls, ProduktionsSla, ProduktionsTakt, ProduktTypEffektivitet
- [ ] **Buggjakt: Kassation/Kvalitet-controllers** — KassationsanalysController, KassationsDrilldown, KassationskvotAlarm, Kassationsorsak, KassationsorsakPerStation, KvalitetscertifikatController, Kvalitetstrendanalys, KvalitetstrendController
- [ ] **Buggjakt: Rebotling-controllers** — RebotlingAdmin, RebotlingAnalytics, RebotlingSammanfattning, RebotlingStationsdetalj, RebotlingTrendanalys, RebotlingProduct (EJ RebotlingController = live)

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
