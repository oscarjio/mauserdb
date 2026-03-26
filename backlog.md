# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-26)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync backend+dist till dev.mauserdb.com (se memory/feedback_deploy_workflow.md)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Pagaende (session #337):
- [~] **Sakerhet-audit** — XSS, SQL injection, CSRF i alla PHP controllers (Worker A)
- [~] **Granska operatorsbonus-UI** — korrekt data, motiverande, dark theme (Worker B)
- [~] **Granska VD-dashboard UI** — snabb laddning, 10-sek overblick, korrekt data (Worker B)
- [~] **Testa alla modaler/dialoger** — oppnas/stangs korrekt, Escape fungerar (Worker B)
- [~] **Granska felhantering i frontend** — catchError + svenskt felmeddelande i alla services (Worker B)
- [~] **Testa 100+ endpoints** — curl mot dev.mauserdb.com, leta 500-fel (Worker A)
- [~] **Granska operatorsbonus backend** — berakningslogik korrekt? (Worker A)

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
