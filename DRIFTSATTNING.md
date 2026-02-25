# Information inför driftsättning – Rebotling
**Datum:** 2026-02-26
**System:** Shellypuck + FX5 PLC (Modbus TCP) + PHP backend + Angular frontend

---

## 1. Registermappning att verifiera med PLC-programmeraren

Koden läser **D4000–D4009** i ett enda Modbus-anrop vid varje cykel och vid skiftrapport.

| Register | Variabel i kod | Innehåll |
|----------|---------------|----------|
| D4000 | op1 | Operatör Tvättplats (operator_id) |
| D4001 | op2 | Operatör Kontrollstation (operator_id) |
| D4002 | op3 | Truckförare (operator_id) |
| D4003 | produkt | Produkt-ID (1=FoodGrade, 4=NonUN, 5=Tvätta) |
| D4004 | ibc_ok | Antal godkända IBC |
| D4005 | ibc_ej_ok | Antal underkända IBC |
| D4006 | bur_ej_ok | Antal underkända burar |
| D4007 | runtime_plc | Drifttid i **minuter** (exkl. rast – PLC räknar bara produktionstid) |
| D4008 | rasttime | Rasttid i **minuter** |
| D4009 | lopnummer | Högsta löpnummer på producerad IBC (loggas, visas ej) |

**Kommandoregister:**

| Register | Användning |
|----------|-----------|
| D4010 | Data: nytt löpnummer (används vid kommando 2) |
| D4011–D4013 | Reserverade |
| D4014 | **Återföring från webb → PLC** – sätts till 0 när skiftrapport är mottagen |
| D4015 | Kommando från PLC (1 = skicka skiftrapport, 2 = ändra löpnummer) |

> **Viktigt att bekräfta:** Är D4007 (runtime) kumulativ sedan skiftstart, eller nollställs den per cykel? Koden behandlar den som kumulativ.

---

## 2. Shellypuck-ingångar och webhook-URLer

Shellypucken har 4 ingångar (Y334–Y337). Dessa webhooks måste vara konfigurerade:

| Ingång | Signal | URL som Shelly kallar |
|--------|--------|-----------------------|
| Y334 | IBC räknare (puls per IBC) | `http://SERVER/noreko-plcbackend/v1.php?type=cycle&line=rebotling&count=RÄKNARE` |
| Y335 | Anläggning i drift (hög=kör, låg=stopp) | `http://SERVER/noreko-plcbackend/v1.php?type=running&line=rebotling&running=1&high=H&low=L` (hög) och `running=0` (låg) |
| Y336 | Anläggning i paus (hög=rast, låg=arbetar) | `http://SERVER/noreko-plcbackend/v1.php?type=rast&line=rebotling&rast=1` (hög) och `rast=0` (låg) |
| Y337 | Läs kommando (puls vid knapptryck) | `http://SERVER/noreko-plcbackend/v1.php?type=command&line=rebotling` |

> **`count`-parametern (Y334):** Ska vara Shellys interna räknare som ökar med 1 per IBC. Koden använder detta för att räkna ut hur många IBC som producerats idag (ifall webbanropet hamnar ur synk).

> **`high` och `low` (Y335):** Dessa lagras men används inte i beräkningar. Kan sättas till 0 om de inte finns naturligt i Shellys webhook.

---

## 3. Flöde steg för steg

### Varje IBC (Y334 → type=cycle)
1. Shelly triggar webhook med aktuell räknare
2. PHP läser D4000–D4009 från PLC via Modbus TCP
3. Beräknar ibc_count (hur många idag) och produktion_procent
4. Sparar rad i `rebotling_ibc`

### Start/stopp (Y335 → type=running)
1. Shelly triggar vid flankändring
2. PHP loggar running=1 eller running=0 i `rebotling_onoff`
3. Skiftraknare räknas upp automatiskt vid **första start varje dag**

### Rast (Y336 → type=rast)
1. Shelly triggar vid flankändring (hög=rast börjar, låg=rast slutar)
2. PHP sparar event i `rebotling_runtime` (undviker duplikat om samma status redan finns)
3. Live-sidan visar gul banner + total rasttid idag

### Skiftrapport (Y337 → type=command, D4015=1)
1. Operatör trycker på knapp → Y337 hög
2. Shelly kallar `type=command`
3. PHP läser D4015 via Modbus → ser att det är 1 (skiftrapport)
4. PHP läser D4000–D4009 (shift-totaler) och sparar rad i `rebotling_skiftrapport`
5. PHP skriver **D4014 = 0** som kvittens till PLC
6. Om något går fel → D4014 skrivs aldrig → PLC kan larma

### Ändra löpnummer (Y337 → type=command, D4015=2)
1. Operatör trycker på "ändra löpnummer"-knapp → Y337 hög
2. PLC skriver nytt nummer i D4010 och D4015=2
3. PHP läser D4015=2, sedan D4010
4. Uppdaterar `lopnummer` på senaste raden i `rebotling_ibc`

---

## 4. Modbus TCP – att kontrollera

- **IP-adress PLC:** `192.168.0.200` (hårdkodat i koden – rätt IP?)
- **Port:** 502 (standard Modbus TCP – stämmer det för FX5?)
- **Timeout:** PHPModbus default – om PLC inte svarar tar anropet `timeout`-tid innan det kraschar
- **Enhetsadress (Unit ID):** `0` – stämmer det för FX5, eller ska det vara `1` eller `255`?

