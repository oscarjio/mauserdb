# 🕐 Noreko Rast-registrering System

## Översikt

Detta system registrerar automatiskt raster för tvättlinje och rebotling genom att övervaka en ingång på Shelly-pucken.

**Koncept:**
- När ingången är **HÖG (1)** = Personal är på rast
- När ingången är **LÅG (0)** = Personal arbetar
- All data sparas i databasen för analys

---

## 📁 Filer som skapats

### Backend (PHP)
- **`noreko-plcbackend/TvattLinje.php`** - Uppdaterad med `handleRast()` metod
- **`noreko-plcbackend/Rebotling.php`** - Uppdaterad med `handleRast()` metod
- **`noreko-plcbackend/WebhookProcessor.php`** - Uppdaterad med 'rast' routing

### Shelly Scripts
- **`shellyscript/tvattlinje/rast.txt`**
  - Övervakar rastingång för tvättlinje
  
- **`shellyscript/rebotling/rast.txt`**
  - Övervakar rastingång för rebotling

### Databas
- **`deploy-scripts/database-migration-runtime.sql`**
  - SQL för att skapa tabeller

---

## 🚀 Installation

### 1. Skapa databasTabeller

Logga in på MySQL och kör migrations-scriptet:

```bash
mysql -u aiab -p mauserdb < deploy-scripts/database-migration-runtime.sql
```

Eller via phpMyAdmin/Adminer:
1. Öppna `database-migration-runtime.sql`
2. Kopiera innehållet
3. Kör i SQL-fönstret

**Tabeller som skapas:**
- `tvattlinje_runtime` - Lagrar rast-status för tvättlinje
- `rebotling_runtime` - Lagrar rast-status för rebotling

### 2. Konfigurera Shelly-pucken

#### A. För Tvättlinje

1. Logga in på Shelly-pucken för tvättlinje (i webbläsaren)
2. Gå till **Scripts** i menyn
3. Skapa ett nytt script
4. Kopiera innehållet från `shellyscript/tvattlinje/rast.txt`
5. Klistra in och spara
6. **Viktigt:** Ändra `rastInputPin` i CONFIG till rätt ingång (troligen 3)
7. Ändra `webhookUrl` IP-adress om servern inte är 192.168.0.100
8. Aktivera scriptet

#### B. För Rebotling

Samma steg som ovan men använd `shellyscript/rebotling/rast.txt`

---

## 🔧 Konfiguration

### Ändra Ingångsnummer

Öppna Shelly-scriptet och ändra i CONFIG-sektionen:

```javascript
let CONFIG = {
  rastInputPin: 3,  // <-- ÄNDRA DETTA till rätt pin-nummer
  // ... resten av config
};
```

### Ändra Debounce-tid

Om du får dubbel-registreringar, öka debounce:

```javascript
let CONFIG = {
  debounceTime: 500,  // <-- Öka till 1000 eller 2000 om problem
  // ...
};
```

### Ändra Backend URL

Om servern har annan IP:

```javascript
let CONFIG = {
  webhookUrl: "http://DIN-SERVER-IP/api/api.php?action=runtime&line=tvattlinje",
  // ...
};
```

---

## 📊 API Endpoints

### 1. Registrera rast (från Shelly)

**Används automatiskt av Shelly-scripten**

```
GET /api/v1.php?type=rast&line=tvattlinje&rast=1
GET /api/v1.php?type=rast&line=rebotling&rast=0
```

**Parameters:**
- `type=rast` - Webhook typ
- `line` - tvattlinje eller rebotling
- `rast` - 0 (arbetar) eller 1 (på rast)

**Response:**
```json
{
  "status": "success"
}
```

### 2. Hämta dagens rasttid

För att hämta rasttid-statistik, lägg till endpoint i `noreko-backend` (webbsidans API) som läser från `*_runtime` tabellerna.

Exempel implementation i `TvattlinjeController.php`:

```php
public function getBreakTime() {
    $stmt = $this->pdo->prepare('
        SELECT datum, rast_status
        FROM tvattlinje_runtime 
        WHERE DATE(datum) = CURDATE()
        ORDER BY datum ASC
    ');
    $stmt->execute();
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Beräkna total rasttid (använd samma logik som runtime-beräkning)
    // ... implementation ...
}
```

---

## 🧪 Testning

### 1. Testa Shelly-scriptet

I Shelly's script-konsol bör du se:

```
╔════════════════════════════════════════╗
║  NOREKO - TVÄTTLINJE RAST-ÖVERVAKARE  ║
╔════════════════════════════════════════╗
  Ingång:  Pin 3
  Backend: http://192.168.0.100/api/...
  Debounce: 500 ms
════════════════════════════════════════
Laddade senaste rast-status från KVS: false
📊 Initial status: ARBETAR
✅ Script aktivt och övervakar raster!
```

### 2. Testa Status-ändring

Koppla ingången hög/låg. Du bör se:

```
═══════════════════════════════════════
🔄 RAST-STATUS ÄNDRAD!
   Tidigare: ARBETAR
   Ny:       PÅ RAST
   Tid:      2026-02-04T10:30:00.000Z
═══════════════════════════════════════
Status sparad i KVS: true
✓ Webhook skickad - Rast: PÅ
  Svar från server: {"success":true, ...}
```

### 3. Testa API direkt

Via webbläsare eller curl:

