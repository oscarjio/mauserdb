# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-26)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync backend+dist till dev.mauserdb.com (se memory/feedback_deploy_workflow.md)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nasta (session #339):
- [ ] **Granska rebotling-live data mot prod DB** — stammer IBC-data, operatorsnamn, skiftinfo?
- [ ] **Granska kassationsanalys** — berakningar, grafer, Pareto-diagram korrekt?
- [ ] **Granska leveransplanering UI** — formular, validering, CRUD, kalenderfunktion
- [ ] **Granska maskinunderhall UI** — formular, historik, varningsnivaer
- [ ] **End-to-end test: VD-flodet** — logga in som VD, navigera alla sidor, verifiera data

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
