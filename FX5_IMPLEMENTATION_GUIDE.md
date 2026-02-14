# 🏭 Mitsubishi FX5 PLC - ModbusTCP Implementation Guide

## 📡 PLC Specifikation

**PLC Modell**: Mitsubishi FX5  
**Protokoll**: ModbusTCP  
**IP-adress**: 192.168.0.200  
**Port**: 502 (standard ModbusTCP)

---

## 📊 Register-mappning (D4000-D4009)

### EXAKTA REGISTER att läsa per cykel:

| D-Register | ModbusTCP Adress | Beskrivning | Datatyp | DB Kolumn |
|------------|------------------|-------------|---------|-----------|
| **D4000** | 4000 | Operatör 1 (Tvättplats) | INT16 | `op1` |
| **D4001** | 4001 | Operatör 2 (Kontrollstation) | INT16 | `op2` |
| **D4002** | 4002 | Operatör 3 (Truckförare) | INT16 | `op3` |
| **D4003** | 4003 | Produkt (1=FoodGrade, 4=NonUN, 5=Färdiga) | INT16 | `produkt` |
| **D4004** | 4004 | IBC_ok (antal godkända) | INT16 | `ibc_ok` |
| **D4005** | 4005 | IBC_ej_ok (antal kasserade) | INT16 | `ibc_ej_ok` |
| **D4006** | 4006 | Bur_ej_ok (antal defekta burar) | INT16 | `bur_ej_ok` |
| **D4007** | 4007 | Runtime (körtid) | INT16 | `runtime_plc` |
| **D4008** | 4008 | Rasttime (paustid) | INT16 | `rasttime` |
| **D4009** | 4009 | Högsta löpnummer (counter) | INT16 | `lopnummer` |

**Total: 10 register = 20 bytes data**

---

## 🔧 Mitsubishi FX5 ModbusTCP Adressering

### Adress-mapping:
Mitsubishi FX5 använder **direkt mapping** för D-register i ModbusTCP:
```
D0 → Holding Register 0
D1 → Holding Register 1
...
D4000 → Holding Register 4000
D4009 → Holding Register 4009
```

**Bekräftat från befintlig kod:**
```php
// Befintlig kod läser D200 med adress 200:
$modbus->readMultipleRegisters(0, 200, 7);  // D200-D206
```

**Därför:**
```php
// För D4000-D4009 använder vi adress 4000:
$modbus->readMultipleRegisters(0, 4000, 10);  // D4000-D4009
```

### PHPModbus library syntax:
```php
readMultipleRegisters(
    int $unitId,        // 0 = default unit
    int $startAddress,  // 4000 för D4000
    int $count          // 10 register
)
```

---

## 💻 PHP Implementation

### 1. Läs alla 10 register i ett anrop

