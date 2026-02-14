# 🚀 SNABBSTART - ModbusTCP Implementation

## ⚡ Gå från 0 till production i 4 steg!

---

## STEG 1: Databas-migration (5 min)

```bash
cd /home/clawd/clawd/mauserdb/migrations
mysql -u aiab -pNoreko2025 -h localhost -P 33061 < 001_add_modbus_fields_to_rebotling_ibc.sql
```

**Verifiera:**
```bash
mysql -u aiab -pNoreko2025 -h localhost -P 33061 -e "DESCRIBE mauserdb.rebotling_ibc;" | grep -E "operator_id|ibc_ok|bonus"
```

**Förväntat resultat:**
```
operator_id   INT
ibc_ok        INT
bur_ej_ok     INT
ibc_ej_ok     INT
effektivitet  DECIMAL(5,2)
produktivitet DECIMAL(5,2)
kvalitet      DECIMAL(5,2)
bonus_poang   DECIMAL(5,2)
```

✅ **Klar!** Databas har nu alla kolumner för PLC-data.

---

## STEG 2: Testa ModbusTCP (10 min)

```bash
cd /home/clawd/clawd/mauserdb/noreko-plcbackend
php test_modbus.php
```

**Förväntat resultat:**
```
✅ Ansluten till PLC!
✅ Läste 14 bytes från D200-D206
✅ Läste 14 bytes från D210-D216

=== D200-D206 (Running) ===
Program: 1
Op1: 123
Op2: 456
Op3: 0
Produkt: 5
Antal: 42
Runtime PLC: 120

=== D210-D216 (Skiftrapport) ===
IBC OK: 95
Bur Ej OK: 2
IBC Ej OK: 5
Totalt: 100
Operator ID: 123
Product ID: 5
Drifttid: 118

=== KPI:er (Beräknade) ===
Effektivitet: 95%
Produktivitet: 47.5 IBC/h
Kvalitet: 97.89%
🏆 BONUS POÄNG: 76.58 / 100

✅ Alla tester klara!
```

**Om fel uppstår:**
- `Connection refused` → Kolla att PLC IP är rätt (192.168.0.200)
- `Timeout` → Kontrollera nätverksanslutning till PLC
- `Permission denied` → Kör som rätt användare

✅ **Klar!** ModbusTCP-anslutning fungerar!

---

## STEG 3: Uppdatera Rebotling.php (30 min)

### 3.1 Backup befintlig fil
```bash
cd /home/clawd/clawd/mauserdb/noreko-plcbackend
cp Rebotling.php Rebotling.php.backup
```

### 3.2 Öppna Rebotling.php och hitta handleCycle()
```bash
nano Rebotling.php
# Eller:
code Rebotling.php
```

### 3.3 Lägg till ModbusTCP-läsning i handleCycle()

**HITTA DENNA KOD** (runt rad 90):
```php
public function handleCycle(array $data): void {
    // Validera data
    if (!isset($_GET['count'])) {
        throw new InvalidArgumentException('Missing required fields for user.created');
    }
```

