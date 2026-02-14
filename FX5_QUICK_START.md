# 🚀 FX5 Implementation - Snabbstart

## ⚡ 4 STEG TILL PRODUCTION

---

## STEG 1: Databas-migration (2 min)

```bash
cd /home/clawd/clawd/mauserdb
mysql -u aiab -pNoreko2025 -h localhost -P 33061 < migrations/002_add_fx5_d4000_fields.sql
```

**Förväntat resultat:**
```
✅ Nya kolumner tillagda!
✅ Index tillagda!
🎉 Migration klar!
```

**Verifiera:**
```bash
mysql -u aiab -pNoreko2025 -h localhost -P 33061 -e "
DESCRIBE mauserdb.rebotling_ibc;
" | grep -E "op1|op2|op3|produkt|ibc_ok|lopnummer|rasttime|bonus"
```

---

## STEG 2: Testa PLC-anslutning (5 min)

```bash
cd /home/clawd/clawd/mauserdb/noreko-plcbackend
php test_fx5.php
```

**Förväntat resultat:**
```
📡 Ansluter till FX5 PLC (192.168.0.200)...
✅ Ansluten!

📊 Läser D4000-D4009 (10 register)...
✅ Läste 20 bytes från PLC

=== PLC Register-värden ===

OPERATÖRER:
  D4000 - Operatör 1 (Tvättplats):     12345
  D4001 - Operatör 2 (Kontrollstation): 67890
  D4002 - Operatör 3 (Truckförare):    11223

PRODUKTION:
  D4003 - Produkt:        FoodGrade
  D4004 - IBC OK:         95
  D4005 - IBC Ej OK:      5
  D4006 - Bur Ej OK:      2

TIDER:
  D4007 - Runtime:        120 (minuter)
  D4008 - Rasttime:       15

RÄKNARE:
  D4009 - Löpnummer:      1234

=== KPI:er ===
Effektivitet:   95%
Produktivitet:  47.5 IBC/h
Kvalitet:       97.89%
🏆 BONUS POÄNG: 76.58 / 100

✅ ALLA TESTER KLARA!
```

**Om test FAILAR:**
```bash
# Testa nätverksanslutning:
ping 192.168.0.200

# Testa ModbusTCP port:
telnet 192.168.0.200 502

# Kolla att PHPModbus finns:
ls -la vendor/adduc/phpmodbus/
```

---

## STEG 3: Uppdatera Rebotling.php (15 min)

### 3.1 Backup
```bash
cd /home/clawd/clawd/mauserdb/noreko-plcbackend
cp Rebotling.php Rebotling.php.backup.$(date +%Y%m%d_%H%M%S)
```

### 3.2 Hitta handleCycle() metoden
Öppna `Rebotling.php` och hitta denna rad (runt rad 20):
```php
public function handleCycle(array $data): void {
```

### 3.3 Lägg till ModbusTCP-läsning DIREKT EFTER valideringen

**HITTA:**
```php
public function handleCycle(array $data): void {
    // Validera data
    if (!isset($_GET['count'])) {
        throw new InvalidArgumentException('Missing required fields for user.created');
    }
```

**LÄGG TILL DIREKT EFTER:**
```php
    // === LÄSER D4000-D4009 FRÅN FX5 PLC ===
    try {
        $this->modbus = new ModbusMaster("192.168.0.200", "TCP");
        
        // Läs alla 10 register i ett anrop (D4000-D4009)
        $raw_data = $this->modbus->readMultipleRegisters(0, 4000, 10);
        $plc_data = $this->convert8to16bit($raw_data);
        
        // Extrahera värden
        $op1         = $plc_data[0];  // D4000
        $op2         = $plc_data[1];  // D4001
        $op3         = $plc_data[2];  // D4002
        $produkt     = $plc_data[3];  // D4003
        $ibc_ok      = $plc_data[4];  // D4004
        $ibc_ej_ok   = $plc_data[5];  // D4005
        $bur_ej_ok   = $plc_data[6];  // D4006
        $runtime_plc = $plc_data[7];  // D4007
        $rasttime    = $plc_data[8];  // D4008
        $lopnummer   = $plc_data[9];  // D4009
        
        error_log("✅ PLC Data - Op1:$op1 Op2:$op2 Op3:$op3 IBC_OK:$ibc_ok Lopnr:$lopnummer");
        
    } catch (Exception $e) {
        error_log("❌ ModbusTCP Error: " . $e->getMessage());
        throw new RuntimeException("Failed to read PLC data: " . $e->getMessage());
    }
```

