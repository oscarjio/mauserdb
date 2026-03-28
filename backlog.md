# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-28)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nasta (session #390):
- [ ] Endpoint-test: verifiera att 115 endpoints fortfarande ar 0x500 efter alla andringar
- [ ] Granska rebotling-sidor: live-data vs historik — stammer allt?
- [ ] Admin CRUD: testa alla edit/delete/create floden pa dev
- [ ] Operatorsbonus: verifiera berakningar mot prod DB for aktuell vecka
- [ ] Mobilanpassning: granska alla tabeller/grafer pa smal skarm
