# ModbusTCP Expansion Plan - Rebotling System

## 🎯 Mål
Expandera ModbusTCP-läsning för att hämta ALL operatörs- och produktionsdata från PLC vid varje cykel, och implementera automatisk bonusberäkning.

---

## 📡 1. NULÄGE - Befintlig ModbusTCP Implementation

### Befintlig kod i `Rebotling.php`:
```php
// KOMMENTERAD UT - BEHÖVER AKTIVERAS!
$this->modbus = new ModbusMaster("192.168.0.200", "TCP");
$PLC_data = $this->modbus->readMultipleRegisters(0, 200, 7);
```

### Register-mappning (från PLC):

#### D200-D206 (handleRunning):
| Register | Beskrivning | Datatyp | Kolumn i DB |
|----------|-------------|---------|-------------|
| D200 | Program | INT16 | `program` |
| D201 | Operatör 1 | INT16 | `op1` |
| D202 | Operatör 2 | INT16 | `op2` |
| D203 | Operatör 3 | INT16 | `op3` |
| D204 | Produkt | INT16 | `produkt` |
| D205 | Antal | INT16 | `antal` |
| D206 | Runtime PLC | INT16 | `runtime_plc` |

#### D210-D216 (handleSkiftrapport):
| Register | Beskrivning | Datatyp | Kolumn i DB |
|----------|-------------|---------|-------------|
| D210 | IBC OK | INT16 | `ibc_ok` |
| D211 | Bur Ej OK | INT16 | `bur_ej_ok` |
| D212 | IBC Ej OK | INT16 | `ibc_ej_ok` |
| D213 | Totalt | INT16 | `totalt` |
| D214 | Operator ID | INT16 | `user_id` |
| D215 | Produkt ID | INT16 | `product_id` |
| D216 | Drifttid | INT16 | `drifttid` |

---

## 🔧 2. IMPLEMENTATIONSSTEG

### STEG 1: Aktivera ModbusTCP i handleCycle()

**Nuvarande kod** (rad ~90 i Rebotling.php):
```php
public function handleCycle(array $data): void {
    if (!isset($_GET['count'])) {
        throw new InvalidArgumentException('Missing required fields for user.created');
    }
    // ... beräkningar ...
}
```

