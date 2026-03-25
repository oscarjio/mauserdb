# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-25)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync backend+dist till dev.mauserdb.com (se memory/feedback_deploy_workflow.md)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Hogsta prioritet (session #329):
- [ ] **rsync --exclude db_config.php** — dev-serverns config skrivs over vid varje deploy, lagg till exclude-regel
- [ ] **operatorsbonus config validering** — config-inputs saknar required-attribut och inline felmeddelanden
- [ ] **Granska resterande templates for svenska** — Worker B fixade 9 filer, men fler kan ha problem
- [ ] **Testa alla sidor visuellt** — logga in pa dev.mauserdb.com, surfa igenom varje vy
- [ ] **Granska produktion_procent-grafer** — verifiera att delta-fix ger ratt kurvor over tid

### Klart (session #328):
- [x] Verifiera produktion%-fix pa dev — 59%, korrekt 0-100%
- [x] Frontend deploy — bygg + rsync, fixade db_config.php
- [x] PHP date/timezone audit — rent, alla Europe/Stockholm
- [x] PHP authorization audit — 3 buggar fixade (admin-krav saknades)
- [x] Angular form validation audit — rent (utom operatorsbonus minor)
- [x] Frontend UX — 21 buggar (19 diakritiker + 2 engelska rubriker)
- [x] Full endpoint-test — 107 endpoints, 0 st 500-fel

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
