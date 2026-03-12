# Worker Backlog — MauserDB

*Lead-agenten fyller på. Workers plockar uppgifter härifrån.*
*Håll 5-10 öppna items. Markera med [x] när klart.*

## Förbättringar

- [x] **Leveransplanering — kundorder vs kapacitet** — matcha kundordrar mot produktionskapacitet, visa leveransprognos och eventuella förseningar
- [x] **Kvalitetscertifikat per batch** — generera kvalitetsintyg/certifikat för avslutade batchar med nyckeltal (kassation%, cykeltid, operatörer)
- [ ] **Operatörs-tidrapport** — automatisk tidrapport baserat på skiftschema + faktisk aktivitet, exporterbar CSV/PDF
- [ ] **Realtids-notifikationer** — push-notiser vid underbemanning, maskinstopp >15 min, produktionsmål uppnått, försenat underhåll
- [ ] **Dashboards favoritlayout** — VD kan välja vilka KPI-kort/widgets som visas på startsidan, drag-and-drop ordning
- [ ] **Historisk produktionsöversikt** — statistiksida: produktion per dag/vecka/månad med trender, jämförbar över perioder. VD:s "hur går det?"-sida
- [ ] **Produktionsflödesvy (Sankey-diagram)** — visuellt IBC-flöde genom rebotling: in → process → godkänd/kassation. Flaskhalsar synliga direkt
- [ ] **Automatiska avvikelseLarm** — varning vid OEE under mål, kassation över gräns, produktionstakt-avvikelse — visas i dashboard