**NYA KODEN:**
```php
public function handleCycle(array $data): void {
    // 1. Anslut till PLC via ModbusTCP
    $this->modbus = new ModbusMaster("192.168.0.200", "TCP");
    
    // 2. Läs ALLA register för en komplett cykel (D200-D206 + D210-D216)
    // Läs D200-D206 (7 register = 14 bytes)
    $PLC_data_running = $this->modbus->readMultipleRegisters(0, 200, 7);
    
    // Läs D210-D216 (7 register = 14 bytes)
    $PLC_data_skift = $this->modbus->readMultipleRegisters(0, 210, 7);
    
    // 3. Konvertera 8-bit till 16-bit värden
    $running_data = $this->convert8to16bit($PLC_data_running);
    $skift_data = $this->convert8to16bit($PLC_data_skift);
    
    // 4. Extrahera data
    $program = $running_data[0];      // D200
    $op1 = $running_data[1];          // D201
    $op2 = $running_data[2];          // D202
    $op3 = $running_data[3];          // D203
    $produkt = $running_data[4];      // D204
    $antal = $running_data[5];        // D205
    $runtime_plc = $running_data[6];  // D206
    
    $ibc_ok = $skift_data[0];         // D210
    $bur_ej_ok = $skift_data[1];      // D211
    $ibc_ej_ok = $skift_data[2];      // D212
    $totalt = $skift_data[3];         // D213
    $operator_id = $skift_data[4];    // D214
    $product_id = $skift_data[5];     // D215
    $drifttid = $skift_data[6];       // D216
    
    // 5. Beräkna KPI:er
    $kpis = $this->calculateKPIs([
        'ibc_ok' => $ibc_ok,
        'ibc_ej_ok' => $ibc_ej_ok,
        'bur_ej_ok' => $bur_ej_ok,
        'runtime_plc' => $runtime_plc,
        'drifttid' => $drifttid
    ]);
    
    // 6. Spara till databas
    $stmt = $this->db->prepare('
        INSERT INTO rebotling_ibc (
            s_count, ibc_count, skiftraknare, produktion_procent,
            program, op1, op2, op3, produkt, antal, runtime_plc,
            ibc_ok, bur_ej_ok, ibc_ej_ok, totalt, operator_id, product_id, drifttid,
            effektivitet, produktivitet, kvalitet, bonus_poang
        )
        VALUES (
            :s_count, :ibc_count, :skiftraknare, :produktion_procent,
            :program, :op1, :op2, :op3, :produkt, :antal, :runtime_plc,
            :ibc_ok, :bur_ej_ok, :ibc_ej_ok, :totalt, :operator_id, :product_id, :drifttid,
            :effektivitet, :produktivitet, :kvalitet, :bonus_poang
        )
    ');
    
    $stmt->execute([
        's_count' => $_GET['count'] ?? $antal,
        'ibc_count' => $ibc_count,  // Beräknas från befintlig logik
        'skiftraknare' => $skiftraknare,  // Beräknas från befintlig logik
        'produktion_procent' => $produktion_procent,  // Beräknas från befintlig logik
        'program' => $program,
        'op1' => $op1,
        'op2' => $op2,
        'op3' => $op3,
        'produkt' => $produkt,
        'antal' => $antal,
        'runtime_plc' => $runtime_plc,
        'ibc_ok' => $ibc_ok,
        'bur_ej_ok' => $bur_ej_ok,
        'ibc_ej_ok' => $ibc_ej_ok,
        'totalt' => $totalt,
        'operator_id' => $operator_id,
        'product_id' => $product_id,
        'drifttid' => $drifttid,
        'effektivitet' => $kpis['effektivitet'],
        'produktivitet' => $kpis['produktivitet'],
        'kvalitet' => $kpis['kvalitet'],
        'bonus_poang' => $kpis['bonus_poang']
    ]);
}

// Hjälpfunktion för 8->16bit konvertering
private function convert8to16bit(array $data): array {
    $result = [];
    for($i = 0; $i < (count($data) / 2); $i++) {
        $result[$i] = ($data[$i*2] << 8) + $data[$i*2+1];
    }
    return $result;
}

// Beräkna KPI:er och bonus
private function calculateKPIs(array $data): array {
    $ibc_ok = $data['ibc_ok'] ?? 0;
    $ibc_ej_ok = $data['ibc_ej_ok'] ?? 0;
    $bur_ej_ok = $data['bur_ej_ok'] ?? 0;
    $runtime = $data['runtime_plc'] ?? 1; // Undvik division med 0
    
    // Effektivitet: Andel godkända av totalt producerade
    $total_produced = $ibc_ok + $ibc_ej_ok;
    $effektivitet = $total_produced > 0 ? round(($ibc_ok / $total_produced) * 100, 2) : 0;
    
    // Produktivitet: Godkända per timme runtime
    $produktivitet = $runtime > 0 ? round(($ibc_ok * 60) / $runtime, 2) : 0;
    
    // Kvalitet: Godkända minus defekta burar
    $kvalitet = $ibc_ok > 0 ? round((($ibc_ok - $bur_ej_ok) / $ibc_ok) * 100, 2) : 0;
    
    // Bonuspoäng: Viktad summa av KPI:er
    // 40% effektivitet, 40% produktivitet, 20% kvalitet
    $bonus_poang = round(
        ($effektivitet * 0.4) + 
        (min($produktivitet, 100) * 0.4) +  // Cap produktivitet vid 100
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

### STEG 2: Uppdatera Databas-schema

**SQL Migration Script:**

```sql
-- Lägg till nya kolumner i rebotling_ibc
ALTER TABLE `rebotling_ibc` 
  ADD COLUMN `program` INT DEFAULT NULL AFTER `produktion_procent`,
  ADD COLUMN `op1` INT DEFAULT NULL AFTER `program`,
  ADD COLUMN `op2` INT DEFAULT NULL AFTER `op1`,
  ADD COLUMN `op3` INT DEFAULT NULL AFTER `op2`,
  ADD COLUMN `produkt` INT DEFAULT NULL AFTER `op3`,
  ADD COLUMN `antal` INT DEFAULT NULL AFTER `produkt`,
  ADD COLUMN `runtime_plc` INT DEFAULT NULL AFTER `antal`,
  ADD COLUMN `ibc_ok` INT DEFAULT NULL AFTER `runtime_plc`,
  ADD COLUMN `bur_ej_ok` INT DEFAULT NULL AFTER `ibc_ok`,
  ADD COLUMN `ibc_ej_ok` INT DEFAULT NULL AFTER `bur_ej_ok`,
  ADD COLUMN `totalt` INT DEFAULT NULL AFTER `ibc_ej_ok`,
  ADD COLUMN `operator_id` INT DEFAULT NULL AFTER `totalt`,
  ADD COLUMN `product_id` INT DEFAULT NULL AFTER `operator_id`,
  ADD COLUMN `drifttid` INT DEFAULT NULL AFTER `product_id`,
  ADD COLUMN `effektivitet` DECIMAL(5,2) DEFAULT NULL COMMENT 'IBC_OK / (IBC_OK + IBC_EJ_OK) * 100' AFTER `drifttid`,
  ADD COLUMN `produktivitet` DECIMAL(5,2) DEFAULT NULL COMMENT 'IBC_OK per timme' AFTER `effektivitet`,
  ADD COLUMN `kvalitet` DECIMAL(5,2) DEFAULT NULL COMMENT '(IBC_OK - BUR_EJ_OK) / IBC_OK * 100' AFTER `produktivitet`,
  ADD COLUMN `bonus_poang` DECIMAL(5,2) DEFAULT NULL COMMENT 'Viktad summa av KPIer' AFTER `kvalitet`;

