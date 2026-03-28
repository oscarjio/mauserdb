# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-28)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #373):
- [x] Input-validering audit — 44 controllers alla OK (Worker A)
- [x] Cache-strategi review — 10 controllers OK (Worker A)
- [x] Full endpoint-test 114 endpoints 0x500 + SQL-audit 1 fix (Worker A)
- [x] Rebotling operatorsbonus UX — fargkodning+formelkort+tooltips (Worker B)
- [x] Admin-sidor UX — users-admin pagination (Worker B)
- [x] Angular bundle/lazy loading — redan optimerad (Worker B)
- [x] Lifecycle-audit — 0 leaks (Worker B)

### Nasta (session #374):
- [ ] Error recovery UX — vad ser anvandaren vid nere/timeout?
- [ ] Rebotling historik-vy forbattring — filtrera per operator/period
- [ ] API rate limiting review — skydd mot overbelastning
- [ ] Accessibility-audit — tangentbordsnavigering, aria-labels
- [ ] PHP 8.x compatibility check — deprecated functions
