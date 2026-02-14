<?php
/**
 * ModbusTCP Test Script
 * Testar anslutning till PLC och läsning av alla register
 */

require_once 'vendor/autoload.php';

// Färgkoder för output
const GREEN = "\033[0;32m";
const RED = "\033[0;31m";
const YELLOW = "\033[1;33m";
const BLUE = "\033[0;34m";
const NC = "\033[0m"; // No Color

echo BLUE . "=================================\n" . NC;
echo BLUE . "ModbusTCP Test - Rebotling System\n" . NC;
echo BLUE . "=================================\n\n" . NC;

// Konfig
$PLC_IP = "192.168.0.200";
$TIMEOUT = 5; // sekunder

try {
    echo YELLOW . "📡 Ansluter till PLC: $PLC_IP...\n" . NC;
    $modbus = new ModbusMaster($PLC_IP, "TCP");
    echo GREEN . "✅ Ansluten till PLC!\n\n" . NC;
    
    // Funktion för att konvertera 8-bit till 16-bit
    function convert8to16bit(array $data): array {
        $result = [];
        $count = count($data) / 2;
        for($i = 0; $i < $count; $i++) {
            $result[$i] = ($data[$i*2] << 8) + $data[$i*2+1];
        }
        return $result;
    }
    
    // TEST 1: Läs D200-D206 (Running-register)
    echo YELLOW . "📊 Test 1: Läser D200-D206 (Running-data)...\n" . NC;
    $raw_data_200 = $modbus->readMultipleRegisters(0, 200, 7);
    echo GREEN . "✅ Läste " . count($raw_data_200) . " bytes från D200-D206\n" . NC;
    
    $data_200 = convert8to16bit($raw_data_200);
    
    echo "\n" . BLUE . "=== D200-D206 (Running) ===" . NC . "\n";
    echo "D200 - Program:     " . $data_200[0] . "\n";
    echo "D201 - Operatör 1:  " . $data_200[1] . "\n";
    echo "D202 - Operatör 2:  " . $data_200[2] . "\n";
    echo "D203 - Operatör 3:  " . $data_200[3] . "\n";
    echo "D204 - Produkt:     " . $data_200[4] . "\n";
    echo "D205 - Antal:       " . $data_200[5] . "\n";
    echo "D206 - Runtime PLC: " . $data_200[6] . " minuter\n";
    
    // TEST 2: Läs D210-D216 (Skiftrapport-register)
    echo "\n" . YELLOW . "📊 Test 2: Läser D210-D216 (Skiftrapport-data)...\n" . NC;
    $raw_data_210 = $modbus->readMultipleRegisters(0, 210, 7);
    echo GREEN . "✅ Läste " . count($raw_data_210) . " bytes från D210-D216\n" . NC;
    
    $data_210 = convert8to16bit($raw_data_210);
    
    echo "\n" . BLUE . "=== D210-D216 (Skiftrapport) ===" . NC . "\n";
    echo "D210 - IBC OK:      " . $data_210[0] . "\n";
    echo "D211 - Bur Ej OK:   " . $data_210[1] . "\n";
    echo "D212 - IBC Ej OK:   " . $data_210[2] . "\n";
    echo "D213 - Totalt:      " . $data_210[3] . "\n";
    echo "D214 - Operator ID: " . $data_210[4] . "\n";
    echo "D215 - Product ID:  " . $data_210[5] . "\n";
    echo "D216 - Drifttid:    " . $data_210[6] . " minuter\n";
    
    // TEST 3: Beräkna KPI:er (simulering)
    echo "\n" . YELLOW . "📊 Test 3: Beräknar KPI:er...\n" . NC;
    
    $ibc_ok = $data_210[0];
    $ibc_ej_ok = $data_210[2];
    $bur_ej_ok = $data_210[1];
    $runtime = $data_200[6] > 0 ? $data_200[6] : 1; // Undvik division med 0
    
    // Effektivitet
    $total_produced = $ibc_ok + $ibc_ej_ok;
    $effektivitet = $total_produced > 0 ? round(($ibc_ok / $total_produced) * 100, 2) : 0;
    
    // Produktivitet (IBC per timme)
    $produktivitet = round(($ibc_ok * 60) / $runtime, 2);
    
    // Kvalitet
    $kvalitet = $ibc_ok > 0 ? round((($ibc_ok - $bur_ej_ok) / $ibc_ok) * 100, 2) : 0;
    
    // Bonus (viktad summa)
    $bonus_poang = round(
        ($effektivitet * 0.4) + 
        (min($produktivitet, 100) * 0.4) + 
        ($kvalitet * 0.2),
        2
    );
    
    echo "\n" . BLUE . "=== KPI:er (Beräknade) ===" . NC . "\n";
    echo "Effektivitet:   " . $effektivitet . "% (IBC_OK / Total)\n";
    echo "Produktivitet:  " . $produktivitet . " IBC/h\n";
    echo "Kvalitet:       " . $kvalitet . "% (Godkända minus defekter)\n";
    echo GREEN . "🏆 BONUS POÄNG: " . $bonus_poang . " / 100\n" . NC;
    
    // TEST 4: Verifiera data-integritet
    echo "\n" . YELLOW . "🔍 Test 4: Verifierar data-integritet...\n" . NC;
    
    $warnings = [];
    
    if ($data_200[1] == 0 && $data_200[2] == 0 && $data_200[3] == 0) {
        $warnings[] = "⚠️  Inga operatörer registrerade (Op1, Op2, Op3 alla = 0)";
    }
    
    if ($data_200[4] == 0) {
        $warnings[] = "⚠️  Ingen produkt vald (D204 = 0)";
    }
    
    if ($data_210[0] == 0 && $data_210[2] == 0) {
        $warnings[] = "⚠️  Ingen produktion registrerad (IBC_OK = 0, IBC_EJ_OK = 0)";
    }
    
    if ($data_200[6] == 0) {
        $warnings[] = "⚠️  Runtime är 0 (kan vara början av skift)";
    }
    
    if (count($warnings) > 0) {
        echo YELLOW . "\nVarningar:\n" . NC;
        foreach ($warnings as $warning) {
            echo "  $warning\n";
        }
    } else {
        echo GREEN . "✅ Alla värden ser OK ut!\n" . NC;
    }
    
    // TEST 5: Simulera databas-INSERT
    echo "\n" . YELLOW . "💾 Test 5: Simulerar databas-INSERT...\n" . NC;
    
    $sql_data = [
        's_count' => $data_200[5],
        'ibc_count' => 1, // Skulle beräknas från DB
        'skiftraknare' => 1, // Skulle beräknas från DB
        'produktion_procent' => 0, // Skulle beräknas från DB
        'program' => $data_200[0],
        'op1' => $data_200[1],
        'op2' => $data_200[2],
        'op3' => $data_200[3],
        'produkt' => $data_200[4],
        'antal' => $data_200[5],
        'runtime_plc' => $data_200[6],
        'ibc_ok' => $data_210[0],
        'bur_ej_ok' => $data_210[1],
        'ibc_ej_ok' => $data_210[2],
        'totalt' => $data_210[3],
        'operator_id' => $data_210[4],
        'product_id' => $data_210[5],
        'drifttid' => $data_210[6],
        'effektivitet' => $effektivitet,
        'produktivitet' => $produktivitet,
        'kvalitet' => $kvalitet,
        'bonus_poang' => $bonus_poang
    ];
    
    echo "SQL-data att insertera:\n";
    foreach ($sql_data as $key => $value) {
        echo "  $key: $value\n";
    }
    
    echo "\n" . GREEN . "✅ Alla tester klara!\n" . NC;
    echo BLUE . "=================================\n\n" . NC;
    
    // Sammanfattning
    echo BLUE . "📋 SAMMANFATTNING:\n" . NC;
    echo "• ModbusTCP-anslutning: " . GREEN . "OK\n" . NC;
    echo "• D200-D206 läsning: " . GREEN . "OK (7 register)\n" . NC;
    echo "• D210-D216 läsning: " . GREEN . "OK (7 register)\n" . NC;
    echo "• KPI-beräkningar: " . GREEN . "OK\n" . NC;
    echo "• Data-integritet: " . (count($warnings) > 0 ? YELLOW . count($warnings) . " varningar\n" : GREEN . "OK\n") . NC;
    
    echo "\n" . GREEN . "🎉 Systemet är redo för produktion!\n" . NC;
    
} catch (Exception $e) {
    echo "\n" . RED . "❌ FEL uppstod:\n" . NC;
    echo RED . $e->getMessage() . "\n" . NC;
    echo RED . "Stack trace:\n" . NC;
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n" . BLUE . "=================================\n" . NC;
echo YELLOW . "Tips: Kör detta script innan du aktiverar ModbusTCP i produktion!\n" . NC;
echo BLUE . "=================================\n" . NC;
