# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-27)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #367):
- [x] Performance month-compare 1032ms→540ms (DATE_FORMAT→range scan + query-konsolidering)
- [x] Rebotling operatorsbonus — rattvis (op1/op2/op3 identiskt krediterade, UNION ALL)
- [x] Admin CRUD end-to-end — operatorer + skiftplanering verifierat OK
- [x] Caching-strategi — 6 controllers, TTL 5-30s, acceptabelt
- [x] Frontend bundle — redan 151 kB (lazy loading pa plats), 8.8MB var gammal matning
- [x] 111 endpoints 0x500 alla <2s + deploy dev OK

### Nasta (session #368):
- [ ] **Month-compare vidare optimering** — 540ms kan forbattras med covering index
- [ ] **Write-through cache invalidering** — explicit cache-rensning vid admin-CRUD
- [ ] **Rebotling heatmap UX** — forbattra interaktivitet och tooltips
- [ ] **Error monitoring** — centralisera error_log till filtrerbar dashboard
- [ ] **E2E regressionstest** — automatiserat testsvit for kritiska floden

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
