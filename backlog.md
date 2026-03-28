# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-28)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #374):
- [x] PHP 8.x audit — 0 deprecated, PHP 8.2 fullt kompatibel (Worker A)
- [x] API rate limiting — RateLimiter.php implementerad 120 req/min (Worker A)
- [x] Error recovery backend — 1126 catch-block, 0 tysta, alla korrekt JSON (Worker A)
- [x] Full endpoint-test 114 endpoints 0x500 <1s + SQL-audit 0 mismatches (Worker A)
- [x] Error recovery UX — interceptor+timeout+catchError alla 88 services OK (Worker B)
- [x] Rebotling historik — operatorsfilter+periodval+CSV redan pa plats (Worker B)
- [x] Accessibility — 13 fixar aria-labels+for/id i 3 filer (Worker B)

### Nasta (session #375):
- [ ] Rebotling skiftrapport — forbattra grafer och KPI-visning
- [ ] Admin audit-logg — visa vem som andrat vad (om data finns)
- [ ] Driftstopp-analys — forbattra timeline och orsaksfordelning
- [ ] Frontend bundle-optimering — lazy load tyngre moduler
- [ ] Notifikationer UX — visa aktiva alarmer tydligare
