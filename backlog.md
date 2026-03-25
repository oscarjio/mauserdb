# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-25)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync backend+dist till dev.mauserdb.com (se memory/feedback_deploy_workflow.md)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Pagaende (session #330):
- [>] **Grundlig SQL-granskning mot prod_db_schema.sql** — Worker A jamfor alla queries
- [>] **Testa alla endpoints med curl mot dev** — Worker A letar 500-fel
- [>] **Granska produktion_procent-berakning** — Worker A undersok kumulativ vs per skift
- [>] **Grundlig template-genomgang (svenska/dark theme/UX)** — Worker B alla .html
- [>] **Operatorsbonus config validering** — Worker B required + felmeddelanden
- [>] **Lifecycle-bugg-granskning i alla komponenter** — Worker B subscriptions/destroy

### Nasta (session #331):
- [ ] **Forbattra rebotling-grafer** — detaljerade datapunkter, adaptiv granularitet
- [ ] **Granska error handling i Angular services** — saknas catchError pa nagra HTTP-anrop?
- [ ] **Optimera polling-intervall** — ar 5s lagom for alla sidor?
- [ ] **Verifiera operatorsbonus-berakningar** — jamfor med prod DB-data
- [ ] **Granska PDF-export** — kontrollera att alla siffror stammer

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
