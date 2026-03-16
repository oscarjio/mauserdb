# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: BUGGJAKT (2026-03-16)

Agaren har bett oss fokusera pa att hitta och fixa buggar. Inga nya features.

- [x] **Buggjakt: Rebotling-controllers** — Worker A #119 — 33 buggar i 5 controllers
- [x] **Buggjakt: Frontend services (batch 3)** — Worker B #119 — 13 buggar i 3 services
- [ ] **Buggjakt: Frontend services (batch 4)** — Produktion-services: produktions-dashboard, produktionsflode, produktionskalender, produktionskostnad, produktionsmal, produktionsprognos, produktionspuls, produktions-sla, produktionstakt, produkttyp-effektivitet
- [ ] **Buggjakt: Frontend services (batch 5)** — Skift/Stopp-services: skiftjamforelse, skiftoverlamning, skiftplanering, skiftrapport, skiftrapport-export, skiftrapport-sammanstallning, stoppage, stopporsaker, stopporsak-operator, stopporsak-registrering, stopporsak-trend, stopptidsanalys
- [ ] **Buggjakt: Frontend services (batch 6)** — Ovrigt: alarm-historik, alerts, andon-board, audit, avvikelselarm, daglig-sammanfattning, drifttids-timeline, kvalitetscertifikat, statistik-dashboard, statistik-overblick, underhallslogg, underhallsprognos, users, vd-dashboard, veckorapport
- [ ] **Buggjakt: Frontend components** — Granska komponent-templates for saknade null-guards, felaktiga pipes, saknade trackBy i *ngFor
- [ ] **Buggjakt: Backend routing/api.php** — Verifiera att alla actions routas korrekt, inga orphan-actions

## Parkerade features (ta inte dessa nu)

- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
- [ ] Rebotling energi/resursforbrukning
- [ ] Rebotling batch-sparning