**LÄGG TILL DIREKT EFTER VALIDERINGEN:**
```php
    // === MODBUS TCP - LÄS ALLA REGISTER ===
    try {
        $this->modbus = new ModbusMaster("192.168.0.200", "TCP");
        
        // Läs D200-D206 (Running)
        $raw_data_200 = $this->modbus->readMultipleRegisters(0, 200, 7);
        $data_200 = $this->convert8to16bit($raw_data_200);
        
        // Läs D210-D216 (Skiftrapport)
        $raw_data_210 = $this->modbus->readMultipleRegisters(0, 210, 7);
        $data_210 = $this->convert8to16bit($raw_data_210);
        
        // Extrahera värden
        $program = $data_200[0];
        $op1 = $data_200[1];
        $op2 = $data_200[2];
        $op3 = $data_200[3];
        $produkt = $data_200[4];
        $antal = $data_200[5];
        $runtime_plc = $data_200[6];
        
        $ibc_ok = $data_210[0];
        $bur_ej_ok = $data_210[1];
        $ibc_ej_ok = $data_210[2];
        $totalt = $data_210[3];
        $operator_id = $data_210[4];
        $product_id = $data_210[5];
        $drifttid = $data_210[6];
        
        // Beräkna KPI:er
        $kpis = $this->calculateKPIs([
            'ibc_ok' => $ibc_ok,
            'ibc_ej_ok' => $ibc_ej_ok,
            'bur_ej_ok' => $bur_ej_ok,
            'runtime_plc' => $runtime_plc
        ]);
        
    } catch (Exception $e) {
        error_log("ModbusTCP Error: " . $e->getMessage());
        // Fallback: sätt default-värden
        $program = $op1 = $op2 = $op3 = $produkt = $antal = $runtime_plc = 0;
        $ibc_ok = $bur_ej_ok = $ibc_ej_ok = $totalt = $operator_id = $product_id = $drifttid = 0;
        $kpis = ['effektivitet' => 0, 'produktivitet' => 0, 'kvalitet' => 0, 'bonus_poang' => 0];
    }
```

### 3.4 Uppdatera SQL INSERT-statement

