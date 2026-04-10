<?php

declare(strict_types=1);

class TvattLinje {
    private WebhookProcessor $processor;
    public ModbusMaster $modbus;
    public PDO $db;

    public function __construct(WebhookProcessor $processor) {
        $this->processor = $processor;
        $this->db = $processor->db;
        $this->modbus = new ModbusMaster("192.168.0.250", "TCP");
    }

    // =====================================================
    // Konvertera PHPModbus 8-bit byte-array → 16-bit int-array
    // =====================================================
    private function convert8to16bit(array $raw): array {
        $result = [];
        $count = (int)(count($raw) / 2);
        for ($i = 0; $i < $count; $i++) {
            $val = ($raw[$i * 2] << 8) + $raw[$i * 2 + 1];
            // Hantera signerade 16-bit-värden (two's complement)
            if ($val > 32767) $val -= 65536;
            $result[$i] = $val;
        }
        return $result;
    }

    // =====================================================
    // Grundläggande validering av PLC-data
    // =====================================================
    private function validatePLCData(int $ibc_ok, int $ibc_ej_ok, int $omtvaatt, int $runtime_plc, int $produkt): void {
        if ($ibc_ok < 0 || $ibc_ej_ok < 0 || $omtvaatt < 0) {
            throw new \RuntimeException("Negativa värden från PLC: ibc_ok={$ibc_ok}, ibc_ej_ok={$ibc_ej_ok}, omtvaatt={$omtvaatt}");
        }
        if ($runtime_plc < 0 || $runtime_plc > 1440) {
            throw new \RuntimeException("Orimlig runtime_plc: {$runtime_plc}");
        }
        $total = $ibc_ok + $ibc_ej_ok + $omtvaatt;
        if ($total > 500) {
            throw new \RuntimeException("Orimligt totalt antal IBC: {$total}");
        }
    }

