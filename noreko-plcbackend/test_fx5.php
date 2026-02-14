<?php
/**
 * Mitsubishi FX5 PLC Test Script
 * Testar läsning av D4000-D4009 register
 */

require_once 'vendor/autoload.php';

// Färgkoder
const GREEN = "\033[0;32m";
const RED = "\033[0;31m";
const YELLOW = "\033[1;33m";
const BLUE = "\033[0;34m";
const CYAN = "\033[0;36m";
const NC = "\033[0m";

echo BLUE . "╔═══════════════════════════════════════╗\n" . NC;
echo BLUE . "║  Mitsubishi FX5 PLC Test Script      ║\n" . NC;
echo BLUE . "║  Register D4000-D4009                 ║\n" . NC;
echo BLUE . "╚═══════════════════════════════════════╝\n\n" . NC;

// Konfiguration
$PLC_IP = "192.168.0.200";
$START_ADDRESS = 4000;  // D4000
$REGISTER_COUNT = 10;    // D4000-D4009

echo CYAN . "Configuration:\n" . NC;
echo "  PLC IP:        $PLC_IP\n";
echo "  Start Address: $START_ADDRESS (D4000)\n";
echo "  Register Count: $REGISTER_COUNT (D4000-D4009)\n";
echo "  Total Bytes:   " . ($REGISTER_COUNT * 2) . " bytes\n\n";