### 3.4 Uppdatera IBC_count beräkning

**HITTA** (runt rad 40):
```php
if($res["s_count"] < $_GET["count"]){
    $ibc_count = ($_GET["count"] - $res["s_count"]) + 1;
```

**ERSÄTT MED** (använd lopnummer istället):
```php
if($res["lopnummer"] && $lopnummer >= $res["lopnummer"]){
    $ibc_count = ($lopnummer - $res["lopnummer"]) + 1;
```

### 3.5 Lägg till KPI-beräkning (innan SQL INSERT)

**LÄGG TILL FÖRE SQL INSERT:**
```php
    // === BERÄKNA KPI:ER ===
    $kpis = $this->calculateKPIs([
        'ibc_ok' => $ibc_ok,
        'ibc_ej_ok' => $ibc_ej_ok,
        'bur_ej_ok' => $bur_ej_ok,
        'runtime_plc' => $runtime_plc
    ]);
```

### 3.6 Uppdatera SQL INSERT

**HITTA:**
```php
$stmt = $this->db->prepare('
    INSERT INTO rebotling_ibc (s_count, ibc_count, skiftraknare, produktion_procent)
    VALUES (:s_count, :ibc_count, :skiftraknare, :produktion_procent)
');
```

**ERSÄTT MED:**
```php
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
```

### 3.7 Lägg till hjälpfunktioner (längst ner i klassen)

**LÄGG TILL FÖRE SISTA `}`:**
```php
    // Konvertera 8-bit bytes till 16-bit värden
    private function convert8to16bit(array $data): array {
        $result = [];
        for($i = 0; $i < (count($data) / 2); $i++) {
            $result[$i] = ($data[$i*2] << 8) + $data[$i*2+1];
        }
        return $result;
    }

    // Beräkna KPI:er och bonus
    private function calculateKPIs(array $data): array {
        $ibc_ok = max($data['ibc_ok'] ?? 0, 0);
        $ibc_ej_ok = max($data['ibc_ej_ok'] ?? 0, 0);
        $bur_ej_ok = max($data['bur_ej_ok'] ?? 0, 0);
        $runtime = max($data['runtime_plc'] ?? 1, 1);
        
        $total_produced = $ibc_ok + $ibc_ej_ok;
        $effektivitet = $total_produced > 0 
            ? round(($ibc_ok / $total_produced) * 100, 2) 
            : 0;
        
        $runtime_hours = $runtime / 60;
        $produktivitet = $runtime_hours > 0 
            ? round($ibc_ok / $runtime_hours, 2) 
            : 0;
        
        $kvalitet = $ibc_ok > 0 
            ? round((($ibc_ok - $bur_ej_ok) / $ibc_ok) * 100, 2) 
            : 0;
        
        $prod_norm = min(($produktivitet / 20) * 100, 100);
        $bonus_poang = round(
            ($effektivitet * 0.4) + 
            ($prod_norm * 0.4) + 
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
}
```

**SPARA FILEN!**

---

## STEG 4: Testa i produktion (10 min)

### 4.1 Testa PHP-syntax
```bash
php -l Rebotling.php
```
**Förväntat:** `No syntax errors detected`

### 4.2 Simulera webhook
```bash
curl -X POST "http://localhost/noreko-plcbackend/v1.php?line=rebotling&type=cycle&count=1"
```

### 4.3 Kontrollera logs
```bash
tail -f /var/log/php/error.log
# Eller:
tail -f /var/log/apache2/error.log
```

**Förväntat i log:**
```
✅ PLC Data - Op1:12345 Op2:67890 Op3:11223 IBC_OK:95 Lopnr:1234
✅ Cycle saved - IBC #1, Lopnr:1234, Bonus:76.58
```

### 4.4 Verifiera databas
```bash
mysql -u aiab -pNoreko2025 -h localhost -P 33061 -e "
SELECT 
    datum,
    op1, op2, op3,
    produkt,
    ibc_ok, ibc_ej_ok,
    lopnummer,
    effektivitet,
    bonus_poang
FROM mauserdb.rebotling_ibc
ORDER BY datum DESC
LIMIT 5;
"
```

