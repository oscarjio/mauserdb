# Worker Backlog — MauserDB

*Lead-agenten fyller på. Workers plockar uppgifter härifrån.*
*Håll 5-10 öppna items. Markera med [x] när klart.*

## Förbättringar

- [PÅGÅR] **Daglig sammanfattning auto-generering** — backend-endpoint som genererar daglig KPI-sammanfattning som VD kan se utan att navigera flera sidor
- [PÅGÅR] **Produktionskalender förbättring** — visa produktionsvolym + kvalitet per dag i kalendervy med färgkodning (grön/gul/röd)
- [ ] **Målhistorik-analys** — rebotling_goal_history finns i DB men saknar visualisering. Visa hur produktionsmål ändrats över tid och effekt på prestation
- [ ] **Underhållsprognos** — baserat på maintenance_log/service_intervals — förutse nästa underhåll, varna VD innan det är dags
- [ ] **Kvalitetstrend per operatör** — visa kvalitet% trend per operatör över veckor/månader. Identifiera vilka som förbättras/försämras. VD ser utbildningsbehov
- [ ] **Skiftjämförelse-dashboard** — jämför dag/kväll/nattskift: IBC/h, kvalitet, stopptid. Hjälper VD fördela resurser och identifiera svaga skift
- [ ] **Stopporsak-trendanalys** — visa hur vanligaste stopporsaker utvecklas över tid (veckovis). Avslöjar om åtgärder fungerar eller om problem förvärras