-- Index för snabbare queries på operatörer och produkter
CREATE INDEX idx_operator ON rebotling_ibc(operator_id);
CREATE INDEX idx_product ON rebotling_ibc(product_id);
CREATE INDEX idx_skiftraknare ON rebotling_ibc(skiftraknare);
CREATE INDEX idx_datum_operator ON rebotling_ibc(datum, operator_id);
```

---

### STEG 3: Testning av ModbusTCP-anslutning

**Test-script** (`test_modbus.php`):

```php
<?php
require_once 'vendor/autoload.php';

try {
    // Anslut till PLC
    $modbus = new ModbusMaster("192.168.0.200", "TCP");
    echo "✅ Ansluten till PLC\n";
    
    // Läs D200-D206
    $data1 = $modbus->readMultipleRegisters(0, 200, 7);
    echo "📊 D200-D206 lästa: " . count($data1) . " bytes\n";
    
    // Läs D210-D216
    $data2 = $modbus->readMultipleRegisters(0, 210, 7);
    echo "📊 D210-D216 lästa: " . count($data2) . " bytes\n";
    
    // Konvertera och visa
    function convert8to16($data) {
        $result = [];
        for($i = 0; $i < count($data) / 2; $i++) {
            $result[$i] = ($data[$i*2] << 8) + $data[$i*2+1];
        }
        return $result;
    }
    
    $d200 = convert8to16($data1);
    $d210 = convert8to16($data2);
    
    echo "\n=== D200-D206 (Running) ===\n";
    echo "Program: {$d200[0]}\n";
    echo "Op1: {$d200[1]}\n";
    echo "Op2: {$d200[2]}\n";
    echo "Op3: {$d200[3]}\n";
    echo "Produkt: {$d200[4]}\n";
    echo "Antal: {$d200[5]}\n";
    echo "Runtime PLC: {$d200[6]}\n";
    
    echo "\n=== D210-D216 (Skiftrapport) ===\n";
    echo "IBC OK: {$d210[0]}\n";
    echo "Bur Ej OK: {$d210[1]}\n";
    echo "IBC Ej OK: {$d210[2]}\n";
    echo "Totalt: {$d210[3]}\n";
    echo "Operator ID: {$d210[4]}\n";
    echo "Product ID: {$d210[5]}\n";
    echo "Drifttid: {$d210[6]}\n";
    
} catch (Exception $e) {
    echo "❌ FEL: " . $e->getMessage() . "\n";
}
```

---

### STEG 4: Dashboard API-endpoints

**Nya endpoints behövs i backend:**

```javascript
// GET /api/rebotling/operator-stats?operator_id=123&date_from=2024-01-01&date_to=2024-01-31
app.get('/api/rebotling/operator-stats', (req, res) => {
    const sql = `
        SELECT 
            operator_id,
            DATE(datum) as dag,
            COUNT(*) as antal_cykler,
            SUM(ibc_ok) as totalt_ibc_ok,
            SUM(ibc_ej_ok) as totalt_ibc_ej_ok,
            SUM(bur_ej_ok) as totalt_bur_ej_ok,
            AVG(effektivitet) as avg_effektivitet,
            AVG(produktivitet) as avg_produktivitet,
            AVG(kvalitet) as avg_kvalitet,
            AVG(bonus_poang) as avg_bonus
        FROM rebotling_ibc
        WHERE operator_id = ?
        AND datum BETWEEN ? AND ?
        GROUP BY operator_id, DATE(datum)
        ORDER BY datum DESC
    `;
    // Execute query...
});