    // =====================================================
    // handleCycle — triggas vid varje ny IBC (Shelly-puck)
    // Läser D4000-D4009 från PLC via Modbus TCP
    // =====================================================
    public function handleCycle(array $data): void {
        if (!isset($_GET['count'])) {
            throw new InvalidArgumentException('Missing required field: count');
        }

        // === MODBUS TCP — Läs D4000-D4009 (10 register) ===
        $op1 = $op2 = $op3 = $produkt = 0;
        $ibc_ok = $ibc_ej_ok = $omtvaatt = 0;
        $runtime_plc = $rasttime = $lopnummer = 0;

        try {
            $raw_data = $this->modbus->readMultipleRegisters(0, 4000, 10);
            $plc = $this->convert8to16bit($raw_data);

            $op1       = max(0, (int)($plc[0] ?? 0));  // D4000 - Op1 Påsatt
            $op2       = max(0, (int)($plc[1] ?? 0));  // D4001 - Op2 Spolplatform
            $op3       = max(0, (int)($plc[2] ?? 0));  // D4002 - Op3 Kontrollstation
            $produkt   = max(0, (int)($plc[3] ?? 0));  // D4003 - Produkt
            $ibc_ok    = max(0, (int)($plc[4] ?? 0));  // D4004 - IBC OK
            $ibc_ej_ok = max(0, (int)($plc[5] ?? 0));  // D4005 - IBC Ej OK
            $omtvaatt  = max(0, (int)($plc[6] ?? 0));  // D4006 - Omtvätt
            $runtime_plc = max(0, (int)($plc[7] ?? 0)); // D4007 - Runtime (min, excl rast)
            $rasttime  = max(0, (int)($plc[8] ?? 0));  // D4008 - Rasttid (min)
            $lopnummer = max(0, (int)($plc[9] ?? 0));  // D4009 - Löpnummer (max)

            $this->validatePLCData($ibc_ok, $ibc_ej_ok, $omtvaatt, $runtime_plc, $produkt);
        } catch (\Exception $e) {
            error_log("TvattLinje handleCycle Modbus error: " . $e->getMessage());
            // Fortsätt med default-värden — spara ändå cykeln
        }

        // Räkna ut IBC-nummer för dagen
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM tvattlinje_ibc WHERE DATE(datum) = CURDATE()");
        $stmt->execute();
        $dbcount = (int)$stmt->fetchColumn();

        $shellyCount = (int)$_GET['count'];

        if ($dbcount < 1) {
            $ibc_count = 1;
        } else {
            $stmt = $this->db->prepare("
                SELECT s_count FROM tvattlinje_ibc
                WHERE DATE(datum) = CURDATE() AND ibc_count = 1 LIMIT 1
            ");
            $stmt->execute();
            $res = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($res && (int)$res['s_count'] < $shellyCount) {
                $ibc_count = ($shellyCount - (int)$res['s_count']) + 1;
            } else {
                $ibc_count = $dbcount + 1;
            }
        }

        // Hämta skiftraknare från tvattlinje_onoff
        $stmt = $this->db->prepare("
            SELECT skiftraknare FROM tvattlinje_onoff
            WHERE skiftraknare IS NOT NULL ORDER BY datum DESC LIMIT 1
        ");
        $stmt->execute();
        $sr = $stmt->fetch(PDO::FETCH_ASSOC);
        $skiftraknare = ($sr && isset($sr['skiftraknare'])) ? (int)$sr['skiftraknare'] : 1;

        // Beräkna effektivitet (enkel: faktisk takt vs mål-takt)
        $effektivitet = null;
        if ($runtime_plc > 0 && $ibc_ok > 0) {
            // Hämta takt_mal från settings
            $taktMal = 3.0; // default 3 min/IBC
            try {
                $sr2 = $this->db->query("SELECT value FROM tvattlinje_settings WHERE setting = 'takt_mal' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($sr2 && $sr2['value'] > 0) $taktMal = (float)$sr2['value'];
            } catch (\Exception $e) { /* ignorera */ }
            // Effektivitet = (ibc_ok * takt_mal) / runtime_plc * 100
            if ($taktMal > 0) {
                $effektivitet = round(($ibc_ok * $taktMal) / max(1, $runtime_plc) * 100, 2);
            }
        }

        // Spara med alla PLC-fält
        $stmt = $this->db->prepare('
            INSERT INTO tvattlinje_ibc (
                s_count, ibc_count, skiftraknare,
                op1, op2, op3, produkt,
                ibc_ok, ibc_ej_ok, omtvaatt,
                runtime_plc, rasttime, lopnummer, effektivitet
            ) VALUES (
                :s_count, :ibc_count, :skiftraknare,
                :op1, :op2, :op3, :produkt,
                :ibc_ok, :ibc_ej_ok, :omtvaatt,
                :runtime_plc, :rasttime, :lopnummer, :effektivitet
            )
        ');

        $stmt->execute([
            's_count'     => $shellyCount,
            'ibc_count'   => $ibc_count,
            'skiftraknare'=> $skiftraknare,
            'op1'         => $op1 ?: null,
            'op2'         => $op2 ?: null,
            'op3'         => $op3 ?: null,
            'produkt'     => $produkt ?: null,
            'ibc_ok'      => $ibc_ok ?: null,
            'ibc_ej_ok'   => $ibc_ej_ok ?: null,
            'omtvaatt'    => $omtvaatt ?: null,
            'runtime_plc' => $runtime_plc ?: null,
            'rasttime'    => $rasttime ?: null,
            'lopnummer'   => $lopnummer ?: null,
            'effektivitet'=> $effektivitet,
        ]);

        // Kvittera till PLC (D4014 = 0)
        try {
            usleep(500000);
            $this->modbus->writeMultipleRegister(0, 4014, [0], ["INT"]);
        } catch (\Exception $e) {
            error_log("TvattLinje handleCycle: kunde inte nolla D4014: " . $e->getMessage());
        }
    }

    // =====================================================
    // handleRunning — triggas vid start/stopp-signal
    // =====================================================
    public function handleRunning(array $data): void {
        if (!isset($_GET['high'], $_GET['low'])) {
            throw new InvalidArgumentException('Missing required fields high and low for handleRunning');
        }

        $high = (int)$_GET['high'];
        $low  = (int)$_GET['low'];
        $running_param = $_GET["running"] ?? "0";
        $is_running = ($running_param === "true" || $running_param === "1" || $running_param === 1) ? 1 : 0;
        $runtime_today = 0;

        // Hämta senaste entry för att detektera flankor
        $stmt = $this->db->prepare('
            SELECT s_count_l, s_count_h, runtime_today, running, datum, CURRENT_TIMESTAMP as tid
            FROM tvattlinje_onoff
            WHERE DATE(datum) = CURDATE()
            ORDER BY datum DESC
            LIMIT 1
        ');
        $stmt->execute();
        $lastEntry = $stmt->fetch(PDO::FETCH_ASSOC);

        // Dagens nuvarande runtime som bas
        $stmt = $this->db->prepare('
            SELECT COALESCE(MAX(runtime_today), 0) as current_runtime
            FROM tvattlinje_onoff WHERE DATE(datum) = CURDATE()
        ');
        $stmt->execute();
        $runtime_today = (float)$stmt->fetch(PDO::FETCH_ASSOC)['current_runtime'];

        if ($lastEntry) {
            $low_flank_detected = ($low > (int)$lastEntry['s_count_l']);

            if ($low_flank_detected && (int)$lastEntry['running'] === 1) {
                $last_entry_time = new DateTime($lastEntry['datum']);
                $current_time    = new DateTime($lastEntry['tid']);
                $interval        = $last_entry_time->diff($current_time);
                $runtime_period  = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i + round($interval->s / 60, 2);
                $runtime_today  += $runtime_period;
            }
        }

        $stmt = $this->db->prepare('
            INSERT INTO tvattlinje_onoff (s_count_h, s_count_l, runtime_today, running)
            VALUES (:s_count_h, :s_count_l, :runtime_today, :running)
        ');
        $stmt->execute([
            's_count_h'    => $high,
            's_count_l'    => $low,
            'runtime_today'=> $runtime_today,
            'running'      => $is_running
        ]);
    }

    // =====================================================
    // handleRast — triggas vid rast-status-ändring
    // =====================================================
    public function handleRast(array $data): void {
        if (!isset($_GET['rast'])) {
            throw new InvalidArgumentException('Missing required field: rast');
        }

        $rast_status = (int)$_GET['rast']; // 0 = arbetar, 1 = rast

        // Kontrollera om vi ska spara till tvattlinje_rast eller tvattlinje_runtime
        $table = 'tvattlinje_rast';
        try {
            $stmt = $this->db->prepare("SELECT rast_status FROM {$table} ORDER BY datum DESC LIMIT 1");
            $stmt->execute();
            $lastEntry = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Fallback: tvattlinje_runtime
            $table = 'tvattlinje_runtime';
            try {
                $stmt = $this->db->prepare("SELECT rast_status FROM {$table} ORDER BY datum DESC LIMIT 1");
                $stmt->execute();
                $lastEntry = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (\Exception $e2) {
                error_log("TvattLinje handleRast: varken tvattlinje_rast eller tvattlinje_runtime finns: " . $e2->getMessage());
                return;
            }
        }

        $lastStatus = $lastEntry ? (int)$lastEntry['rast_status'] : -1;

        if ($lastStatus !== $rast_status) {
            $stmt = $this->db->prepare("INSERT INTO {$table} (datum, rast_status) VALUES (NOW(), :rast_status)");
            $stmt->execute(['rast_status' => $rast_status]);
        }
    }

    // =====================================================
    // handleSkiftrapport — sparar skiftdata från PLC (D4000-D4011)
    // Triggas via D4015=1 (command register)
    // =====================================================
    public function handleSkiftrapport(array $data): void {
        // Läs D4000-D4011 (12 register)
        $op1 = $op2 = $op3 = $produkt = 0;
        $ibc_ok = $ibc_ej_ok = $omtvaatt = 0;
        $runtime_plc = $rasttime = $lopnummer_max = 0;
        $aktuellt_lopnummer = 0;
        $driftstopptime = 0;

        try {
            $raw_data = $this->modbus->readMultipleRegisters(0, 4000, 12);
            $plc = $this->convert8to16bit($raw_data);

            $op1               = max(0, (int)($plc[0] ?? 0));
            $op2               = max(0, (int)($plc[1] ?? 0));
            $op3               = max(0, (int)($plc[2] ?? 0));
            $produkt           = max(0, (int)($plc[3] ?? 0));
            $ibc_ok            = max(0, (int)($plc[4] ?? 0));
            $ibc_ej_ok         = max(0, (int)($plc[5] ?? 0));
            $omtvaatt          = max(0, (int)($plc[6] ?? 0));
            $runtime_plc       = max(0, (int)($plc[7] ?? 0));
            $rasttime          = max(0, (int)($plc[8] ?? 0));
            $lopnummer_max     = max(0, (int)($plc[9] ?? 0));
            $aktuellt_lopnummer= max(0, (int)($plc[10] ?? 0));
            $driftstopptime    = max(0, (int)($plc[11] ?? 0));
        } catch (\Exception $e) {
            error_log("TvattLinje handleSkiftrapport Modbus error: " . $e->getMessage());
        }

        // Hämta skiftraknare
        $stmt = $this->db->prepare("
            SELECT skiftraknare FROM tvattlinje_onoff
            WHERE skiftraknare IS NOT NULL ORDER BY datum DESC LIMIT 1
        ");
        $stmt->execute();
        $sr = $stmt->fetch(PDO::FETCH_ASSOC);
        $skiftraknare = ($sr && isset($sr['skiftraknare'])) ? (int)$sr['skiftraknare'] : 1;

        $totalt = $ibc_ok + $ibc_ej_ok + $omtvaatt;
        $datum  = date('Y-m-d');

        // Spara skiftrapporten
        $stmt = $this->db->prepare('
            INSERT INTO tvattlinje_skiftrapport (
                datum, antal_ok, antal_ej_ok, omtvaatt, totalt,
                op1, op2, op3, product_id,
                drifttid, rasttime, driftstopptime,
                lopnummer, skiftraknare, inlagd
            ) VALUES (
                :datum, :antal_ok, :antal_ej_ok, :omtvaatt, :totalt,
                :op1, :op2, :op3, :product_id,
                :drifttid, :rasttime, :driftstopptime,
                :lopnummer, :skiftraknare, 1
            )
        ');

        $stmt->execute([
            'datum'         => $datum,
            'antal_ok'      => $ibc_ok,
            'antal_ej_ok'   => $ibc_ej_ok,
            'omtvaatt'      => $omtvaatt,
            'totalt'        => $totalt,
            'op1'           => $op1 ?: null,
            'op2'           => $op2 ?: null,
            'op3'           => $op3 ?: null,
            'product_id'    => $produkt ?: null,
            'drifttid'      => $runtime_plc,
            'rasttime'      => $rasttime,
            'driftstopptime'=> $driftstopptime,
            'lopnummer'     => $lopnummer_max ?: $aktuellt_lopnummer,
            'skiftraknare'  => $skiftraknare,
        ]);

        // Kvittera till PLC
        try {
            usleep(500000);
            $this->modbus->writeMultipleRegister(0, 4014, [0], ["INT"]);
            $this->modbus->writeMultipleRegister(0, 4015, [0], ["INT"]);
        } catch (\Exception $e) {
            error_log("TvattLinje handleSkiftrapport: kunde inte kvittera PLC: " . $e->getMessage());
        }
    }

    // =====================================================
    // handleCommand — läser D4015 och rotar till rätt handler
    // D4015=1: Skiftrapport, D4015=3: Driftstopp start, D4015=4: Driftstopp slut
    // =====================================================
    public function handleCommand(array $data): void {
        $command = 0;
        try {
            $raw_cmd = $this->modbus->readMultipleRegisters(0, 4015, 1);
            $plc_cmd = $this->convert8to16bit($raw_cmd);
            $command = (int)($plc_cmd[0] ?? 0);
        } catch (\Exception $e) {
            error_log("TvattLinje handleCommand: kunde inte läsa D4015: " . $e->getMessage());
            return;
        }

        switch ($command) {
            case 1:
                // Skiftrapport
                $this->handleSkiftrapport($data);
                break;
            case 3:
                // Driftstopp start
                try {
                    $this->db->exec("
                        INSERT INTO tvattlinje_driftstopp (datum, status) VALUES (NOW(), 'start')
                    ");
                } catch (\Exception $e) {
                    error_log("TvattLinje driftstopp start: " . $e->getMessage());
                }
                break;
            case 4:
                // Driftstopp slut
                try {
                    $this->db->exec("
                        INSERT INTO tvattlinje_driftstopp (datum, status) VALUES (NOW(), 'slut')
                    ");
                } catch (\Exception $e) {
                    error_log("TvattLinje driftstopp slut: " . $e->getMessage());
                }
                break;
            default:
                // Okänt kommando — nolla registret
                break;
        }

        // Nolla D4015 och D4014
        try {
            $this->modbus->writeMultipleRegister(0, 4015, [0], ["INT"]);
            $this->modbus->writeMultipleRegister(0, 4014, [0], ["INT"]);
        } catch (\Exception $e) {
            error_log("TvattLinje handleCommand: kunde inte nolla D4015/D4014: " . $e->getMessage());
        }
    }
}
