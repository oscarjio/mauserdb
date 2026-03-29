# Worker Backlog — MauserDB

*Lead-agenten fyller pa. Workers plockar uppgifter harifran.*
*Hall 5-10 oppna items. Markera med [x] nar klart.*

## PRIORITET: GRUNDLIG GENOMGANG + FORBATTRING (2026-03-29)

### NY KONTEXT — Anvand detta:
- **prod_db_schema.sql** i projektroten — FACIT for alla SQL-queries
- **Deploy till dev**: rsync till /var/www/mauserdb-dev/ pa dev.mauserdb.com (ssh -p 32546)
- **Testa live pa dev**: curl mot https://dev.mauserdb.com/noreko-backend/api.php?action=...
- **Prod DB direkt**: ssh -p 32546 user@mauserdb.com "mysql -u aiab -pNoreko2025 -P 33061 -h 127.0.0.1 mauserdb -e 'QUERY'"

### Klart (session #394):
- [x] Alarm-historik: SQL OK, frontend OK (1 chart, lifecycle, dark theme) — verifierad mot prod DB
- [x] Underhallsprognos: SQL OK, frontend OK (1 chart, 3 datasektioner) — 12 komponenter matchar prod DB
- [x] Produktionskalender/prognos: SQL OK + ProduktionsPrognosController COUNT→MAX fix — frontend OK
- [x] Andon-board: 2 komp OK (7+2 intervals rensas, 1 chart, TV-layout) — realtidsdata OK
- [x] Shift-plan/tidrapport: frontend OK (1 chart, CSV-export, bemanningsvarning)
- [x] 108 endpoints testade 0x500, 10 frontend-komp granskade 0 buggar

### Nasta (session #395):
- [ ] Slow endpoints: operator-ranking 5.4s, morgonrapport 1.7s, statistikdashboard 1.6s — optimera
- [ ] Rebotling-historik: djupgranskning alla historik-endpoints + frontend
- [ ] Kvalitet/kassation: granska kvalitetscontrollers + frontend
- [ ] Stopporsak: verifiera registrering + trender mot prod DB
- [ ] Export-funktioner: granska CSV/PDF/Excel-export pa alla sidor
