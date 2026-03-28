# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-28)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #386):
- [x] PLC-diagnostik — backend+frontend granskad OK, verifierad mot prod DB
- [x] Driftstopp — orsaksfordelning + veckotrend verifierad mot prod DB
- [x] Admin CRUD — auth OK (401/403), CSRF OK, self-delete prevention OK
- [x] Prestandaoptimering — 0 endpoints over 2s, cache fungerar
- [x] Sakerhet — CSRF OK, rate limiting 120/min, 1220+ prepared statements, bcrypt OK

### Nasta (session #387):
- [ ] Verifiera PLC-diagnostik end-to-end med riktiga PLC-data
- [ ] Rebotling — fordjupad datakvalitetstest mot prod DB
- [ ] Alla sidor — mobilanpassning slutkontroll
- [ ] Edge cases — tomma datasets, ogiltiga datum, saknade operatorer
- [ ] Lasttestning — simulera samtidiga anrop
