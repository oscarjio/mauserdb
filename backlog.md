# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-26)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Nasta (session #342):
- [ ] **Granska kvalitetskontroll-UI** — kassation, reklamation, kvalitetscertifikat korrekt?
- [ ] **Granska energi/miljo-UI** — energiforbrukning, miljorapport, grafer stammer?
- [ ] **Granska underhallsplanering-UI** — schemalagt underhall, historik, notifieringar OK?
- [ ] **Verifiera produktion_procent** — verkar kumulativ, undersok berakning mot prod DB
- [ ] **Rensa oanvanda $from/$to params** — GamificationController countBadgesTotal har oanvanda signaturparams

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
