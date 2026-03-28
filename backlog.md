# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-28)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #376):
- [x] Operator-KPI-jamforelse stapeldiagram i skiftrapport (Worker B)
- [x] Driftstopp orsaksfordelning doughnut + veckotrend linjediagram (Worker B)
- [x] Operatorsbonus berakningar verifierade mot prod — OK (Worker A)
- [x] Admin CRUD-audit — alla controllers OK (Worker A)
- [x] Endpoint-test 113 endpoints 0x500 <1s (Worker A)
- [x] SQL-audit 0 mismatches (Worker A)
- [x] Lifecycle-audit 0 leaks + data-verifiering 5030 cykler 0 diskrepanser (Worker B)

### Nasta (session #377):
- [ ] Granska operatorsbonus-sidan UX — visa KPI-detaljer per operator tydligare
- [ ] Rebotling historik — forbattra filterfunktioner och sortering
- [ ] Statistik dashboard — forbattra periodval och jamnforelser
- [ ] Granska navigationsmenyn — alla sidor narbara, korrekt ordning
- [ ] Rebotling live-dashboard — verifiera att data uppdateras korrekt (TITTA BARA, ror ej koden)
