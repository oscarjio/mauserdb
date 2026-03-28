# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-28)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nasta (session #385):
- [ ] Operatorsbonus — verifiera bonusberakningar end-to-end mot prod DB
- [ ] Skiftrapport — testa rapportgenerering + email-funktion + KPI-data
- [ ] Statistik — granska alla grafer for korrekt data + adaptiv granularitet
- [ ] Gamification — verifiera poang/utmarkelser/leaderboard mot prod data
- [ ] Mobilanpassning — testa alla sidor pa mobil-viewport (< 768px)
