# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-26)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync backend+dist till dev.mauserdb.com (se memory/feedback_deploy_workflow.md)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #334):
- [x] **Error handling** — PHP: alla ~90 controllers OK, global Throwable-catch. Angular: 96 services OK, catchError+retry+timeout
- [x] **produktion_procent FIX** — var momentan (ej kumulativ), delta-logik fran #330 felaktig, aterställd till ravaarden med cap 100
- [x] **Rollbaserad navigation** — menyer + guards + backend rollkontroll matchar. VD-dashboard/veckorapport fick admin-check
- [x] **Lazy loading/routing** — alla guards korrekta
- [x] **Internationalisering** — inga engelska strangar i UI
- [x] **Responsivitet FIX** — hamburger-meny for mobil tillagd i menu-komponenten
- [x] **115 endpoints testade** — 0 fel (27 publika OK, 88 auth-skyddade returnerar 401/403 korrekt)

### Nasta (session #335):
- [ ] **Granska rebotling-sidor pa djupet** — data korrekt? grafer meningsfulla? berakningar stammer med prod DB?
- [ ] **Testa operatorsbonus-berakningar** — jamfor med prod DB-data, verifiera formler
- [ ] **Granska VD-dashboard KPI:er** — stammer siffror med verkligheten? 10-sek overblick?
- [ ] **Testa formulärvalidering** — alla inputfält har ratt validering och felmeddelanden
- [ ] **Granska caching-strategi** — onodiga HTTP-anrop? data som borde cachas?

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
