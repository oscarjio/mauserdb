# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-26)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nasta (session #347):
- [ ] **Granska Alert-systemet** — A: AlertsController+AvvikelselarmController+AlarmHistorikController SQL, B: alert-UI
- [ ] **Granska kassationskvot-alarm** — A: KassationskvotAlarmController SQL+endpoints, B: UI
- [ ] **Granska rebotling-admin djupare** — A: alla endpoints+SQL, B: formulär+validering+UX
- [ ] **Endpoint-sweep: alla kvarvarande controllers** — A: curl alla endpoints, leta 500-fel
- [ ] **Prestandaprofilering runda 2** — A: mät alla endpoints, optimera >500ms

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
