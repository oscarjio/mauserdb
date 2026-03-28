# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-28)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #375):
- [x] Rebotling skiftrapport — KPI-forbattringar backend + trendgrafer frontend (Worker A+B)
- [x] Driftstopp-analys — orsaksfordelning + veckotrend endpoints (Worker A)
- [x] Admin audit-logg — redan implementerad (270 rader i prod) (Worker B)
- [x] Notifikationer UX — redan implementerad (alarm-historik+alerts) (Worker B)
- [x] Frontend bundle-optimering — redan optimerad (69KB main, 137/138 lazy) (Worker B)
- [x] Full endpoint-test 115 endpoints 0x500 <1s + SQL-audit 0 mismatches (Worker A)
- [x] Data-verifiering 5030 cykler 0 diskrepanser (Worker B)
- [x] Prestandafix SkiftplaneringController ensureTables 2.4s->0.13s (Worker A)

### Nasta (session #376):
- [ ] Rebotling skiftrapport — anvand nya KPI-endpoints i frontend (operator-kpi-jamforelse)
- [ ] Driftstopp-analys frontend — anvand nya orsaksfordelning+veckotrend endpoints
- [ ] Operatorsbonus — granska berakningar mot prod-data
- [ ] Statistik dashboard — forbattra KPI-kort och grafer
- [ ] Granska alla admin-sidor — CRUD, validering, UX
