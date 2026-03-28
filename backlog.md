# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-28)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #378):
- [x] Rebotling historik frontend — daglig-endpoint med filter/sortering/pagination (Worker B)
- [x] Statistik dashboard — manads/kvartalsjamforelser med pilar+fargkodning + 180d/365d (Worker B)
- [x] Operatorsbonus — klickbar drilldown per operator med KPI+historik (Worker B)
- [x] Driftstopp-analys — typ-filter och min-langd-filter (Worker B)
- [x] Endpoint-test 115 endpoints 0x500 <1s (Worker A)
- [x] SQL-audit 0 mismatches (Worker A)
- [x] Backend controller-granskning — berakningar OK, 5030 cykler 0 diskrepanser (Worker A)
- [x] Lifecycle-audit alla komponenter 0 lackor (Worker B)

### Nasta (session #379):
- [ ] Rebotling live-dashboard — granska UX, verifiera realtidsdata mot prod
- [ ] Operatorsbonus — trendgraf per operator (daglig/veckovis utveckling)
- [ ] Statistik dashboard — forbattra grafernas interaktivitet (hover, zoom, export)
- [ ] Admin-sidor — granska alla CRUD-operationer, UX-forbattringar
- [ ] Driftstopp-analys — timeline forbattring, barre datumnavigering
