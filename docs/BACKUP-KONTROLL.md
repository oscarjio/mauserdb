# Backup-kontroll — mauserdb (tvättlinje/rebotling-databasen)

Läskontroll utförd 2026-10-21, uppdaterad 2026-10-22. Endast läsning — ingen SQL som
skriver, inget ändrat på servern.

## Mekanism

- **Crontab** (användaren `user` på mauserdb.com): `0 2 * * * /home/user/mauserdb-backup.sh >> /home/user/db-backups/backup.log 2>&1`
- **Script**: `mysqldump --single-transaction --routines --triggers` av databasen `mauserdb` → `gzip` → `/home/user/db-backups/mauserdb_ÅÅÅÅ-MM-DD_HHMMSS.sql.gz`
- **Retention i scriptet**: `find ... -mtime +30 -delete` (avsett: 30 dagar)
- Ingen systemd-timer för backup. Ingen root-crontab kunde läsas (ingen sudo utan lösenord) — inget tyder på någon ytterligare backup-mekanism.

## Senaste 7 dygnen (2026-10-16 till 2026-10-22)

| Datum | Tid | Storlek |
|---|---|---|
| 2026-10-16 | 02:03 | 55 427 357 B (~52,9 MB) |
| 2026-10-17 | 02:03 | 55 427 496 B (~52,9 MB) |
| 2026-10-18 | 02:09 | 55 427 571 B (~52,9 MB) |
| 2026-10-19 | 02:08 | 55 427 571 B (~52,9 MB) |
| 2026-10-20 | 02:08 | 55 442 326 B (~52,9 MB) |
| 2026-10-21 | 02:10 | 55 451 749 B (~52,9 MB) |
| 2026-10-22 | 02:09 | 55 467 958 B (~52,9 MB) |

En fil per dag, inga luckor. `gzip -t` OK på samtliga testade filer (giltig gzip-ström).

## 🔴 Kritiskt fynd — dumpen är ofullständig, varje dag, sedan åtminstone april

`mysqldump` kraschar varje natt på tabellen `tvattlinje_settings_broken_backup`:

```
mysqldump: Error 1194: Table 'tvattlinje_settings_broken_backup' is marked as
crashed and should be repaired when dumping table
`tvattlinje_settings_broken_backup` at row: 8799927
```

Tabellen är markerad **crashed** på lagringsmotor-nivå (MyISAM-korruption). Eftersom
scriptet kör `mysqldump | gzip` blir `.sql.gz`-filen fortfarande en giltig gzip-ström
(`gzip -t` = OK), men SQL-dumpen **innanför** avbryts mitt i den tabellen:

- Ingen `-- Dump completed`-rad i någon testad fil (senast kontrollerat: 2026-10-22-filen).
- **Alla tabeller som sorteras alfabetiskt efter `tvattlinje_settings_broken_backup`
  saknas helt** i varje backup sedan problemet uppstod (loggen visar felet redan i april).
- Av `backup.log`:s totalt 1094 rader är endast 78 körningar fullständiga ("Backup
  skapad"-rad skriven); 115 av körningarna slutar i rena felmeddelanden utan att nå
  retentions-/loggningssteget.

**Åtgärd krävs av någon med skrivbehörighet till databasen** — antingen:
- `REPAIR TABLE tvattlinje_settings_broken_backup`, eller
- bedöma om tabellen (namnet antyder en redan övergiven kopia) bara kan droppas.

Ingen av dessa åtgärder har utförts — kräver DB-skrivbehörighet som denna
läskontroll uttryckligen inte har/får använda.

## Retention fungerar inte i praktiken

Scriptet är satt till 30 dagars retention, men äldsta kvarvarande fil är från
**2026-05-31** (~144 dagar gammal, kontrollerat 2026-10-22). `find -mtime +30 -delete`
körs tekniskt sett (loggen skriver "Rensning klar" oavsett om något faktiskt togs bort),
men gamla filer rensas inte bort. Disk: 86 GB ledigt av 100 GB (15 % använt) — inte
akut utrymmesbrist ännu, men bör åtgärdas.

## Kod/uploads

Backas **inte** upp automatiskt. Enda spåren är manuella engångssnapshots i
`/var/www/mauserdb-backups/` (daterade 2026-03-06, >7 månader gamla vid kontrolltillfället)
och några manuellt namngivna mappkopior (`mauserdb-prod260603` m.fl.) — ingen cron
kopplad till någon av dem.

## Sammanfattning av brister

1. **Databasbackupen har saknat delar av databasen i flera månader** (allt efter
   `tvattlinje_settings_broken_backup` alfabetiskt) — det allvarligaste fyndet.
2. Retention tar inte bort gamla filer (30 dagar avsett, 144+ dagar i praktiken).
3. Ingen automatisk backup av kod/uploads, bara manuella engångskopior.

Inget av ovanstående är åtgärdat av denna kontroll — kräver DB-skrivbehörighet
respektive beslut om vad som ska prioriteras.
