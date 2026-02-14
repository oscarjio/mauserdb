# PLC Register-mappning - Rebotling System

## 📡 ModbusTCP-anslutning
- **PLC IP**: `192.168.0.200`
- **Protokoll**: TCP
- **Library**: PHPModbus (vendor/adduc/phpmodbus)

---

## 📊 Register-mappning

### D200-D206: Running-data (läses vid varje statusändring)

| Register | Namn | Datatyp | Beskrivning | DB-kolumn |
|----------|------|---------|-------------|-----------|
| **D200** | Program | INT16 | Programnummer från PLC | `program` |
| **D201** | Operatör 1 | INT16 | ID för operatör 1 (från HMI) | `op1` |
| **D202** | Operatör 2 | INT16 | ID för operatör 2 (från HMI) | `op2` |
| **D203** | Operatör 3 | INT16 | ID för operatör 3 (från HMI) | `op3` |
| **D204** | Produkt | INT16 | Produkt-ID (från HMI) | `produkt` |
| **D205** | Antal | INT16 | Löpnummer/räknare från PLC | `antal` |
| **D206** | Runtime PLC | INT16 | Maskinkörning i minuter | `runtime_plc` |

**Läsning:**
```php
$modbus = new ModbusMaster("192.168.0.200", "TCP");
$raw_data = $modbus->readMultipleRegisters(0, 200, 7);
$data = convert8to16bit($raw_data); // 7 register = 14 bytes → 7 värden
```

---

### D210-D216: Skiftrapport-data (läses vid cykel-avslut)

| Register | Namn | Datatyp | Beskrivning | DB-kolumn |
|----------|------|---------|-------------|-----------|
| **D210** | IBC OK | INT16 | Antal godkända IBC | `ibc_ok` |
| **D211** | Bur Ej OK | INT16 | Antal defekta burar | `bur_ej_ok` |
| **D212** | IBC Ej OK | INT16 | Antal kasserade IBC | `ibc_ej_ok` |
| **D213** | Totalt | INT16 | Totalt antal producerade | `totalt` |
| **D214** | Operator ID | INT16 | Huvudoperatör för cykeln | `operator_id` |
| **D215** | Product ID | INT16 | Produkt-ID | `product_id` |
| **D216** | Drifttid | INT16 | Drifttid i minuter | `drifttid` |

**Läsning:**
```php
$modbus = new ModbusMaster("192.168.0.200", "TCP");
$raw_data = $modbus->readMultipleRegisters(0, 210, 7);
$data = convert8to16bit($raw_data); // 7 register = 14 bytes → 7 värden
```

---

## 🔄 Dataflöde

```
┌──────────────┐
│     HMI      │  ← Operatör fyller i namn & väljer produkt
└──────┬───────┘
       │
       ↓
┌──────────────┐
│     PLC      │  ← Sparar data i D-register (D200-D206, D210-D216)
└──────┬───────┘
       │ ModbusTCP
       ↓
┌──────────────┐
│ PHP Backend  │  ← Läser register via ModbusTCP
│ (Rebotling.  │     Beräknar KPI:er
│  php)        │     Sparar till databas
└──────┬───────┘
       │
       ↓
┌──────────────┐
│   MySQL DB   │  ← rebotling_ibc tabellen
└──────┬───────┘
       │
       ↓
┌──────────────┐
│  Dashboard   │  ← Visualisering av prestanda
└──────────────┘
```

---

## 🧮 KPI-beräkningar (från PLC-data)

### 1. Effektivitet (Godkända av totalt producerade)
```
Effektivitet = (IBC_OK / (IBC_OK + IBC_EJ_OK)) × 100
```
**Exempel**: 95 godkända, 5 kasserade → `(95 / 100) × 100 = 95%`

---

### 2. Produktivitet (IBC per timme)
```
Produktivitet = (IBC_OK × 60) / Runtime_PLC
```
**Exempel**: 95 godkända på 120 minuter → `(95 × 60) / 120 = 47.5 IBC/h`

---

### 3. Kvalitet (Godkända minus defekter)
```
Kvalitet = ((IBC_OK - BUR_EJ_OK) / IBC_OK) × 100
```
**Exempel**: 95 godkända, 2 defekta burar → `((95 - 2) / 95) × 100 = 97.9%`

---