```php
public function handleCycle(array $data): void {
    // Validera trigger
    if (!isset($_GET['count'])) {
        throw new InvalidArgumentException('Missing count parameter');
    }

    // === MODBUS TCP - LÄS D4000-D4009 ===
    try {
        // Anslut till FX5 PLC
        $this->modbus = new ModbusMaster("192.168.0.200", "TCP");
        
        // Läs alla 10 register i ETT anrop (D4000-D4009)
        $raw_data = $this->modbus->readMultipleRegisters(0, 4000, 10);
        
        // Konvertera 8-bit bytes till 16-bit värden
        $plc_data = $this->convert8to16bit($raw_data);
        
        // Extrahera värden (array index 0-9)
        $op1          = $plc_data[0];  // D4000
        $op2          = $plc_data[1];  // D4001
        $op3          = $plc_data[2];  // D4002
        $produkt      = $plc_data[3];  // D4003
        $ibc_ok       = $plc_data[4];  // D4004
        $ibc_ej_ok    = $plc_data[5];  // D4005
        $bur_ej_ok    = $plc_data[6];  // D4006
        $runtime_plc  = $plc_data[7];  // D4007
        $rasttime     = $plc_data[8];  // D4008
        $lopnummer    = $plc_data[9];  // D4009
        
        // Log för debugging
        error_log("PLC Data - Op1:$op1 Op2:$op2 Op3:$op3 Produkt:$produkt IBC_OK:$ibc_ok");
        
    } catch (Exception $e) {
        error_log("ModbusTCP Error: " . $e->getMessage());
        // Kasta vidare felet - vi vill INTE spara ofullständig data
        throw new RuntimeException("Failed to read PLC data: " . $e->getMessage());
    }

    // === BERÄKNA IBC_COUNT (befintlig logik) ===
    $stmt = $this->db->prepare("
        SELECT COUNT(*) 
        FROM rebotling_ibc 
        WHERE DATE(datum) = CURDATE()
    ");
    $stmt->execute();
    $dbcount = (int)$stmt->fetchColumn();
    
    if($dbcount < 1) {
        $ibc_count = 1;
    } else {
        $stmt = $this->db->prepare("
            SELECT lopnummer 
            FROM rebotling_ibc 
            WHERE DATE(datum) = CURDATE()
            ORDER BY datum ASC
            LIMIT 1
        ");
        $stmt->execute();
        $first_lopnummer = $stmt->fetchColumn();
        
        if ($first_lopnummer && $lopnummer >= $first_lopnummer) {
            $ibc_count = ($lopnummer - $first_lopnummer) + 1;
        } else {
            $ibc_count = $dbcount + 1;
        }
    }

    // === BERÄKNA SKIFTRAKNARE (befintlig logik) ===
    $stmt = $this->db->prepare('
        SELECT skiftraknare
        FROM rebotling_ibc 
        WHERE skiftraknare IS NOT NULL
        ORDER BY datum DESC 
        LIMIT 1
    ');
    $stmt->execute();
    $lastShift = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Om första IBC idag, öka skifträknaren
    if ($dbcount == 0) {
        $skiftraknare = $lastShift ? (int)$lastShift['skiftraknare'] + 1 : 1;
    } else {
        $skiftraknare = $lastShift ? (int)$lastShift['skiftraknare'] : 1;
    }

    // === BERÄKNA KPI:ER ===
    $kpis = $this->calculateKPIs([
        'ibc_ok' => $ibc_ok,
        'ibc_ej_ok' => $ibc_ej_ok,
        'bur_ej_ok' => $bur_ej_ok,
        'runtime_plc' => $runtime_plc,
        'rasttime' => $rasttime
    ]);

    // === SPARA TILL DATABAS ===
    $stmt = $this->db->prepare('
        INSERT INTO rebotling_ibc (
            datum, ibc_count, skiftraknare, lopnummer,
            op1, op2, op3, produkt,
            ibc_ok, ibc_ej_ok, bur_ej_ok,
            runtime_plc, rasttime,
            effektivitet, produktivitet, kvalitet, bonus_poang
        )
        VALUES (
            NOW(), :ibc_count, :skiftraknare, :lopnummer,
            :op1, :op2, :op3, :produkt,
            :ibc_ok, :ibc_ej_ok, :bur_ej_ok,
            :runtime_plc, :rasttime,
            :effektivitet, :produktivitet, :kvalitet, :bonus_poang
        )
    ');
    
    $stmt->execute([
        'ibc_count' => $ibc_count,
        'skiftraknare' => $skiftraknare,
        'lopnummer' => $lopnummer,
        'op1' => $op1,
        'op2' => $op2,
        'op3' => $op3,
        'produkt' => $produkt,
        'ibc_ok' => $ibc_ok,
        'ibc_ej_ok' => $ibc_ej_ok,
        'bur_ej_ok' => $bur_ej_ok,
        'runtime_plc' => $runtime_plc,
        'rasttime' => $rasttime,
        'effektivitet' => $kpis['effektivitet'],
        'produktivitet' => $kpis['produktivitet'],
        'kvalitet' => $kpis['kvalitet'],
        'bonus_poang' => $kpis['bonus_poang']
    ]);
    
    // Logga framgång
    error_log("✅ Cycle saved - IBC #$ibc_count, Lopnr:$lopnummer, Bonus:{$kpis['bonus_poang']}");
}

// Hjälpfunktion: 8-bit → 16-bit konvertering
private function convert8to16bit(array $data): array {
    $result = [];
    for($i = 0; $i < (count($data) / 2); $i++) {
        // Big-endian: High byte först
        $result[$i] = ($data[$i*2] << 8) + $data[$i*2+1];
    }
    return $result;
}

// KPI-beräkningar
private function calculateKPIs(array $data): array {
    $ibc_ok = max($data['ibc_ok'] ?? 0, 0);
    $ibc_ej_ok = max($data['ibc_ej_ok'] ?? 0, 0);
    $bur_ej_ok = max($data['bur_ej_ok'] ?? 0, 0);
    $runtime = max($data['runtime_plc'] ?? 1, 1);
    
    // Effektivitet: Godkända av totalt producerade
    $total_produced = $ibc_ok + $ibc_ej_ok;
    $effektivitet = $total_produced > 0 
        ? round(($ibc_ok / $total_produced) * 100, 2) 
        : 0;
    
    // Produktivitet: IBC per timme (runtime är i sekunder eller minuter?)
    // ANTAGANDE: Runtime är i minuter (verifiera med PLC!)
    $runtime_hours = $runtime / 60;
    $produktivitet = $runtime_hours > 0 
        ? round($ibc_ok / $runtime_hours, 2) 
        : 0;
    
    // Kvalitet: Godkända minus defekta burar
    $kvalitet = $ibc_ok > 0 
        ? round((($ibc_ok - $bur_ej_ok) / $ibc_ok) * 100, 2) 
        : 0;
    
    // Bonus: Viktad summa (40% effektivitet, 40% produktivitet, 20% kvalitet)
    // Normalisera produktivitet till 0-100 skala (anta max 20 IBC/h = 100%)
    $produktivitet_normalized = min(($produktivitet / 20) * 100, 100);
    
    $bonus_poang = round(
        ($effektivitet * 0.4) + 
        ($produktivitet_normalized * 0.4) + 
        ($kvalitet * 0.2),
        2
    );
    
    return [
        'effektivitet' => $effektivitet,
        'produktivitet' => $produktivitet,
        'kvalitet' => $kvalitet,
        'bonus_poang' => $bonus_poang
    ];
}
```

