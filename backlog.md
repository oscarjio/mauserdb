# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-28)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nasta (session #386):
- [ ] PLC-diagnostik — ny sida, granska och testa mot dev
- [ ] Driftstopp — verifiera orsaksfordelning + veckotrend mot prod DB
- [ ] Admin CRUD — fullstandig test av alla admin-sidor (skapa/redigera/ta bort)
- [ ] Prestandaoptimering — langsammare endpoints (vpn 2.3s, maskin-oee 2.7s)
- [ ] Sakerhet — granska CSRF + rate limiting + input-validering end-to-end