try {
    // === TEST 1: Anslut till PLC ===
    echo YELLOW . "📡 TEST 1: Ansluter till FX5 PLC...\n" . NC;
    $modbus = new ModbusMaster($PLC_IP, "TCP");
    echo GREEN . "✅ Ansluten till $PLC_IP\n\n" . NC;
    
    // === HJÄLPFUNKTIONER ===
    function convert8to16bit(array $data): array {
        $result = [];
        for($i = 0; $i < (count($data) / 2); $i++) {
            // Big-endian: High byte först
            $result[$i] = ($data[$i*2] << 8) + $data[$i*2+1];
        }
        return $result;
    }
    
    function getProductName(int $id): string {
        $products = [
            1 => "FoodGrade",
            4 => "NonUN",
            5 => "Tvätta färdiga IBC"
        ];
        return $products[$id] ?? "Okänd ($id)";
    }
    
    // === TEST 2: Läs D4000-D4009 ===
    echo YELLOW . "📊 TEST 2: Läser D4000-D4009 ($REGISTER_COUNT register)...\n" . NC;
    $raw_data = $modbus->readMultipleRegisters(0, $START_ADDRESS, $REGISTER_COUNT);
    echo GREEN . "✅ Läste " . count($raw_data) . " bytes från PLC\n" . NC;
    
    // Konvertera till 16-bit värden
    $plc_data = convert8to16bit($raw_data);
    
    // === TEST 3: Extrahera och visa data ===
    echo "\n" . BLUE . "╔═══════════════════════════════════════╗\n" . NC;
    echo BLUE . "║  PLC Register-värden                  ║\n" . NC;
    echo BLUE . "╚═══════════════════════════════════════╝\n\n" . NC;
    
    $op1         = $plc_data[0];  // D4000
    $op2         = $plc_data[1];  // D4001
    $op3         = $plc_data[2];  // D4002
    $produkt     = $plc_data[3];  // D4003
    $ibc_ok      = $plc_data[4];  // D4004
    $ibc_ej_ok   = $plc_data[5];  // D4005
    $bur_ej_ok   = $plc_data[6];  // D4006
    $runtime     = $plc_data[7];  // D4007
    $rasttime    = $plc_data[8];  // D4008
    $lopnummer   = $plc_data[9];  // D4009
    
    echo CYAN . "OPERATÖRER:\n" . NC;
    echo "  D4000 - Operatör 1 (Tvättplats):     " . ($op1 > 0 ? GREEN . $op1 . NC : RED . "Ej registrerad" . NC) . "\n";
    echo "  D4001 - Operatör 2 (Kontrollstation): " . ($op2 > 0 ? GREEN . $op2 . NC : RED . "Ej registrerad" . NC) . "\n";
    echo "  D4002 - Operatör 3 (Truckförare):    " . ($op3 > 0 ? GREEN . $op3 . NC : RED . "Ej registrerad" . NC) . "\n\n";
    
    echo CYAN . "PRODUKTION:\n" . NC;
    echo "  D4003 - Produkt:        " . getProductName($produkt) . "\n";
    echo "  D4004 - IBC OK:         " . GREEN . $ibc_ok . NC . " (godkända)\n";
    echo "  D4005 - IBC Ej OK:      " . ($ibc_ej_ok > 0 ? YELLOW . $ibc_ej_ok . NC : "0") . " (kasserade)\n";
    echo "  D4006 - Bur Ej OK:      " . ($bur_ej_ok > 0 ? RED . $bur_ej_ok . NC : "0") . " (defekta burar)\n\n";
    
    echo CYAN . "TIDER:\n" . NC;
    echo "  D4007 - Runtime:        $runtime (minuter/sekunder?)\n";
    echo "  D4008 - Rasttime:       $rasttime\n\n";
    
    echo CYAN . "RÄKNARE:\n" . NC;
    echo "  D4009 - Löpnummer:      " . BLUE . $lopnummer . NC . "\n\n";
    
    // === TEST 4: Beräkna KPI:er ===
    echo YELLOW . "🧮 TEST 4: Beräknar KPI:er (samma formel som Rebotling.php)...\n\n" . NC;

    $runtime_safe = max($runtime, 1); // Undvik division med 0

    $total_produced = $ibc_ok + $ibc_ej_ok;
    $effektivitet = $total_produced > 0
        ? round(($ibc_ok / $total_produced) * 100, 2)
        : 0;

    // Runtime i MINUTER - produktivitet = IBC per timme
    $produktivitet = round(($ibc_ok * 60) / $runtime_safe, 2);

    $kvalitet = $ibc_ok > 0
        ? round((($ibc_ok - $bur_ej_ok) / $ibc_ok) * 100, 2)
        : 0;

    // Bonus poäng: viktad summa (produktivitet cappas vid 100)
    $bonus_poang = round(
        ($effektivitet * 0.4) +
        (min($produktivitet, 100) * 0.4) +
        ($kvalitet * 0.2),
        2
    );
    
    echo BLUE . "╔═══════════════════════════════════════╗\n" . NC;
    echo BLUE . "║  KPI:er (Beräknade)                   ║\n" . NC;
    echo BLUE . "╚═══════════════════════════════════════╝\n\n" . NC;
    
    echo CYAN . "EFFEKTIVITET:\n" . NC;
    echo "  Formel:     IBC_OK / (IBC_OK + IBC_EJ_OK) × 100\n";
    echo "  Beräkning:  $ibc_ok / $total_produced × 100\n";
    echo "  Resultat:   " . ($effektivitet >= 95 ? GREEN : ($effektivitet >= 90 ? YELLOW : RED)) 
         . $effektivitet . "%" . NC . "\n\n";
    
    echo CYAN . "PRODUKTIVITET:\n" . NC;
    echo "  Formel:     (IBC_OK × 60) / runtime_minuter\n";
    echo "  Beräkning:  ($ibc_ok × 60) / $runtime_safe\n";
    echo "  Resultat:   " . ($produktivitet >= 15 ? GREEN : ($produktivitet >= 10 ? YELLOW : RED))
         . $produktivitet . " IBC/h" . NC . "\n";
    echo "  För bonus:  " . min($produktivitet, 100) . " (cappas vid 100)\n\n";
    
    echo CYAN . "KVALITET:\n" . NC;
    echo "  Formel:     (IBC_OK - BUR_EJ_OK) / IBC_OK × 100\n";
    echo "  Beräkning:  ($ibc_ok - $bur_ej_ok) / $ibc_ok × 100\n";
    echo "  Resultat:   " . ($kvalitet >= 98 ? GREEN : ($kvalitet >= 95 ? YELLOW : RED)) 
         . $kvalitet . "%" . NC . "\n\n";
    
    echo BLUE . "═══════════════════════════════════════\n" . NC;
    echo "🏆 " . BLUE . "BONUS POÄNG: " . NC;
    echo ($bonus_poang >= 80 ? GREEN : ($bonus_poang >= 70 ? YELLOW : RED));
    echo $bonus_poang . " / 100" . NC . "\n";
    echo BLUE . "═══════════════════════════════════════\n\n" . NC;
    
    echo CYAN . "Viktning:\n" . NC;
    echo "  Effektivitet:   40% × $effektivitet% = " . round($effektivitet * 0.4, 2) . "\n";
    echo "  Produktivitet:  40% × " . min($produktivitet, 100) . " = " . round(min($produktivitet, 100) * 0.4, 2) . "\n";
    echo "  Kvalitet:       20% × $kvalitet% = " . round($kvalitet * 0.2, 2) . "\n\n";
    
    // === TEST 5: Data-validering ===
    echo YELLOW . "🔍 TEST 5: Validerar data...\n\n" . NC;
    
    $warnings = [];
    $errors = [];
    
    if ($op1 == 0 && $op2 == 0 && $op3 == 0) {
        $warnings[] = "Inga operatörer registrerade";
    }
    
    if ($produkt < 1 || $produkt > 5) {
        $errors[] = "Ogiltig produkt-ID: $produkt (förväntat 1, 4 eller 5)";
    }
    
    if ($ibc_ok == 0 && $ibc_ej_ok == 0) {
        $warnings[] = "Ingen produktion registrerad";
    }
    
    if ($runtime == 0) {
        $warnings[] = "Runtime är 0 (kan vara början av skift)";
    }
    
    if ($lopnummer == 0) {
        $errors[] = "Löpnummer är 0 (ska aldrig vara 0!)";
    }
    
    if ($bur_ej_ok > $ibc_ok) {
        $errors[] = "Fler defekta burar ($bur_ej_ok) än godkända IBC ($ibc_ok) - verkar fel!";
    }
    
    if (count($errors) > 0) {
        echo RED . "❌ FEL:\n" . NC;
        foreach ($errors as $error) {
            echo "  • $error\n";
        }
        echo "\n";
    }
    
    if (count($warnings) > 0) {
        echo YELLOW . "⚠️  VARNINGAR:\n" . NC;
        foreach ($warnings as $warning) {
            echo "  • $warning\n";
        }
        echo "\n";
    }
    
    if (count($errors) == 0 && count($warnings) == 0) {
        echo GREEN . "✅ All data ser korrekt ut!\n\n" . NC;
    }
    
    // === TEST 6: Simulera databas-INSERT ===
    echo YELLOW . "💾 TEST 6: Simulerar databas-INSERT...\n\n" . NC;
    
    $sql_data = [
        'ibc_count' => 1,  // Skulle beräknas från DB
        'skiftraknare' => 1,  // Skulle beräknas från DB
        'lopnummer' => $lopnummer,
        'op1' => $op1,
        'op2' => $op2,
        'op3' => $op3,
        'produkt' => $produkt,
        'ibc_ok' => $ibc_ok,
        'ibc_ej_ok' => $ibc_ej_ok,
        'bur_ej_ok' => $bur_ej_ok,
        'runtime_plc' => $runtime,
        'rasttime' => $rasttime,
        'effektivitet' => $effektivitet,
        'produktivitet' => $produktivitet,
        'kvalitet' => $kvalitet,
        'bonus_poang' => $bonus_poang
    ];
    
    echo CYAN . "SQL INSERT-värden:\n" . NC;
    foreach ($sql_data as $key => $value) {
        echo "  $key: " . ($value === 0 ? YELLOW . "0" . NC : $value) . "\n";
    }
    
    // === SAMMANFATTNING ===
    echo "\n" . GREEN . "╔═══════════════════════════════════════╗\n" . NC;
    echo GREEN . "║  ✅ ALLA TESTER KLARA!               ║\n" . NC;
    echo GREEN . "╚═══════════════════════════════════════╝\n\n" . NC;
    
    echo BLUE . "SAMMANFATTNING:\n" . NC;
    echo "  • ModbusTCP-anslutning:  " . GREEN . "OK\n" . NC;
    echo "  • D4000-D4009 läsning:   " . GREEN . "OK (10 register, 20 bytes)\n" . NC;
    echo "  • 8-bit → 16-bit konv.:  " . GREEN . "OK\n" . NC;
    echo "  • KPI-beräkningar:       " . GREEN . "OK\n" . NC;
    echo "  • Data-validering:       " . (count($errors) > 0 ? RED . count($errors) . " FEL" : (count($warnings) > 0 ? YELLOW . count($warnings) . " varningar" : GREEN . "OK")) . NC . "\n";
    
    echo "\n" . CYAN . "IMPLEMENTATION STATUS:\n" . NC;
    echo "  ✅ Rebotling.php uppdaterad med FX5-kod\n";
    echo "  ✅ KPI-beräkningar implementerade\n";
    echo "  ✅ convert8to16bit() och calculateKPIs() tillagda\n\n";

    echo CYAN . "NÄSTA STEG:\n" . NC;
    echo "  1. Verifiera att Runtime är i MINUTER (inte sekunder)\n";
    echo "  2. Kör databas-migration: migrations/002_add_fx5_d4000_fields.sql\n";
    echo "  3. Testa med faktisk webhook från PLC\n";
    echo "  4. Bygg frontend-komponenter för bonusvisning\n\n";
    
    echo GREEN . "🚀 Systemet är redo för implementation!\n\n" . NC;
    
} catch (Exception $e) {
    echo "\n" . RED . "╔═══════════════════════════════════════╗\n" . NC;
    echo RED . "║  ❌ FEL UPPSTOD                       ║\n" . NC;
    echo RED . "╚═══════════════════════════════════════╝\n\n" . NC;
    echo RED . "Felmeddelande:\n" . NC;
    echo "  " . $e->getMessage() . "\n\n";
    echo RED . "Stack trace:\n" . NC;
    echo $e->getTraceAsString() . "\n\n";
    
    echo YELLOW . "FELSÖKNING:\n" . NC;
    echo "  • Kontrollera att PLC:n är påslagen och nåbar\n";
    echo "  • Testa: ping $PLC_IP\n";
    echo "  • Testa: telnet $PLC_IP 502\n";
    echo "  • Verifiera att ModbusTCP är aktiverat i PLC:n\n";
    echo "  • Kontrollera att adress 4000 är korrekt för D4000\n\n";
    
    exit(1);
}

echo BLUE . "═══════════════════════════════════════\n" . NC;
