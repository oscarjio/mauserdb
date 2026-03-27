# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-27)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Pagaende (session #366):
- [~] **Full endpoint-stresstest + PHP controller-audit** — Worker A testar ALLA endpoints, granskar edge cases, fixar
- [~] **Integration test API-floden** — Worker A testar kompletta floden (operators, rebotling, statistik, filter)
- [~] **Error/404-hantering** — Worker A saker att okanda actions ger 404 inte 500
- [~] **Data-korrekthet UI vs DB** — Worker B jamfor API-svar mot prod DB for alla rebotling-sidor
- [~] **Angular kodgranskning + build** — Worker B granskar services/guards/interceptors, fixar varningar
- [~] **Chart-datakorrekthet** — Worker B verifierar att alla grafer visar ratt data med ratt axlar/labels

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
