# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-26)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync backend+dist till dev.mauserdb.com (se memory/feedback_deploy_workflow.md)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #335):
- [x] **Rebotling PHP audit** — alla 7 controllers matchar prod DB schema. hourly-rhythm 500-bugg FIXAD (LAG MariaDB-kompat)
- [x] **Operatorsbonus veriferad** — bonusformler korrekt (4 faktorer, rimliga prod DB-resultat)
- [x] **VD-dashboard KPI OK** — OEE, IBC-aggregering, dagsmal korrekt beraknade
- [x] **116 endpoints testade** — 1 bugg (hourly-rhythm) fixad, 0 kvar
- [x] **Rebotling Angular-grafer** — 69 komponenter OK, lifecycle korrekt, statistik-veckojamforelse felhantering fixad
- [x] **Svenska diakritiker** — 20+ filer: Hamtar→Hämtar, Aterstall→Återställ, Forbattring→Förbättring m.fl.
- [x] **Formularvalidering** — 7 formular OK, svenska felmeddelanden
- [x] **Caching-strategi** — 94 services OK, rimliga polling-intervall

### Nasta (session #336):
- [ ] **Granska tvattlinje/saglinje/klassificering-sidor** — fungerar UI aven utan data? Vettiga tom-tillstand?
- [ ] **Granska PDF-export pa djupet** — testa alla rapporter, korrekt data/layout?
- [ ] **Granska admin-panelen** — alla CRUD-operationer, validering, felhantering
- [ ] **Prestanda-audit** — stora queries, N+1-problem, onodiga JOINs?
- [ ] **Testa skiftrapporter** — data korrekt? berakningar stammer?

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
