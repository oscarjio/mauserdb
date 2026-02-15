# Bonussystem - Implementeringsguide

## Översikt

Bonussystemet beräknar prestationsbaserad bonus för operatörer på rebotling-linjen baserat på tre KPI:er: **Effektivitet**, **Produktivitet** och **Kvalitet**. Beräkningen sker automatiskt vid varje IBC-cykel via PLC-data.

---

## 1. Databas-migration

Kör migration `003_bonus_admin_tables.sql` för att skapa de nödvändiga tabellerna:

```bash
mysql -u aiab -p -P 33061 mauserdb < migrations/003_bonus_admin_tables.sql
```

### Tabeller som skapas:
| Tabell | Syfte |
|--------|-------|
| `bonus_config` | Systemkonfiguration (viktningar, mål, tier-multiplikatorer) |
| `bonus_periods` | Periodhantering (öppen/låst/godkänd/betald) |
| `bonus_adjustments` | Manuella justeringar per operatör |
| `bonus_audit_log` | Ändringslogg för alla admin-åtgärder |
| `rebotling_products` | Produktkonfiguration med mål |

### Kolumner som läggs till i `rebotling_ibc`:
- `bonus_approved` (TINYINT) - Om bonusen är godkänd
- `bonus_approved_by` (VARCHAR) - Vem som godkände
- `bonus_approved_at` (DATETIME) - När den godkändes
- `bonus_paid` (TINYINT) - Om bonusen är utbetald
- `bonus_paid_at` (DATETIME) - När den betalades ut

---

## 2. Hur bonusberäkningen fungerar

### Automatisk beräkning (redan implementerat)

Bonusen beräknas **automatiskt** vid varje cykel i `noreko-plcbackend/Rebotling.php` → `handleCycle()`:

1. PLC:n (Mitsubishi FX5) skickar webhook vid varje avslutad cykel
2. `Rebotling.php` läser register D4000-D4009 via Modbus TCP
3. `BonusCalculator::calculateAdvancedKPIs()` beräknar KPI:er
4. Resultatet sparas i `rebotling_ibc` med kolumnerna `effektivitet`, `produktivitet`, `kvalitet`, `bonus_poang`

### PLC-register (D4000-D4009)

| Register | Fält | Beskrivning |
|----------|------|-------------|
| D4000 | op1 | Operatör 1 (Tvättplats) - anställningsnummer |
| D4001 | op2 | Operatör 2 (Kontrollstation) - anställningsnummer |
| D4002 | op3 | Operatör 3 (Truckförare) - anställningsnummer |
| D4003 | produkt | Produkt-ID (1=FoodGrade, 4=NonUN, 5=Tvättade) |
| D4004 | ibc_ok | Antal godkända IBC |
| D4005 | ibc_ej_ok | Antal kasserade IBC |
| D4006 | bur_ej_ok | Antal defekta burar |
| D4007 | runtime_plc | Körtid i minuter |
| D4008 | rasttime | Paustid i minuter |
| D4009 | lopnummer | Löpnummer (counter) |

### Bonusformel

```
1. Effektivitet = (ibc_ok / (ibc_ok + ibc_ej_ok)) × 100

2. Produktivitet = (ibc_ok × 60) / runtime_minuter
   Normaliserad = min((produktivitet / mål) × 100, 120)

   Mål per produkt:
   - FoodGrade: 12 IBC/h
   - NonUN: 20 IBC/h
   - Tvättade: 15 IBC/h

3. Kvalitet = ((ibc_ok - bur_ej_ok) / ibc_ok) × 100

4. BasBonus = (Eff × vikt_eff) + (Prod_norm × vikt_prod) + (Kval × vikt_qual)

   Viktning per produkt:
   - FoodGrade: 30% / 30% / 40%
   - NonUN: 35% / 45% / 20%
   - Tvättade: 40% / 35% / 25%

5. TierMultiplikator:
   95+: ×2.0 (Outstanding)
   90-94: ×1.5 (Excellent)
   80-89: ×1.25 (God prestanda)
   70-79: ×1.0 (Basbonus)
   <70: ×0.75 (Under förväntan)

6. FinalBonus = min(BasBonus × TierMult, 200)
```

---

## 3. Vad du behöver göra i PLC:n

### Redan klart (ingen åtgärd krävs):
- Bonusberäkning vid cykel ✅
- KPI-lagring i databasen ✅
- API:er för dashboard och admin ✅
- Frontend-sidor ✅

