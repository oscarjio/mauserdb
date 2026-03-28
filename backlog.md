# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-28)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #377):
- [x] Operatorsbonus UX — KPI-detaljer+radar+fargkodning (Worker B) + backend 7 nya falt per operator (Worker A)
- [x] Rebotling historik — daglig endpoint med filter/sortering/pagination (Worker A)
- [x] Statistik dashboard — periodval fix [ngValue] + manads/kvartalsjamforelser backend (Worker A+B)
- [x] Navigationsmenyn — routerLinkActive fix (Worker B)
- [x] Rebotling live-dashboard — data verifierad OK, 5 API:er valid JSON (Worker B)
- [x] Endpoint-test 169 endpoints 0x500 <1s (Worker A)
- [x] SQL-audit 0 mismatches (Worker A)
- [x] Lifecycle-audit 40+ komponenter 0 lackor (Worker B)
- [x] Prestandafix driftstopp table-check 1.6s->0.23s (Worker A)
- [x] Data-verifiering 5030 cykler 0 diskrepanser (Worker B)

### Nasta (session #378):
- [ ] Rebotling historik frontend — integrera daglig-endpoint med filter/sortering UI
- [ ] Statistik dashboard frontend — integrera manads/kvartalsjamforelser fran backend
- [ ] Operatorsbonus — detaljvy per operator (klickbar rad -> drilldown)
- [ ] Skiftrapport — granska och forbattra UX, verifiera berakningar
- [ ] Driftstopp-analys — forbattra timeline UX och filterfunktioner