---

## 🗄️ Databas-migration (uppdaterad)

```sql
-- Lägg till kolumner för D4000-D4009
ALTER TABLE `rebotling_ibc` 
  -- Operatörer (D4000-D4002)
  ADD COLUMN `op1` INT DEFAULT NULL COMMENT 'D4000 - Operatör 1 (Tvättplats) anställningsnr' AFTER `skiftraknare`,
  ADD COLUMN `op2` INT DEFAULT NULL COMMENT 'D4001 - Operatör 2 (Kontrollstation) anställningsnr' AFTER `op1`,
  ADD COLUMN `op3` INT DEFAULT NULL COMMENT 'D4002 - Operatör 3 (Truckförare) anställningsnr' AFTER `op2`,
  
  -- Produkt (D4003)
  ADD COLUMN `produkt` INT DEFAULT NULL COMMENT 'D4003 - Produkt (1=FoodGrade, 4=NonUN, 5=Färdiga)' AFTER `op3`,
  
  -- Produktion (D4004-D4006)
  ADD COLUMN `ibc_ok` INT DEFAULT NULL COMMENT 'D4004 - Antal godkända IBC' AFTER `produkt`,
  ADD COLUMN `ibc_ej_ok` INT DEFAULT NULL COMMENT 'D4005 - Antal kasserade IBC' AFTER `ibc_ok`,
  ADD COLUMN `bur_ej_ok` INT DEFAULT NULL COMMENT 'D4006 - Antal defekta burar' AFTER `ibc_ej_ok`,
  
  -- Tider (D4007-D4008)
  ADD COLUMN `runtime_plc` INT DEFAULT NULL COMMENT 'D4007 - Runtime (körtid i min/sek)' AFTER `bur_ej_ok`,
  ADD COLUMN `rasttime` INT DEFAULT NULL COMMENT 'D4008 - Rasttime (paustid)' AFTER `runtime_plc`,
  
  -- Räknare (D4009)
  ADD COLUMN `lopnummer` INT DEFAULT NULL COMMENT 'D4009 - Högsta löpnummer från PLC' AFTER `rasttime`,
  
  -- KPI:er (beräknade)
  ADD COLUMN `effektivitet` DECIMAL(5,2) DEFAULT NULL COMMENT 'KPI: IBC_OK / (IBC_OK + IBC_EJ_OK) * 100' AFTER `lopnummer`,
  ADD COLUMN `produktivitet` DECIMAL(5,2) DEFAULT NULL COMMENT 'KPI: IBC_OK per timme' AFTER `effektivitet`,
  ADD COLUMN `kvalitet` DECIMAL(5,2) DEFAULT NULL COMMENT 'KPI: (IBC_OK - BUR_EJ_OK) / IBC_OK * 100' AFTER `produktivitet`,
  ADD COLUMN `bonus_poang` DECIMAL(5,2) DEFAULT NULL COMMENT 'Bonus: Viktad summa av KPIer' AFTER `kvalitet`;

-- Index för snabbare queries
CREATE INDEX idx_op1 ON rebotling_ibc(op1);
CREATE INDEX idx_op2 ON rebotling_ibc(op2);
CREATE INDEX idx_op3 ON rebotling_ibc(op3);
CREATE INDEX idx_produkt ON rebotling_ibc(produkt);
CREATE INDEX idx_lopnummer ON rebotling_ibc(lopnummer);
CREATE INDEX idx_datum_op1 ON rebotling_ibc(datum, op1);

-- Verifiera
SELECT COLUMN_NAME, DATA_TYPE, COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'mauserdb' 
  AND TABLE_NAME = 'rebotling_ibc'
  AND COLUMN_NAME IN ('op1', 'op2', 'op3', 'produkt', 'ibc_ok', 'lopnummer', 'rasttime');
```