### 4. Bonus Poäng (Viktad summa)
```
Bonus = (Effektivitet × 0.4) + (Produktivitet × 0.4) + (Kvalitet × 0.2)
```
**Viktning:**
- 40% Effektivitet (viktigast - hur många blir godkända)
- 40% Produktivitet (viktigast - hur snabbt går det)
- 20% Kvalitet (mindre vikt - defekter påverkar mindre)

**Exempel:**
```
Effektivitet = 95%
Produktivitet = 47.5% (normaliserad till 0-100, cap vid 100)
Kvalitet = 97.9%

Bonus = (95 × 0.4) + (47.5 × 0.4) + (97.9 × 0.2)
      = 38 + 19 + 19.58
      = 76.58 poäng
```

---

## 🔧 8-bit → 16-bit Konvertering

PLC D-register är 16-bit, men ModbusTCP läser i 8-bit bytes.  
**Konvertering krävs:**

```php
function convert8to16bit(array $data): array {
    $result = [];
    for($i = 0; $i < (count($data) / 2); $i++) {
        // High byte << 8 + Low byte
        $result[$i] = ($data[$i*2] << 8) + $data[$i*2+1];
    }
    return $result;
}
```

**Exempel:**
```
Raw data: [0x00, 0x5F]  (2 bytes)
Converted: 0x005F = 95 (decimal)
```

---

## 📦 Databas-schema (rebotling_ibc)

```sql
CREATE TABLE rebotling_ibc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    datum TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    s_count INT,
    ibc_count INT,
    skiftraknare INT,
    produktion_procent DECIMAL(5,2),
    
    -- D200-D206 (Running)
    program INT,
    op1 INT,
    op2 INT,
    op3 INT,
    produkt INT,
    antal INT,
    runtime_plc INT,
    
    -- D210-D216 (Skiftrapport)
    ibc_ok INT,
    bur_ej_ok INT,
    ibc_ej_ok INT,
    totalt INT,
    operator_id INT,
    product_id INT,
    drifttid INT,
    
    -- KPI:er (beräknade)
    effektivitet DECIMAL(5,2),
    produktivitet DECIMAL(5,2),
    kvalitet DECIMAL(5,2),
    bonus_poang DECIMAL(5,2),
    
    -- Index
    INDEX idx_operator_id (operator_id),
    INDEX idx_product_id (product_id),
    INDEX idx_skiftraknare (skiftraknare),
    INDEX idx_datum_operator (datum, operator_id)
);
```

---

## 🚀 Användning

### 1. Testa anslutning:
```bash
cd /home/clawd/clawd/mauserdb/noreko-plcbackend
php test_modbus.php
```

### 2. Aktivera i produktion:
Avkommentera ModbusTCP-koden i `Rebotling.php`:
```php
// I handleCycle():
$this->modbus = new ModbusMaster("192.168.0.200", "TCP");
$data_200 = $this->modbus->readMultipleRegisters(0, 200, 7);
$data_210 = $this->modbus->readMultipleRegisters(0, 210, 7);
```

### 3. Verifiera data:
```sql
SELECT 
    datum,
    operator_id,
    ibc_ok,
    effektivitet,
    produktivitet,
    kvalitet,
    bonus_poang
FROM rebotling_ibc
ORDER BY datum DESC
LIMIT 10;
```

---

## 📝 Noteringar

- **HMI är datakällan** för operatörer och produktval - INTE backend!
- **PLC är "source of truth"** - vi läser bara och sparar
- **ModbusTCP-anrop sker vid varje cykel** (~1-5 minuter mellan cykler)
- **Fel-hantering viktig**: Om PLC inte svarar, logga fel men crasha inte systemet
- **Data-validering**: Kontrollera att värden är inom rimliga gränser

---

## 🔍 Felsökning

### PLC svarar inte:
```bash
ping 192.168.0.200
telnet 192.168.0.200 502  # ModbusTCP port
```

### Register läses fel:
- Kontrollera byte-ordning (big-endian vs little-endian)
- Verifiera att convert8to16bit() fungerar korrekt
- Testa med test_modbus.php först

### Data sparas inte:
- Kolla PHP error logs: `tail -f /var/log/php/error.log`
- Verifiera databas-anslutning
- Kontrollera att migration körts: `DESCRIBE rebotling_ibc;`

---

**UPPDATERAD**: 2024-02-09  
**VERSION**: 1.0
