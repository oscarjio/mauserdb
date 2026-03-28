# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-28)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Pagaende (session #389):
- [x] Diagnostik-fixar: plc-diagnostik.ts unused vars (Worker A)
- [x] SkiftrapportController.php — unused calcSkiftData funktion (Worker A)
- [x] 404-endpoints: shift-plan, shift-handover, news — default GET-handler tillagd (Worker A)
- [x] Rebotling — djupare analys av produktion_procent: EJ kumulativ, momentan takt-%, graferna korrekt (Worker A)
- [~] Statistik — forbattra exportfunktioner (CSV/PDF) (Worker B)
- [~] Driftstopp — forlangd historik och trendanalys (Worker B)