```bash
# Registrera rast
curl "http://192.168.0.100/api/api.php?action=runtime&line=tvattlinje&rast=1"

# Hämta dagens tid
curl "http://192.168.0.100/api/api.php?action=runtime&run=today&line=tvattlinje"
```

### 4. Kontrollera databas

```sql
-- Se senaste registreringarna
SELECT * FROM tvattlinje_runtime ORDER BY datum DESC LIMIT 10;
SELECT * FROM rebotling_runtime ORDER BY datum DESC LIMIT 10;

-- Se dagens raster
SELECT * FROM tvattlinje_runtime 
WHERE DATE(datum) = CURDATE() 
ORDER BY datum ASC;
```

---

## 🎯 Användningsexempel

### Visa rasttid i Frontend

Lägg till en metod i `TvattlinjeController.php` (noreko-backend) för att hämta rasttid:

```php
private function getBreakTime() {
    $stmt = $this->pdo->prepare('
        SELECT datum, rast_status
        FROM tvattlinje_runtime 
        WHERE DATE(datum) = CURDATE()
        ORDER BY datum ASC
    ');
    $stmt->execute();
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalBreakMinutes = 0;
    $lastBreakStart = null;
    $now = new DateTime();
    
    foreach ($entries as $entry) {
        $entryTime = new DateTime($entry['datum']);
        $isOnBreak = (bool)$entry['rast_status'];
        
        if ($isOnBreak && $lastBreakStart === null) {
            $lastBreakStart = $entryTime;
        } elseif (!$isOnBreak && $lastBreakStart !== null) {
            $diff = $lastBreakStart->diff($entryTime);
            $periodMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
            $totalBreakMinutes += $periodMinutes;
            $lastBreakStart = null;
        }
    }
    
    // Om rast pågår, räkna till nu
    if ($lastBreakStart !== null) {
        $lastEntryTime = new DateTime($entries[count($entries) - 1]['datum']);
        $diff = $lastBreakStart->diff($now);
        $periodMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + ($diff->s / 60);
        $totalBreakMinutes += $periodMinutes;
    }
    
    return [
        'total_break_minutes' => round($totalBreakMinutes, 2),
        'total_break_hours' => round($totalBreakMinutes / 60, 2)
    ];
}
```

Sen kan du anropa det från Angular-frontend:

```typescript
// I TvattlinjeService
getBreakTimeToday() {
  return this.http.get('/api/api.php?action=tvattlinje&run=breaktime');
}
```

### Beräkna effektiv arbetstid

```typescript
const totalWorkHours = 8; // 8 timmars arbetsdag
const breakHours = data.data.total_break_hours;
const effectiveWorkHours = totalWorkHours - breakHours;
```

---

## 🔍 Felsökning

### Problem: Ingen data i databasen

**Kontroller:**
1. Kör Shelly-scriptet? (Se i Shelly-konsolen)
2. Rätt webhook URL? (Kolla CONFIG i scriptet)
3. Servern nåbar från Shelly? (Testa URL i webbläsare)
4. Tabellerna skapade? (`SHOW TABLES LIKE '%runtime%'`)

### Problem: Dubbel-registreringar

**Lösning:** Öka debounceTime i CONFIG:

```javascript
debounceTime: 1000,  // Öka från 500 till 1000
```

### Problem: Status inte uppdateras

**Kontroller:**
1. Kolla Shelly-logg för fel
2. Testa webhook URL manuellt i webbläsare
3. Kontrollera att rätt ingång används (rastInputPin)

### Problem: Script kraschar vid omstart

Scriptet sparar status i Shelly KVS (Key-Value Store), så det överlever omstarter.

---

## 💡 Tips & Tricks

### 1. Övervaka i realtid

Använd polling i frontend för att visa live-status:

```typescript
setInterval(() => {
  this.updateBreakTime();
}, 30000); // Uppdatera var 30:e sekund
```

### 2. Visa rast-historik

Skapa en graf som visar raster under dagen:

```sql
SELECT 
    DATE_FORMAT(datum, '%H:%i') as tid,
    rast_status
FROM tvattlinje_runtime 
WHERE DATE(datum) = CURDATE()
ORDER BY datum ASC;
```

### 3. Alert vid långa raster

I backend, lägg till check:

```php
if ($totalBreakMinutes > 60) {
    // Skicka notis eller alert
}
```

---

## 📈 Framtida Förbättringar

Möjliga tillägg:
- Dashboard för rast-visualisering
- Jämförelse mellan linjer
- Automatisk rapportgenerering
- Push-notiser vid avvikelser
- Integration med HR-system

---

## ✅ Checklista

- [ ] Databas-tabeller skapade
- [ ] RuntimeController.php på servern
- [ ] Shelly-script för tvättlinje uppladdat
- [ ] Shelly-script för rebotling uppladdat
- [ ] CONFIG anpassad (PIN, URL)
- [ ] Scripts aktiverade i Shelly
- [ ] Testat manuellt med ingång
- [ ] API-anrop fungerar
- [ ] Data sparas i databas

---

**Lycka till! 🚀**

Vid frågor, kolla loggarna i:
- Shelly-konsolen
- Apache error log: `/var/log/apache2/error.log`
- MySQL: `SELECT * FROM tvattlinje_runtime ORDER BY datum DESC LIMIT 5;`