**Förväntat resultat:**
```
+---------------------+-------+-------+-------+---------+--------+-----------+-----------+--------------+-------------+
| datum               | op1   | op2   | op3   | produkt | ibc_ok | ibc_ej_ok | lopnummer | effektivitet | bonus_poang |
+---------------------+-------+-------+-------+---------+--------+-----------+-----------+--------------+-------------+
| 2024-02-09 15:23:45 | 12345 | 67890 | 11223 |       1 |     95 |         5 |      1234 |        95.00 |       76.58 |
+---------------------+-------+-------+-------+---------+--------+-----------+-----------+--------------+-------------+
```

---

## ✅ KLART!

Din ModbusTCP-implementation är nu live och läser alla 10 register från FX5 PLC!

---

## 📊 NÄSTA STEG: Dashboard

### API-endpoints att skapa:

```bash
# Operatörs-statistik
GET /api/rebotling/operator-stats/:employeeId?date_from=2024-01-01&date_to=2024-01-31

# Bonus-ranking (topplista)
GET /api/rebotling/bonus-ranking?days=7

# Produkt-statistik
GET /api/rebotling/product-stats/:productId?days=30

# Skift-sammanfattning
GET /api/rebotling/shift-summary/:skiftraknare
```

### Dashboard-komponenter:

1. **OperatorStatsCard** - Visa individuell operatörs-prestanda
2. **BonusLeaderboard** - Topplista med bonus-poäng
3. **ProductionChart** - Graf över IBC_ok per dag/vecka
4. **QualityMetrics** - KPI-visualisering (effektivitet/produktivitet/kvalitet)

---

## 🆘 Felsökning

**Problem: "Can't connect to PLC"**
```bash
ping 192.168.0.200
telnet 192.168.0.200 502
# Kontrollera att ModbusTCP är aktiverat i PLC:n
```

**Problem: "Wrong number of bytes returned"**
```bash
# Testa läsning manuellt:
php -r "
require_once 'vendor/autoload.php';
\$m = new ModbusMaster('192.168.0.200', 'TCP');
\$data = \$m->readMultipleRegisters(0, 4000, 10);
echo 'Bytes: ' . count(\$data) . PHP_EOL;
"
```

**Problem: "SQL error"**
```bash
# Kontrollera att alla kolumner finns:
mysql -u aiab -pNoreko2025 -h localhost -P 33061 -e "DESCRIBE mauserdb.rebotling_ibc;"
```

**Problem: "Values are 0"**
```bash
# Kolla att HMI har skrivit data till PLC:
# - Operatörer måste vara inloggade på HMI
# - Produkt måste vara vald
# - Y334 måste ha pulsats (IBC färdig)
```

---

## 📚 Dokumentation

- **Fullständig guide**: `FX5_IMPLEMENTATION_GUIDE.md`
- **Register-mappning**: Se tabell i guiden
- **KPI-formler**: Effektivitet, Produktivitet, Kvalitet, Bonus
- **Test-script**: `test_fx5.php`

---

## 🎯 Register-sammanfattning

| Register | Vad | Exempel |
|----------|-----|---------|
| D4000 | Operatör 1 (Tvättplats) | 12345 |
| D4001 | Operatör 2 (Kontrollstation) | 67890 |
| D4002 | Operatör 3 (Truckförare) | 11223 |
| D4003 | Produkt | 1 (FoodGrade) |
| D4004 | IBC OK | 95 |
| D4005 | IBC Ej OK | 5 |
| D4006 | Bur Ej OK | 2 |
| D4007 | Runtime | 120 (min) |
| D4008 | Rasttime | 15 (min) |
| D4009 | Löpnummer | 1234 |

**Läses med:**
```php
$modbus->readMultipleRegisters(0, 4000, 10);
```

**Adressering:** D4000 = ModbusTCP Holding Register 4000 (direkt mapping)

---

**LYCKA TILL!** 🚀

Om något går fel, kolla logs:
```bash
tail -f /var/log/php/error.log
tail -f /var/log/apache2/error.log
```
