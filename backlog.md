# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-29)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Pagaende (session #400):
- [ ] Operatorssidor — granska my-bonus, operator-dashboard, min-dag, my-stats (Worker B)
- [ ] Admin-sidor — granska alla admin-CRUD sidor, edge cases (Worker A)
- [ ] Driftstopp-registrering — verifiera stopporsak-flödet end-to-end (Worker A)
- [ ] Skiftöverlämning — verifiera shift-handover/skiftoverlamning data (Worker B)
- [ ] Prestanda-audit — hitta endpoints >500ms, optimera (Worker A)
