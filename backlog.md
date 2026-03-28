# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-28)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #392):
- [x] manads-aggregat 12.8s → 0.34s (33x snabbare) + veckotrend 1.2s→0.27s + vecko-aggregat 2.6s→0.49s
- [x] HistorikController SQL-fix: getMonthly/getYearly GROUP BY skiftraknare (650→793 IBC, matchar prod DB)
- [x] GamificationController SQL-fix: COUNT(*)→MAX(ibc_ok) per skiftraknare + op-tilldelning utan dubbelrakning
- [x] Rebotling historik: verifierad mot prod DB (Worker A) + 3 komp UX OK (Worker B)
- [x] Admin-sidor: 10 sidor granskade, 0 buggar (Worker B)
- [x] Statistik-sidor: OEE/trender verifierade mot prod DB + 4 komp UX OK
- [x] Gamification: poangberakningar fixade + UX 3 flikar OK
- [x] 107 endpoints 0x500, alla <1.1s

### Nasta (session #393):
- [ ] Operatorsbonus: verifiera berakningar efter GamificationController-fix
- [ ] Skiftrapport: end-to-end test med nya HistorikController-siffror
- [ ] Dashboard KPI: verifiera att nya korrekta IBC-siffror propagerar till VD-vy
- [ ] Lasttest: parallella anrop mot optimerade endpoints (manads-aggregat, veckotrend)
- [ ] Rebotling cykeltider: granska berakningar (medel, min, max) mot prod DB
