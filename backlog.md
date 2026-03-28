# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-28)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #391):
- [x] Driftstopp-sidor: verifierat timeline, orsaksfordelning, veckotrend mot prod DB — OK
- [x] Skiftrapport: end-to-end test — 28 rapporter, operatordata, CSV/PDF — OK
- [x] VD-dashboard + executive-dashboard: KPI-berakningar verifierade mot prod DB — OK
- [x] Morgonrapport + veckorapport: siffror stammer (VeckorapportController SQL fixad) — OK
- [x] Operatorsportal: alla sidor granskade ur operators perspektiv — OK

### Nasta (session #392):
- [ ] manads-aggregat prestanda: 12.8s — optimera (cachning eller batch-query)
- [ ] Rebotling historik-sidor: verifiera all historisk data mot prod DB
- [ ] Admin-sidor: grundlig test av alla CRUD-operationer med edge cases
- [ ] Statistik-sidor: verifiera berakningar (OEE, trender, jamforelser) mot prod DB
- [ ] Gamification/achievements: granska poangberakningar och badges mot prod DB