---

## 🧪 Test-script (uppdaterat)

```php
<?php
require_once 'vendor/autoload.php';

const GREEN = "\033[0;32m";
const RED = "\033[0;31m";
const YELLOW = "\033[1;33m";
const BLUE = "\033[0;34m";
const NC = "\033[0m";

echo BLUE . "=================================\n" . NC;
echo BLUE . "FX5 PLC Test - D4000-D4009\n" . NC;
echo BLUE . "=================================\n\n" . NC;

try {
    echo YELLOW . "📡 Ansluter till FX5 PLC (192.168.0.200)...\n" . NC;
    $modbus = new ModbusMaster("192.168.0.200", "TCP");
    echo GREEN . "✅ Ansluten!\n\n" . NC;
    
    // Konverteringsfunktion
    function convert8to16bit(array $data): array {
        $result = [];
        for($i = 0; $i < count($data) / 2; $i++) {
            $result[$i] = ($data[$i*2] << 8) + $data[$i*2+1];
        }
        return $result;
    }
    
    // Läs D4000-D4009 (10 register)
    echo YELLOW . "📊 Läser D4000-D4009 (10 register)...\n" . NC;
    $raw_data = $modbus->readMultipleRegisters(0, 4000, 10);
    echo GREEN . "✅ Läste " . count($raw_data) . " bytes\n" . NC;
    
    $plc_data = convert8to16bit($raw_data);
    
    echo "\n" . BLUE . "=== PLC Data (D4000-D4009) ===" . NC . "\n";
    echo "D4000 - Operatör 1:     " . $plc_data[0] . "\n";
    echo "D4001 - Operatör 2:     " . $plc_data[1] . "\n";
    echo "D4002 - Operatör 3:     " . $plc_data[2] . "\n";
    echo "D4003 - Produkt:        " . $plc_data[3] . " (1=FoodGrade, 4=NonUN, 5=Färdiga)\n";
    echo "D4004 - IBC OK:         " . $plc_data[4] . "\n";
    echo "D4005 - IBC Ej OK:      " . $plc_data[5] . "\n";
    echo "D4006 - Bur Ej OK:      " . $plc_data[6] . "\n";
    echo "D4007 - Runtime:        " . $plc_data[7] . " (sekunder/minuter?)\n";
    echo "D4008 - Rasttime:       " . $plc_data[8] . "\n";
    echo "D4009 - Löpnummer:      " . $plc_data[9] . "\n";
    
    // Beräkna KPI:er
    $ibc_ok = $plc_data[4];
    $ibc_ej_ok = $plc_data[5];
    $bur_ej_ok = $plc_data[6];
    $runtime = max($plc_data[7], 1);
    
    $total = $ibc_ok + $ibc_ej_ok;
    $effektivitet = $total > 0 ? round(($ibc_ok / $total) * 100, 2) : 0;
    
    $runtime_hours = $runtime / 60; // Anta minuter
    $produktivitet = $runtime_hours > 0 ? round($ibc_ok / $runtime_hours, 2) : 0;
    
    $kvalitet = $ibc_ok > 0 ? round((($ibc_ok - $bur_ej_ok) / $ibc_ok) * 100, 2) : 0;
    
    $prod_norm = min(($produktivitet / 20) * 100, 100);
    $bonus = round(($effektivitet * 0.4) + ($prod_norm * 0.4) + ($kvalitet * 0.2), 2);
    
    echo "\n" . BLUE . "=== KPI:er ===" . NC . "\n";
    echo "Effektivitet:   " . $effektivitet . "%\n";
    echo "Produktivitet:  " . $produktivitet . " IBC/h\n";
    echo "Kvalitet:       " . $kvalitet . "%\n";
    echo GREEN . "🏆 BONUS:       " . $bonus . " poäng\n" . NC;
    
    echo "\n" . GREEN . "✅ Test klart!\n" . NC;
    
} catch (Exception $e) {
    echo "\n" . RED . "❌ FEL: " . $e->getMessage() . "\n" . NC;
    exit(1);
}
```

