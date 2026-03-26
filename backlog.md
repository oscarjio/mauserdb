# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-26)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync backend+dist till dev.mauserdb.com (se memory/feedback_deploy_workflow.md)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #333):
- [x] **Granska Angular pipes/direktiv** — inga custom pipes, enbart inbyggda Angular pipes
- [x] **Testa autentisering end-to-end** — bcrypt OK, rate limiting, CSRF, session-timeout, 401 korrekt
- [x] **Granska PDF-export** — SQL matchar schema, OEE-berakningar korrekta
- [x] **Stresstesta polling** — 76 filer OK, alla har clearInterval+ngOnDestroy, inga lackor
- [x] **Granska modaler/dialoger** — dark theme OK, 6 Escape-tangent-fixar, 4 Chart.js-fixar

### Nasta (session #334):
- [ ] **Granska error handling i services** — catchError, retry-logik, felmeddelanden till anvandare
- [ ] **Testa rollbaserad navigation** — admin/operator/vd ser ratt menyer och sidor
- [ ] **Granska lazy loading och routing** — korrekta guards, preloading-strategi
- [ ] **Verifiera responsivitet pa mobil** — tabeller, grafer, navigation pa smal skarm
- [ ] **Granska internationalisering** — alla hardkodade strängar pa svenska, inga kvarvarande engelska

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
