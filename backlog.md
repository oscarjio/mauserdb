# Worker Backlog — MauserDB

*Lead-agenten fyller på. Workers plockar uppgifter härifrån.*
*Håll 5-10 öppna items. Markera med [x] när klart.*

## Förbättringar

- [x] **Operatörsranking historik** — leaderboard-trender över tid, visa placeringsändring per vecka, motiverande "klättrare"-indikator
- [x] **Operatörs-feedback analys** — operator_feedback-tabell finns i DB men saknar UI. Visa stämningsöversikt, trender, VD ser personalläge
- [ ] **Daglig sammanfattning auto-generering** — backend-endpoint som genererar daglig KPI-sammanfattning som VD kan se utan att navigera flera sidor
- [ ] **Produktionskalender förbättring** — visa produktionsvolym + kvalitet per dag i kalendervy med färgkodning (grön/gul/röd)
- [ ] **Målhistorik-analys** — rebotling_goal_history finns i DB men saknar visualisering. Visa hur produktionsmål ändrats över tid och effekt på prestation
- [ ] **Underhållsprognos** — baserat på maintenance_log/service_intervals — förutse nästa underhåll, varna VD innan det är dags