> **Kritiskt:** Om Modbus-anropet vid cykel misslyckas sparas **nollvärden** i databasen (befintlig fallback-logik). Överväg om detta är önskat beteende eller om raden ska hoppas över vid kommunikationsfel.

---

## 5. Databas – att köra vid driftsättning

Migrationer måste köras i ordning på produktionsdatabasen:

```
001_add_modbus_fields_to_rebotling_ibc.sql
002_add_fx5_d4000_fields.sql
003_bonus_admin_tables.sql
004_add_performance_indexes.sql
005_bcrypt_password_column.sql   ← lägger till kolumn men vi använder ej bcrypt
006_add_operator_id_to_users.sql
007_add_plc_fields_to_rebotling_skiftrapport.sql  ← NYA kolumner op1/op2/op3/drifttid/rasttime/lopnummer
```

> Migration 007 kan också köras live – koden kontrollerar om kolumnerna finns och lägger till dem automatiskt vid första skiftrapport om de saknas.

---

## 6. Kända begränsningar / saker att ha koll på

### Skiftraknare
Skiftraknaren ökas automatiskt vid **första running=1 varje dag**. Om linjen startas och stoppas flera gånger under ett dygn skapas **ett skift per dag**, inte per start/stopp. Stämmer det med hur ni vill räkna skift?

### Produktvärden
Koden validerar att produkt-ID är 1, 4 eller 5. Om PLC skickar annat värde loggas en varning men raden sparas ändå.

| Värde | Produkt |
|-------|---------|
| 1 | FoodGrade |
| 4 | NonUN |
| 5 | Tvätta färdiga IBC |

Om fler produkter ska läggas till behöver tabellen `rebotling_products` uppdateras.

### Shellys räknare (s_count) och PLC-omstart
Om Shellys räknare nollställs (PLC-omstart, ny firmware etc.) tar koden hand om det: den faller tillbaka på `dbcount + 1` som ibc_count. Inga dubbletter skapas.

### Återföring D4014
D4014 sätts bara till 0 om **hela flödet lyckas** (Modbus-läsning + DB-insert). Om Modbus-anropet för att skriva D4014 misslyckas efter lyckad DB-insert – data är sparad men PLC ser det som ett misslyckande. PLC bör ha en rimlig timeout (t.ex. 10 sekunder) innan den larmar.

### display_errors i v1.php
`ini_set('display_errors', 1)` är på i `v1.php`. Bra för felsökning nu, men **bör stängas av** när systemet är i drift (PHP-felmeddelanden syns annars i svaret till Shelly).

---

## 7. Checklista inför testkörning

**Nätverks/anslutning:**
- [ ] Servern kan nå PLC på `192.168.0.200:502` (testa med `nc -zv 192.168.0.200 502`)
- [ ] Shellypucken kan nå servern (testa manuellt med curl eller webbläsare)
- [ ] Databasen på extern server är nåbar

**Register:**
- [ ] D4000–D4009 mappning stämmer mot PLC-program
- [ ] D4007 (runtime) är i minuter och exkluderar rast
- [ ] D4014 är skrivbar från webben (inte skyddad i PLC)
- [ ] D4015 sätts korrekt av PLC vid skiftrapport-kommando
- [ ] Produkt-ID (D4003) matchar kodvärden 1/4/5

**Webhooks – testa manuellt i webbläsaren eller med curl:**
```bash
# Testa cycle-webhook (count=1)
curl "http://SERVER/noreko-plcbackend/v1.php?type=cycle&line=rebotling&count=1"

# Testa running-webhook
curl "http://SERVER/noreko-plcbackend/v1.php?type=running&line=rebotling&running=1&high=0&low=0"

# Testa rast-webhook
curl "http://SERVER/noreko-plcbackend/v1.php?type=rast&line=rebotling&rast=1"

# Testa command-webhook (kräver att PLC är ansluten och D4015 är satt)
curl "http://SERVER/noreko-plcbackend/v1.php?type=command&line=rebotling"
```

**Förväntat svar:** `{"status":"success"}` – om du ser `{"error":"..."}` finns det ett problem att felsöka.

**Databas:**
- [ ] Migration 007 körts (eller kör den manuellt)
- [ ] Tabellerna `rebotling_ibc`, `rebotling_onoff`, `rebotling_runtime`, `rebotling_skiftrapport` finns
- [ ] Live-sidan på `/rebotling-live` visar data efter första webhook

**Första riktiga testet:**
1. Kör en manuell cycle-webhook → kolla att rad skapas i `rebotling_ibc`
2. Starta linjen → kolla att `rebotling_onoff` får running=1
3. Trigga en rast → kolla gul banner på live-sidan
4. Trigga skiftrapport → kolla att rad skapas i `rebotling_skiftrapport` **och** att D4014 kvitteras

---

## 8. Buggar fixade inför driftsättning

- `$res` null-check i handleCycle – krasch om ingen rad med ibc_count=1 finns i DB
- `$_GET['count']` castas nu explicit till `int` (förhindrar eventuella typkonverteringsproblem)
- `handleSkiftrapport` läser rätt register (D4000–D4009) istället för gamla D210–D216
- `handleCommand` (Y337) implementerat med D4015-routing
- Återföring D4014=0 skrivs efter lyckad skiftrapport
- Kommentar i WebhookReceiver korrigerad (GET, inte POST)
- Lösenord återställt till sha1(md5()) för kompatibilitet med befintlig produktion
