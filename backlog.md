# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-25)

Agaren vill ha en grundlig genomgang av HELA sidan med den nya datan vi har.
Vi har nu: prod_db_schema.sql (facit), deploy-pipeline (rsync till dev), SSH-nyckel.

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync backend+dist till dev.mauserdb.com (se memory/feedback_deploy_workflow.md)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Hogsta prioritet (session #327):
- [ ] **Statistik-sidan forbeattring** — grafen visar produktion_procent men vaardena ser konstiga ut (kumulativa fran PLC, gar over 100%). Undersok vad PLC:n skickar och om vi behover berakna delta eller momentan procent istallet
- [ ] **Skiftrapport grundlig test** — surfa genom alla flikar (oversikt, statistik, analys, trender) pa dev.mauserdb.com, fixa alla kvarstaende 500-fel och darlig data
- [ ] **Frontend UX-genomgang** — granska ALLA sidor som en anvandare skulle: ar menyer ratt, funkar navigation, visas data korrekt, ar dark theme konsekvent?
- [ ] **Backend endpoint-test mot dev** — curl VARJE endpoint, verifiera 200 och korrekt JSON. Fixa alla som failar.
- [ ] **Produktion%-berakning** — produktion_procent fran PLC ar kumulativ (8,17,30,43...). For grafen behover vi antingen delta per cykel eller en annan berakning

### Nasta (session #328+):
- [ ] **PHP date/timezone audit** — inkonsekvent datumhantering
- [ ] **Angular HTTP error handling audit** — saknade catchError, timeout
- [ ] **PHP authorization audit** — saknade behoorighetskontroller
- [ ] **Angular form validation audit** — saknad validering

## Parkerade features (ta inte dessa nu)

- [ ] Developer mode + feature flags (plan finns i fancy-skipping-gizmo.md)
- [ ] Driftstopp PLC-kommando
- [ ] Dashboards favoritlayout
- [ ] Realtids-notifikationer