---

## 🚀 DEPLOYMENT

### 1. Kör migration
```bash
mysql -u aiab -pNoreko2025 -h localhost -P 33061 < migrations/002_add_fx5_fields.sql
```

### 2. Testa anslutning
```bash
cd /home/clawd/clawd/mauserdb/noreko-plcbackend
php test_fx5.php
```

### 3. Backup befintlig fil
```bash
cp Rebotling.php Rebotling.php.backup.$(date +%Y%m%d)
```

### 4. Uppdatera Rebotling.php
Ersätt handleCycle() med ny implementation ovan.

### 5. Testa med webhook
```bash
curl -X POST "http://localhost/noreko-plcbackend/v1.php?line=rebotling&type=cycle&count=1"
```

### 6. Verifiera data
```bash
mysql -u aiab -pNoreko2025 -h localhost -P 33061 -e "
SELECT datum, op1, op2, op3, produkt, ibc_ok, lopnummer, bonus_poang 
FROM mauserdb.rebotling_ibc 
ORDER BY datum DESC LIMIT 3;
"
```

---

## ⚠️ VIKTIGA NOTERINGAR

1. **Runtime enhet**: Verifiera om D4007 är i **sekunder** eller **minuter**!
   - Testa med faktisk data från PLC
   - Justera KPI-beräkning om nödvändigt

2. **Trigger**: Y334 (Räkna IBC) triggar webhook → handleCycle()
   - Webhook måste anropas varje gång Y334 pulsas
   - Implementera i PLC-ladder logic eller HMI

3. **Felhantering**: Om ModbusTCP misslyckas → kasta exception!
   - INTE spara ofullständig data
   - Logga fel för debugging

4. **Produkttyper**:
   - 1 = FoodGrade
   - 4 = NonUN
   - 5 = Tvätta färdiga IBC

5. **Operatörs-ID**: Anställningsnummer (inte databas user_id!)
   - Koppla till personal-register vid behov

---

## 📚 Referenser

- **FX5 ModbusTCP Manual**: Se /docs/mitsubishi-fx5-modbus.pdf (om tillgänglig)
- **PHPModbus Library**: vendor/adduc/phpmodbus/
- **Befintlig implementation**: Rebotling.php (D200-D206, D210-D216)

---

**UPPDATERAD**: 2024-02-09  
**FÖR**: Mitsubishi FX5 PLC (D4000-D4009)  
**VERSION**: 2.0
