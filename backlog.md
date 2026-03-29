# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-29)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #401):
- [x] Operatorssidor — 7 sidor granskade, 3 buggar fixade (Worker B)
- [x] Admin-sidor — 6 controllers CRUD-audit OK, 0 SQL-buggar (Worker A)
- [x] Driftstopp-registrering — end-to-end OK, 8 stopporsaker verifierade (Worker A)
- [x] Skiftoverlamning — shift-handover+skiftoverlamning OK (Worker B)
- [x] Prestanda-audit — 30+ endpoints <500ms, 0x500 (Worker A)
- [x] KRITISK: mb_substr UTF-8 bugg fixad (shift-plan operators-list) (Worker A)

### Nasta (session #402):
- [ ] Rapport-sidor — granska veckorapport, manadsrapport, kvartalsrapport
- [ ] Export-funktioner — verifiera CSV/PDF-export pa alla sidor
- [ ] Gamification — granska teamspelare, operator-ranking, achievements
- [ ] OEE-sidor — verifiera OEE-berakningar mot prod DB
- [ ] Navigering — verifiera alla routes och lazy loading