// GET /api/rebotling/bonus-ranking?date_from=2024-01-01&date_to=2024-01-31
app.get('/api/rebotling/bonus-ranking', (req, res) => {
    const sql = `
        SELECT 
            r.operator_id,
            u.name as operator_namn,
            COUNT(*) as antal_cykler,
            SUM(r.ibc_ok) as totalt_ibc_ok,
            AVG(r.effektivitet) as avg_effektivitet,
            AVG(r.produktivitet) as avg_produktivitet,
            AVG(r.kvalitet) as avg_kvalitet,
            AVG(r.bonus_poang) as avg_bonus,
            SUM(r.bonus_poang) as total_bonus
        FROM rebotling_ibc r
        LEFT JOIN users u ON r.operator_id = u.id
        WHERE r.datum BETWEEN ? AND ?
        AND r.operator_id IS NOT NULL
        GROUP BY r.operator_id
        ORDER BY total_bonus DESC
    `;
    // Execute query...
});
```

---

### STEG 5: Frontend Dashboard-komponenter

**Komponenter att skapa:**

1. **OperatorStatsCard.tsx** - Visa individuell operatörs-prestanda
2. **BonusRanking.tsx** - Topplista med bonuspoäng
3. **KPIChart.tsx** - Graf för effektivitet/produktivitet/kvalitet över tid
4. **ShiftSummary.tsx** - Sammanfattning per skift

**Exempel KPI-visualisering:**
```typescript
interface KPIData {
  effektivitet: number;  // 0-100%
  produktivitet: number; // IBC/h
  kvalitet: number;      // 0-100%
  bonus_poang: number;   // 0-100
}

// Grafkomponent för att visa trend över tid
```

---

## ✅ 3. CHECKLISTA

- [ ] **Databas-migration**
  - [ ] Kör ALTER TABLE script
  - [ ] Verifiera nya kolumner finns
  - [ ] Testa index-prestanda

- [ ] **ModbusTCP-kod**
  - [ ] Avkommentera Modbus-anrop i handleCycle()
  - [ ] Lägg till convert8to16bit() hjälpfunktion
  - [ ] Lägg till calculateKPIs() funktion
  - [ ] Uppdatera INSERT-statement med nya kolumner

- [ ] **Testning**
  - [ ] Kör test_modbus.php för att verifiera PLC-anslutning
  - [ ] Testa en komplett cykel från PLC → DB
  - [ ] Verifiera att alla 14 register läses korrekt
  - [ ] Kontrollera att KPI-beräkningar stämmer

- [ ] **Backend API**
  - [ ] Implementera /api/rebotling/operator-stats
  - [ ] Implementera /api/rebotling/bonus-ranking
  - [ ] Testa endpoints med Postman/curl

- [ ] **Frontend Dashboard**
  - [ ] Skapa OperatorStatsCard komponent
  - [ ] Skapa BonusRanking komponent
  - [ ] Skapa KPIChart komponent
  - [ ] Integrera med backend API

---

## 🎯 4. BONUSSYSTEM - Viktning av KPI:er

### Formel:
```
Bonus Poäng = (Effektivitet × 0.4) + (Produktivitet × 0.4) + (Kvalitet × 0.2)
```

### KPI-definitioner:
- **Effektivitet**: `(IBC_OK / (IBC_OK + IBC_EJ_OK)) × 100`
- **Produktivitet**: `(IBC_OK × 60 / Runtime_PLC)` (IBC per timme, cap vid 100)
- **Kvalitet**: `((IBC_OK - BUR_EJ_OK) / IBC_OK) × 100`

### Exempel:
```
IBC_OK = 95
IBC_EJ_OK = 5
BUR_EJ_OK = 2
Runtime_PLC = 120 minuter

Effektivitet = (95 / 100) × 100 = 95%
Produktivitet = (95 × 60 / 120) = 47.5 IBC/h → normalisera till 47.5%
Kvalitet = ((95 - 2) / 95) × 100 = 97.9%

Bonus = (95 × 0.4) + (47.5 × 0.4) + (97.9 × 0.2)
      = 38 + 19 + 19.58
      = 76.58 poäng
```

---

## 📌 5. NÄSTA STEG - PRIORITERAD ORDNING

1. **FÖRST**: Kör databas-migration för att lägga till kolumner
2. **SEDAN**: Testa ModbusTCP-anslutning med test_modbus.php
3. **DÄREFTER**: Uppdatera Rebotling.php med ModbusTCP-läsning
4. **SLUTLIGEN**: Bygg dashboard för visualisering

---

## 🔗 Relaterade filer:
- `/home/clawd/clawd/mauserdb/noreko-plcbackend/Rebotling.php` - Huvudfil att modifiera
- `/home/clawd/clawd/mauserdb/noreko-plcbackend/vendor/adduc/phpmodbus/` - ModbusTCP library
- `/home/clawd/clawd/mauserdb/noreko-frontend/` - Frontend för dashboard

---

**FÖRFATTARE**: AI Agent (Clawdbot)  
**DATUM**: 2024-02-09  
**VERSION**: 1.0
