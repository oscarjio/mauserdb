# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-27)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nasta (session #352):
- [ ] **Felhantering vid nolldata** — A: granska alla controllers for tomma dataset, verifiera att grafer/tabeller hanterar 0 rader korrekt
- [ ] **API-svarstider audit** — A: testa ALLA endpoints med timing, identifiera > 500ms
- [ ] **Datavalidering backend** — A: granska input-validering i alla POST/PUT-endpoints
- [ ] **Tillganglighet (a11y)** — B: aria-labels, kontrast, tangentbordsnavigation
- [ ] **Grafinteraktivitet** — B: tooltips, klickbara datapunkter, exportfunktion i Chart.js-grafer
- [ ] **Error states UI** — B: snygga felmeddelanden vid nerkopplad server, timeouts, tom data

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