### Kontrollera att PLC:n skickar korrekt data:

**Kritiskt:** Operatörs-ID:n (D4000-D4002) måste vara satta korrekt. Om en operatör inte sätts i PLC:n (=0) räknas den inte in i bonusen.

1. **Operatörs-ID**: Se till att operatörernas anställningsnummer skrivs till D4000-D4002 när ett skift börjar
2. **Produkt-ID (D4003)**: Måste vara 1, 4 eller 5 - andra värden ger default-viktning
3. **Runtime (D4007)**: Måste vara > 0 minuter för att undvika division med noll
4. **IBC-counters**: D4004-D4006 bör vara kumulativa per skift

### Om operatörs-ID inte sätts från PLC:n

Om operatörer loggar in via webbgränssnittet istället för PLC kan du:
1. Skapa ett separat register i PLC som webappen skriver till
2. Eller hantera operatörstilldelning via skiftrapporten

---

## 4. Åtkomst via webbgränssnittet

### Meny (admin-only, under Rebotling):
- **Bonus** → Dashboard med ranking, KPI:er, operatörssökning
- **Bonus Admin** → Konfiguration, viktningar, godkänna perioder, export

### API-endpoints:

**Bonus Dashboard (BonusController):**
```
GET /noreko-backend/api.php?action=bonus&run=summary          → Dagens sammanfattning
GET /noreko-backend/api.php?action=bonus&run=ranking&period=week → Top 10 ranking
GET /noreko-backend/api.php?action=bonus&run=operator&id=123   → Operatörsstatistik
GET /noreko-backend/api.php?action=bonus&run=team&period=week  → Skiftöversikt
GET /noreko-backend/api.php?action=bonus&run=kpis&id=123       → KPI-detaljer (Chart.js)
GET /noreko-backend/api.php?action=bonus&run=history&id=123    → Operatörshistorik
```

**Bonus Admin (BonusAdminController):**
```
GET  /noreko-backend/api.php?action=bonusadmin&run=get_config     → Hämta config
POST /noreko-backend/api.php?action=bonusadmin&run=update_weights → Uppdatera viktningar
POST /noreko-backend/api.php?action=bonusadmin&run=set_targets    → Sätt produktivitetsmål
GET  /noreko-backend/api.php?action=bonusadmin&run=get_periods    → Hämta perioder
POST /noreko-backend/api.php?action=bonusadmin&run=approve_bonuses → Godkänn bonusar
GET  /noreko-backend/api.php?action=bonusadmin&run=export_report  → Exportera CSV
GET  /noreko-backend/api.php?action=bonusadmin&run=get_stats      → Systemstatistik
```

---

## 5. Steg-för-steg checklista

- [ ] Kör databas-migration `003_bonus_admin_tables.sql`
- [ ] Verifiera att PLC:n skickar operatörs-ID i D4000-D4002
- [ ] Verifiera att produkt-ID i D4003 är korrekt (1, 4, eller 5)
- [ ] Bygg Angular-frontend: `cd noreko-frontend && ng build`
- [ ] Deploya frontend till webbservern
- [ ] Logga in som admin och navigera till Rebotling → Bonus
- [ ] Kontrollera att KPI-kort och ranking visar data
- [ ] Gå till Rebotling → Bonus Admin
- [ ] Verifiera att konfiguration kan läsas och uppdateras
- [ ] Testa CSV-export för en period
- [ ] Testa godkännande av bonusperiod

---

## 6. Felsökning

### Ingen data visas på dashboard
- Kontrollera att `rebotling_ibc` har rader med `bonus_poang IS NOT NULL`
- Kör: `SELECT COUNT(*), AVG(bonus_poang) FROM rebotling_ibc WHERE bonus_poang IS NOT NULL`

### Bonus beräknas inte vid cykel
- Kontrollera PLC-anslutningen: `Rebotling.php` loggar fel till error_log
- Se `noreko-plcbackend/Rebotling.php` → `handleCycle()` rad 50-55

### Admin-API ger "Unauthorized"
- `BonusAdminController::isAdmin()` returnerar `true` som default
- Implementera riktig auth-check om så önskas

### Viktningar summerar inte till 1.0
- Admin-gränssnittet validerar detta och visar felmeddelande
- Backend validerar också och returnerar fel om summan avviker > 0.001
