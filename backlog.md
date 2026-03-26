# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-26)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync backend+dist till dev.mauserdb.com (se memory/feedback_deploy_workflow.md)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #336):
- [x] **Admin-panel audit** — 3 controllers OK, bcrypt, prepared statements, rollkontroll
- [x] **Prestanda-audit** — 4 N+1-fixar, skiftjamforelse 270 queries -> 2 queries (15s -> 0.7s)
- [x] **Skiftrapporter verifierade** — aggregeringar korrekta mot prod DB
- [x] **150 endpoints testade** — 0 st 500, 0 timeouts
- [x] **Tvattlinje/saglinje/klassificering tom-tillstand** — alla 12 komponenter OK, banner tillagd
- [x] **PDF-export audit** — 3 system OK, svenska tecken, tom data hanteras
- [x] **Diakritikfix** — 4 admin-sidor fixade (Atkomst->Atkomst etc)

### Nasta (session #337):
- [ ] **Granska operatorsbonus-UI** — visas korrekt data? motiverande for operator?
- [ ] **Granska VD-dashboard UI** — laddar snabbt? 10-sek overblick? korrekt data?
- [ ] **Testa alla modaler/dialoger** — oppnas/stangs korrekt? Escape fungerar?
- [ ] **Granska felhantering i frontend** — alla API-anrop har catchError + svenskt felmeddelande?
- [ ] **Sakerhet-audit** — XSS, SQL injection, CSRF i PHP controllers

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
