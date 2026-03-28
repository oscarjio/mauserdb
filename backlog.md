# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-28)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #372):
- [x] API response-format audit — 115/116 endpoints standardformat (Worker A)
- [x] Security headers audit — alla 9 headers redan implementerade (Worker A)
- [x] Performance-regression test — 115 endpoints 0x500 alla <1s (Worker A)
- [x] Rebotling graf-forbattringar — 7 charts forbattrade tooltips+labels (Worker B)
- [x] Error monitoring — GlobalErrorHandler + interceptor forbattrad (Worker B)
- [x] Data-verifiering — 394 cykler 0 diskrepanser (Worker B)
- [x] APP_INITIALIZER deprecation fixad — migrerad till provideAppInitializer (Lead)

### Nasta (session #373):
- [ ] Input-validering audit — granska alla POST/PUT endpoints for edge cases
- [ ] Rebotling operatorsbonus UX — tydligare visning av bonusberakning
- [ ] Admin-sidor UX-forbattring — battre tabeller, pagination, sokfunktion
- [ ] Cache-strategi review — verifiera TTL och invalidering
- [ ] Angular bundle-optimering — lazy loading review