**HITTA DENNA KOD** (runt rad 160):
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
    'ibc_count' => $ibc_count,
    'skiftraknare' => $skiftraknare,
    'produktion_procent' => $produktion_procent,
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
```

### 3.5 Lägg till hjälpfunktioner (längst ner i klassen)

```php
    // Konvertera 8-bit till 16-bit
    private function convert8to16bit(array $data): array {
        $result = [];
        for($i = 0; $i < (count($data) / 2); $i++) {
            $result[$i] = ($data[$i*2] << 8) + $data[$i*2+1];
        }
        return $result;
    }

    // Beräkna KPI:er
    private function calculateKPIs(array $data): array {
        $ibc_ok = $data['ibc_ok'] ?? 0;
        $ibc_ej_ok = $data['ibc_ej_ok'] ?? 0;
        $bur_ej_ok = $data['bur_ej_ok'] ?? 0;
        $runtime = max($data['runtime_plc'] ?? 1, 1); // Undvik division med 0
        
        $total_produced = $ibc_ok + $ibc_ej_ok;
        $effektivitet = $total_produced > 0 ? round(($ibc_ok / $total_produced) * 100, 2) : 0;
        $produktivitet = round(($ibc_ok * 60) / $runtime, 2);
        $kvalitet = $ibc_ok > 0 ? round((($ibc_ok - $bur_ej_ok) / $ibc_ok) * 100, 2) : 0;
        
        $bonus_poang = round(
            ($effektivitet * 0.4) + 
            (min($produktivitet, 100) * 0.4) + 
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

**Spara filen!**

✅ **Klar!** Backend kan nu läsa och spara PLC-data.

---

## STEG 4: Testa i produktion (10 min)

### 4.1 Simulera ett webhook-anrop
```bash
curl -X POST "http://localhost/noreko-plcbackend/v1.php?line=rebotling&type=cycle&count=123"
```

### 4.2 Kontrollera databas
```bash
mysql -u aiab -pNoreko2025 -h localhost -P 33061 -e "
SELECT 
    datum,
    operator_id,
    ibc_ok,
    ibc_ej_ok,
    effektivitet,
    produktivitet,
    kvalitet,
    bonus_poang
FROM mauserdb.rebotling_ibc
ORDER BY datum DESC
LIMIT 5;
"
```

**Förväntat resultat:**
```
+---------------------+-------------+--------+------------+--------------+---------------+---------+-------------+
| datum               | operator_id | ibc_ok | ibc_ej_ok  | effektivitet | produktivitet | kvalitet| bonus_poang |
+---------------------+-------------+--------+------------+--------------+---------------+---------+-------------+
| 2024-02-09 14:32:01 |         123 |     95 |          5 |        95.00 |         47.50 |   97.89 |       76.58 |
+---------------------+-------------+--------+------------+--------------+---------------+---------+-------------+
```

✅ **KLART!** Systemet är live och sparar PLC-data!

---

## 📊 BONUSSTEG: Dashboard API

Skapa nya API-endpoints för dashboard:

```bash
cd /home/clawd/clawd/mauserdb/noreko-backend
nano routes/rebotling.js
```

**Lägg till:**
```javascript
// Operatörs-prestanda
router.get('/operator-stats/:operatorId', async (req, res) => {
    const sql = `
        SELECT 
            DATE(datum) as dag,
            COUNT(*) as antal_cykler,
            SUM(ibc_ok) as totalt_ibc_ok,
            SUM(ibc_ej_ok) as totalt_ibc_ej_ok,
            AVG(effektivitet) as avg_effektivitet,
            AVG(produktivitet) as avg_produktivitet,
            AVG(kvalitet) as avg_kvalitet,
            AVG(bonus_poang) as avg_bonus
        FROM rebotling_ibc
        WHERE operator_id = ?
        AND datum >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(datum)
        ORDER BY datum DESC
    `;
    const [rows] = await db.query(sql, [req.params.operatorId]);
    res.json(rows);
});

// Bonus-ranking (topplista)
router.get('/bonus-ranking', async (req, res) => {
    const sql = `
        SELECT 
            r.operator_id,
            u.name as operator_namn,
            COUNT(*) as antal_cykler,
            SUM(r.ibc_ok) as totalt_ibc_ok,
            AVG(r.bonus_poang) as avg_bonus,
            SUM(r.bonus_poang) as total_bonus
        FROM rebotling_ibc r
        LEFT JOIN users u ON r.operator_id = u.id
        WHERE r.datum >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND r.operator_id IS NOT NULL
        GROUP BY r.operator_id
        ORDER BY total_bonus DESC
        LIMIT 10
    `;
    const [rows] = await db.query(sql);
    res.json(rows);
});
```

**Testa API:**
```bash
curl http://localhost:3000/api/rebotling/operator-stats/123
curl http://localhost:3000/api/rebotling/bonus-ranking
```

---

## 🎯 SAMMANFATTNING

Du har nu:
✅ Databas-schema uppdaterat med alla PLC-fält  
✅ ModbusTCP-anslutning testad och fungerande  
✅ Backend läser 14 register per cykel (D200-D206 + D210-D216)  
✅ KPI:er beräknas automatiskt (effektivitet, produktivitet, kvalitet)  
✅ Bonuspoäng räknas ut från viktad summa av KPI:er  
✅ API-endpoints för dashboard-visualisering  

---

## 📚 Dokumentation

- **Detaljerad plan**: `MODBUS_EXPANSION_PLAN.md`
- **Register-mappning**: `PLC_REGISTER_MAPPING.md`
- **Test-script**: `noreko-plcbackend/test_modbus.php`
- **SQL-migration**: `migrations/001_add_modbus_fields_to_rebotling_ibc.sql`

---

## 🆘 Felsökning

**Problem: ModbusTCP timeout**
```bash
ping 192.168.0.200
telnet 192.168.0.200 502
```

**Problem: Databas-fel**
```bash
tail -f /var/log/php/error.log
tail -f /var/log/mysql/error.log
```

**Problem: Data sparas inte**
```bash
# Kontrollera PHP syntax:
php -l Rebotling.php

# Kör test-script:
php test_modbus.php
```

---

## 🚀 GO LIVE!

När allt funkar i test:
1. Backup produktion-databas
2. Kör migration på produktion
3. Deploya uppdaterad Rebotling.php
4. Övervaka logs i 1 timme
5. Verifiera att data sparas korrekt

**LYCKA TILL!** 🎉
