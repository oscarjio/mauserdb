<?php

declare(strict_types=1);

class TvattLinje {
    public ModbusMaster $modbus;
    public PDO $db;

    private const PLC_IP      = '192.168.10.23';
    private const PLC_UNIT_ID = 0;

    public function __construct(WebhookProcessor $processor) {
        $this->db = $processor->db;
    }

    // ─── Diagnostiklogg ─────────────────────────────────────────────────────
    private function log(string $ctx, string $msg, array $data = []): void {
        $line = "[TvattLinje:{$ctx}] {$msg}";
        if ($data) {
            $line .= ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        error_log($line);
    }

    // ─── 8-bit byte-array → 16-bit int-array (two's complement) ────────────
    private function convert8to16bit(array $raw): array {
        $result = [];
        $count  = (int)(count($raw) / 2);
        for ($i = 0; $i < $count; $i++) {
            $val = ($raw[$i * 2] << 8) + $raw[$i * 2 + 1];
            if ($val > 32767) $val -= 65536;
            $result[$i] = $val;
        }
        return $result;
    }

    // ─── Läs Modbus-register med diagnostikloggning ─────────────────────────
    // Returnerar parsade 16-bit-värden, eller null vid fel.
    private function readRegisters(string $ctx, int $startReg, int $count): ?array {
        $this->log($ctx, "Modbus READ försök", [
            'ip'    => self::PLC_IP,
            'start' => "D{$startReg}",
            'count' => $count,
        ]);
        try {
            $this->modbus = new ModbusMaster(self::PLC_IP, "TCP");
            usleep(500000);
            $raw = $this->modbus->readMultipleRegisters(self::PLC_UNIT_ID, $startReg, $count);
            $plc = $this->convert8to16bit($raw);
            $this->log($ctx, "Modbus READ OK", [
                'start'  => "D{$startReg}",
                'values' => $plc,
            ]);
            return $plc;
        } catch (\Exception $e) {
            $this->log($ctx, "Modbus READ MISSLYCKADES", [
                'ip'    => self::PLC_IP,
                'start' => "D{$startReg}",
                'count' => $count,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ─── Skriv Modbus-register med diagnostikloggning ────────────────────────
    private function writeRegister(string $ctx, int $reg, int $value): void {
        $this->log($ctx, "Modbus WRITE försök", ['reg' => "D{$reg}", 'value' => $value]);
        try {
            $this->modbus = new ModbusMaster(self::PLC_IP, "TCP");
            usleep(500000);
            $this->modbus->writeMultipleRegister(self::PLC_UNIT_ID, $reg, [$value], ["INT"]);
            $this->log($ctx, "Modbus WRITE OK", ['reg' => "D{$reg}", 'value' => $value]);
        } catch (\Exception $e) {
            $this->log($ctx, "Modbus WRITE MISSLYCKADES", [
                'reg'   => "D{$reg}",
                'value' => $value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ─── Validering av PLC-data ──────────────────────────────────────────────
    private function validatePLCData(string $ctx, int $ibc_ok, int $ibc_ej_ok, int $omtvaatt, int $runtime_plc, int $produkt): void {
        if ($ibc_ok < 0 || $ibc_ej_ok < 0 || $omtvaatt < 0) {
            throw new \RuntimeException("Negativa värden från PLC: ibc_ok={$ibc_ok}, ibc_ej_ok={$ibc_ej_ok}, omtvaatt={$omtvaatt}");
        }
        if ($runtime_plc < 0 || $runtime_plc > 1440) {
            throw new \RuntimeException("Orimlig runtime_plc={$runtime_plc} (max 1440 min/dag)");
        }
        $total = $ibc_ok + $ibc_ej_ok + $omtvaatt;
        if ($total > 500) {
            throw new \RuntimeException("Orimligt totalt IBC={$total} (max 500)");
        }
        $this->log($ctx, "Validering OK", [
            'ibc_ok'    => $ibc_ok,
            'ibc_ej_ok' => $ibc_ej_ok,
            'omtvaatt'  => $omtvaatt,
            'runtime'   => $runtime_plc,
            'produkt'   => $produkt,
        ]);
    }

    // =====================================================
    // handleCycle — triggas vid varje ny IBC (Shelly-puck)
    // Läser D4000-D4009 från PLC via Modbus TCP
    // =====================================================
    public function handleCycle(array $_data): void {
        if (!isset($_GET['count'])) {
            throw new InvalidArgumentException('Missing required field: count');
        }

        $shellyCount = (int)$_GET['count'];
        $this->log('handleCycle', "Cycle webhook mottagen", ['shelly_count' => $shellyCount]);

        $op1 = $op2 = $op3 = $produkt = 0;
        $ibc_ok = $ibc_ej_ok = $omtvaatt = 0;
        $runtime_plc = $rasttime = $lopnummer = $driftstopptime = 0;
        $modbusOk = false;

        $plc = $this->readRegisters('handleCycle', 4000, 12);
        if ($plc !== null) {
            $op1            = max(0, (int)($plc[0]  ?? 0));  // D4000 - Op1 Påsatt
            $op2            = max(0, (int)($plc[1]  ?? 0));  // D4001 - Op2 Spolplatform
            $op3            = max(0, (int)($plc[2]  ?? 0));  // D4002 - Op3 Kontrollstation
            $produkt        = max(0, (int)($plc[3]  ?? 0));  // D4003 - Produkt
            $ibc_ok         = max(0, (int)($plc[4]  ?? 0));  // D4004 - IBC OK
            $ibc_ej_ok      = max(0, (int)($plc[5]  ?? 0));  // D4005 - IBC Ej OK
            $omtvaatt       = max(0, (int)($plc[6]  ?? 0));  // D4006 - Omtvätt
            $runtime_plc    = max(0, (int)($plc[7]  ?? 0));  // D4007 - Runtime (min, excl rast)
            $rasttime       = max(0, (int)($plc[8]  ?? 0));  // D4008 - Rasttid (min)
            $lopnummer      = max(0, (int)($plc[9]  ?? 0));  // D4009 - Löpnummer (max)
            // D4010 = aktuellt löpnummer (ignoreras vid cykel)
            $driftstopptime = max(0, (int)($plc[11] ?? 0));  // D4011 - Driftstopptid (min)

            try {
                $this->validatePLCData('handleCycle', $ibc_ok, $ibc_ej_ok, $omtvaatt, $runtime_plc, $produkt);
                $modbusOk = true;
            } catch (\RuntimeException $e) {
                $this->log('handleCycle', "Validering MISSLYCKADES — sparar ändå med default-värden", ['error' => $e->getMessage()]);
            }
        } else {
            $this->log('handleCycle', "Modbus misslyckades — sparar cykel med null-värden (ibc räknas ändå)");
        }

        // Räkna ut ibc_count för dagen
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM tvattlinje_ibc WHERE DATE(datum) = CURDATE()");
        $stmt->execute();
        $dbcount = (int)$stmt->fetchColumn();

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

        $this->log('handleCycle', "IBC-nummer beräknat", [
            'ibc_count' => $ibc_count,
            'db_count'  => $dbcount,
            'shelly'    => $shellyCount,
        ]);

        // Duplikat-skydd: samma s_count idag = samma fysiska IBC, ignorera
        $dupStmt = $this->db->prepare("SELECT COUNT(*) FROM tvattlinje_ibc WHERE DATE(datum) = CURDATE() AND s_count = :s_count");
        $dupStmt->execute(['s_count' => $shellyCount]);
        if ((int)$dupStmt->fetchColumn() > 0) {
            $this->log('handleCycle', "Duplikat ignorerat — s_count redan registrerad idag", ['s_count' => $shellyCount]);
            $this->writeRegister('handleCycle/ACK', 4014, 0);
            return;
        }

        // Auto-stäng öppen rast om IBC registreras (linjen kör = rasten är slut)
        try {
            $lastRast = $this->db->query("SELECT rast_status FROM tvattlinje_rast ORDER BY datum DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($lastRast && (int)$lastRast['rast_status'] === 1) {
                $this->db->exec("INSERT INTO tvattlinje_rast (datum, rast_status) VALUES (NOW(), 0)");
                $this->log('handleCycle', "Auto-stängde öppen rast — IBC kom in trots aktiv rast");
            }
        } catch (\Exception $e) {
            $this->log('handleCycle', "Auto-rast-stängning misslyckades", ['error' => $e->getMessage()]);
        }

        // Auto-stäng öppet driftstopp om IBC registreras
        try {
            $lastStopp = $this->db->query("SELECT driftstopp_status FROM tvattlinje_driftstopp ORDER BY datum DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($lastStopp && (int)$lastStopp['driftstopp_status'] === 1) {
                $this->db->exec("INSERT INTO tvattlinje_driftstopp (datum, driftstopp_status) VALUES (NOW(), 0)");
                $this->log('handleCycle', "Auto-stängde öppet driftstopp — IBC kom in trots aktivt stopp");
            }
        } catch (\Exception $e) {
            $this->log('handleCycle', "Auto-driftstopp-stängning misslyckades", ['error' => $e->getMessage()]);
        }

        // Skiftraknare
        $stmt = $this->db->prepare("
            SELECT skiftraknare FROM tvattlinje_onoff
            WHERE skiftraknare IS NOT NULL ORDER BY datum DESC LIMIT 1
        ");
        $stmt->execute();
        $sr = $stmt->fetch(PDO::FETCH_ASSOC);
        $skiftraknare = ($sr && isset($sr['skiftraknare'])) ? (int)$sr['skiftraknare'] : 1;

        // Effektivitet — baserat på ibc_count-delta från skiftstart, inte ibc_ok (D-reg fryst)
        $effektivitet = null;
        if ($modbusOk && $runtime_plc > 0 && $ibc_count > 0) {
            $taktMal = 3.0;
            try {
                $sr2 = $this->db->query("SELECT value FROM tvattlinje_settings WHERE setting = 'takt_mal' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($sr2 && $sr2['value'] > 0) $taktMal = (float)$sr2['value'];
            } catch (\Exception $e) { /* ignorera */ }

            // Hämta ibc_count vid skiftstart (MIN för aktuellt skiftraknare)
            $ibc_count_at_start = 0;
            try {
                $stmtMin = $this->db->prepare('
                    SELECT MIN(ibc_count) as min_count
                    FROM tvattlinje_ibc
                    WHERE skiftraknare = :sk AND DATE(datum) = CURDATE()
                ');
                $stmtMin->execute(['sk' => $skiftraknare]);
                $minRow = $stmtMin->fetch(PDO::FETCH_ASSOC);
                if ($minRow && $minRow['min_count'] !== null) {
                    $ibc_count_at_start = (int)$minRow['min_count'];
                }
            } catch (\Exception $e) {
                $this->log('handleCycle', "Kunde inte hämta ibc_count_at_start", ['error' => $e->getMessage()]);
            }

            // Faktiska IBCer detta skift = current - start + 1 (inkluderar nuvarande rad)
            $actual_ibcs = max(1, $ibc_count - $ibc_count_at_start + 1);
            $effektivitet = round(($actual_ibcs * $taktMal) / max(1, $runtime_plc) * 100, 2);
        }

        // Spara
        $stmt = $this->db->prepare('
            INSERT INTO tvattlinje_ibc (
                s_count, ibc_count, skiftraknare,
                op1, op2, op3, produkt,
                ibc_ok, ibc_ej_ok, omtvaatt,
                runtime_plc, rasttime, lopnummer, driftstopptime, effektivitet
            ) VALUES (
                :s_count, :ibc_count, :skiftraknare,
                :op1, :op2, :op3, :produkt,
                :ibc_ok, :ibc_ej_ok, :omtvaatt,
                :runtime_plc, :rasttime, :lopnummer, :driftstopptime, :effektivitet
            )
        ');
        $stmt->execute([
            's_count'        => $shellyCount,
            'ibc_count'      => $ibc_count,
            'skiftraknare'   => $skiftraknare,
            'op1'            => $modbusOk ? $op1 : null,
            'op2'            => $modbusOk ? $op2 : null,
            'op3'            => $modbusOk ? $op3 : null,
            'produkt'        => $modbusOk ? $produkt : null,
            'ibc_ok'         => $modbusOk ? $ibc_ok : null,
            'ibc_ej_ok'      => $modbusOk ? $ibc_ej_ok : null,
            'omtvaatt'       => $modbusOk ? $omtvaatt : null,
            'runtime_plc'    => $modbusOk ? $runtime_plc : null,
            'rasttime'       => $modbusOk ? $rasttime : null,
            'lopnummer'      => $modbusOk ? $lopnummer : null,
            'driftstopptime' => $modbusOk ? $driftstopptime : null,
            'effektivitet'   => $effektivitet,
        ]);
        $this->log('handleCycle', "DB INSERT OK", [
            'ibc_count'      => $ibc_count,
            'ibc_ok'         => $ibc_ok,
            'runtime_plc'    => $runtime_plc,
            'driftstopptime' => $driftstopptime,
            'effektivitet'   => $effektivitet,
            'modbus_ok'      => $modbusOk,
        ]);

        // Kvittera D4014 = 0
        usleep(200000);
        $this->writeRegister('handleCycle/ACK', 4014, 0);
    }

    // =====================================================
    // handleRunning — triggas vid start/stopp-signal
    // =====================================================
    public function handleRunning(array $_data): void {
        if (!isset($_GET['high'], $_GET['low'])) {
            throw new InvalidArgumentException('Missing required fields high and low for handleRunning');
        }

        $high           = (int)$_GET['high'];
        $low            = (int)$_GET['low'];
        $running_param  = $_GET['running'] ?? '0';
        $is_running     = ($running_param === 'true' || $running_param === '1') ? 1 : 0;
        $runtime_today  = 0;

        $this->log('handleRunning', "Signal mottagen", [
            'high'    => $high,
            'low'     => $low,
            'running' => $is_running,
        ]);

        $stmt = $this->db->prepare('
            SELECT s_count_l, s_count_h, runtime_today, running, datum, CURRENT_TIMESTAMP as tid
            FROM tvattlinje_onoff WHERE DATE(datum) = CURDATE() ORDER BY datum DESC LIMIT 1
        ');
        $stmt->execute();
        $lastEntry = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare('
            SELECT COALESCE(MAX(runtime_today), 0) as current_runtime
            FROM tvattlinje_onoff WHERE DATE(datum) = CURDATE()
        ');
        $stmt->execute();
        $runtime_today = (float)$stmt->fetch(PDO::FETCH_ASSOC)['current_runtime'];

        if ($lastEntry) {
            $low_flank = ($low > (int)$lastEntry['s_count_l']);
            if ($low_flank && (int)$lastEntry['running'] === 1) {
                $t1  = new DateTime($lastEntry['datum']);
                $t2  = new DateTime($lastEntry['tid']);
                $iv  = $t1->diff($t2);
                $min = ($iv->days * 24 * 60) + ($iv->h * 60) + $iv->i + round($iv->s / 60, 2);
                $runtime_today += $min;
                $this->log('handleRunning', "Runtime uppdaterad", [
                    'period_min'   => $min,
                    'runtime_idag' => $runtime_today,
                ]);
            }
        }

        // Skiftraknare — räkna upp vid varje ON-händelse (is_running=1)
        $skiftraknare = null;
        if ($is_running === 1) {
            $stmtSk = $this->db->prepare('
                SELECT skiftraknare FROM tvattlinje_onoff
                WHERE skiftraknare IS NOT NULL ORDER BY datum DESC LIMIT 1
            ');
            $stmtSk->execute();
            $lastSk = $stmtSk->fetch(PDO::FETCH_ASSOC);
            if ($lastSk && isset($lastSk['skiftraknare'])) {
                $skiftraknare = (int)$lastSk['skiftraknare'] + 1;
            } else {
                $skiftraknare = 1;
            }
        }

        $stmt = $this->db->prepare('
            INSERT INTO tvattlinje_onoff (s_count_h, s_count_l, runtime_today, running, skiftraknare)
            VALUES (:s_count_h, :s_count_l, :runtime_today, :running, :skiftraknare)
        ');
        $stmt->execute([
            's_count_h'    => $high,
            's_count_l'    => $low,
            'runtime_today'=> $runtime_today,
            'running'      => $is_running,
            'skiftraknare' => $skiftraknare,
        ]);
        $this->log('handleRunning', "DB INSERT OK", ['running' => $is_running, 'runtime_today' => $runtime_today, 'skiftraknare' => $skiftraknare]);
    }

    // =====================================================
    // handleRast — triggas vid rast-status-ändring
    // =====================================================
    public function handleRast(array $_data): void {
        if (!isset($_GET['rast'])) {
            throw new InvalidArgumentException('Missing required field: rast');
        }

        $rast_status = (int)$_GET['rast'];
        $this->log('handleRast', "Signal mottagen", ['rast_status' => $rast_status]);

        $table     = null;
        $lastEntry = null;
        foreach (['tvattlinje_rast', 'tvattlinje_runtime'] as $candidate) {
            try {
                $stmt = $this->db->prepare("SELECT rast_status FROM {$candidate} ORDER BY datum DESC LIMIT 1");
                $stmt->execute();
                $lastEntry = $stmt->fetch(PDO::FETCH_ASSOC);
                $table     = $candidate;
                break;
            } catch (\Throwable $e) {
                $this->log('handleRast', "Tabell ej tillgänglig — provar nästa", [
                    'tabell' => $candidate,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        if ($table === null) {
            $this->log('handleRast', "KRITISKT: varken tvattlinje_rast eller tvattlinje_runtime finns");
            return;
        }

        $lastStatus = $lastEntry ? (int)$lastEntry['rast_status'] : -1;
        if ($lastStatus === $rast_status) {
            $this->log('handleRast', "Ingen förändring — hoppar över INSERT", [
                'status' => $rast_status,
            ]);
            return;
        }

        try {
            $stmt = $this->db->prepare("INSERT INTO {$table} (datum, rast_status) VALUES (NOW(), :rast_status)");
            $stmt->execute(['rast_status' => $rast_status]);
            $this->log('handleRast', "DB INSERT OK", [
                'tabell'      => $table,
                'rast_status' => $rast_status,
                'förra'       => $lastStatus,
            ]);
        } catch (\Throwable $e) {
            $this->log('handleRast', "DB INSERT MISSLYCKADES", [
                'tabell' => $table,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    // =====================================================
    // handleSkiftrapport — sparar skiftdata från PLC (D4000-D4011)
    // Triggas via D4015=1 (command register)
    // Tar emot redan lästa register från handleCommand för att undvika race-condition
    // där PLC nollställer D4000-D4011 direkt när D4015=1 skickas.
    // =====================================================
    public function handleSkiftrapport(array $preloadedPlc): void {
        $this->log('handleSkiftrapport', "Skiftrapport triggas");

        $op1 = $op2 = $op3 = $produkt = 0;
        $ibc_ok = $ibc_ej_ok = $omtvaatt = 0;
        $runtime_plc = $rasttime = $lopnummer_max = $aktuellt_lopnummer = $driftstopptime = 0;
        $modbusOk = false;

        // Använd förhandslästa register om tillgängliga (undviker race-condition)
        // annars försök läsa direkt (fallback)
        $plc = !empty($preloadedPlc) ? $preloadedPlc : $this->readRegisters('handleSkiftrapport/fallback', 4000, 12);
        $this->log('handleSkiftrapport', "Använder " . (!empty($preloadedPlc) ? "förhandslästa" : "direktlästa") . " register");

        if ($plc !== null) {
            $op1                = max(0, (int)($plc[0] ?? 0));  // D4000
            $op2                = max(0, (int)($plc[1] ?? 0));  // D4001
            $op3                = max(0, (int)($plc[2] ?? 0));  // D4002
            $produkt            = max(0, (int)($plc[3] ?? 0));  // D4003
            $ibc_ok             = max(0, (int)($plc[4] ?? 0));  // D4004
            $ibc_ej_ok          = max(0, (int)($plc[5] ?? 0));  // D4005
            $omtvaatt           = max(0, (int)($plc[6] ?? 0));  // D4006
            $runtime_plc        = max(0, (int)($plc[7] ?? 0));  // D4007
            $rasttime           = max(0, (int)($plc[8] ?? 0));  // D4008
            $lopnummer_max      = max(0, (int)($plc[9] ?? 0));  // D4009
            $aktuellt_lopnummer = max(0, (int)($plc[10] ?? 0)); // D4010
            $driftstopptime     = max(0, (int)($plc[11] ?? 0)); // D4011
            $modbusOk = true;
            $this->log('handleSkiftrapport', "PLC-data extraherad", [
                'op1' => $op1, 'op2' => $op2, 'op3' => $op3, 'produkt' => $produkt,
                'ibc_ok' => $ibc_ok, 'ibc_ej_ok' => $ibc_ej_ok, 'omtvaatt' => $omtvaatt,
                'runtime_plc' => $runtime_plc, 'rasttime' => $rasttime,
                'lopnummer_max' => $lopnummer_max, 'driftstopptime' => $driftstopptime,
            ]);
        } else {
            $this->log('handleSkiftrapport', "PLC-data saknas — skiftrapport sparas med null-värden");
        }

        // Skiftraknare
        $stmt = $this->db->prepare("
            SELECT skiftraknare FROM tvattlinje_onoff
            WHERE skiftraknare IS NOT NULL ORDER BY datum DESC LIMIT 1
        ");
        $stmt->execute();
        $sr           = $stmt->fetch(PDO::FETCH_ASSOC);
        $skiftraknare = ($sr && isset($sr['skiftraknare'])) ? (int)$sr['skiftraknare'] : 1;

        $totalt = $ibc_ok + $ibc_ej_ok + $omtvaatt;
        $datum  = date('Y-m-d');

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
            'datum'          => $datum,
            'antal_ok'       => $ibc_ok,
            'antal_ej_ok'    => $ibc_ej_ok,
            'omtvaatt'       => $omtvaatt,
            'totalt'         => $totalt,
            'op1'            => $op1 ?: null,
            'op2'            => $op2 ?: null,
            'op3'            => $op3 ?: null,
            'product_id'     => $produkt ?: null,
            'drifttid'       => $runtime_plc,
            'rasttime'       => $rasttime,
            'driftstopptime' => $driftstopptime,
            'lopnummer'      => $lopnummer_max ?: $aktuellt_lopnummer,
            'skiftraknare'   => $skiftraknare,
        ]);
        $this->log('handleSkiftrapport', "DB INSERT OK", [
            'datum'         => $datum,
            'ibc_ok'        => $ibc_ok,
            'ibc_ej_ok'     => $ibc_ej_ok,
            'omtvaatt'      => $omtvaatt,
            'runtime_plc'   => $runtime_plc,
            'rasttime'      => $rasttime,
            'driftstopptime'=> $driftstopptime,
            'lopnummer'     => $lopnummer_max ?: $aktuellt_lopnummer,
            'skiftraknare'  => $skiftraknare,
            'modbus_ok'     => $modbusOk,
        ]);

        // ACK hanteras av handleCommand efter att handleSkiftrapport returnerar
    }

    // =====================================================
    // handleCommand — läser D4015 och rotar till rätt handler
    // D4015=1: Skiftrapport, D4015=3: Driftstopp start, D4015=4: Driftstopp slut
    // =====================================================
    public function handleCommand(array $_data): void {
        $this->log('handleCommand', "Command webhook mottagen — läser D4000-D4015 i ett anrop");

        // Läs D4000-D4015 (16 register) i ett enda Modbus-anrop.
        // Detta undviker race-condition: PLCn kan nollställa D4000-D4011 direkt
        // när D4015=1 skickas — om vi läser i separata anrop kan data vara borta.
        $plc = $this->readRegisters('handleCommand', 4000, 16);
        if ($plc === null) {
            $this->log('handleCommand', "KRITISKT: kunde inte läsa D4000-D4015 — avbryter");
            return;
        }

        $command = (int)($plc[15] ?? 0); // D4015 = index 15
        $this->log('handleCommand', "D4015 läst", ['command' => $command]);

        switch ($command) {
            case 1:
                $this->log('handleCommand', "→ Skiftrapport");
                // Skicka förhandslästa D4000-D4011 (index 0-11) till handleSkiftrapport
                $this->handleSkiftrapport(array_slice($plc, 0, 12));
                break;

            case 3:
                $this->log('handleCommand', "→ Driftstopp START");
                try {
                    $this->db->exec("INSERT INTO tvattlinje_driftstopp (datum, driftstopp_status) VALUES (NOW(), 1)");
                    $this->log('handleCommand', "Driftstopp START sparad i DB");
                } catch (\Exception $e) {
                    $this->log('handleCommand', "DB INSERT driftstopp START MISSLYCKADES", ['error' => $e->getMessage()]);
                }
                break;

            case 4:
                $this->log('handleCommand', "→ Driftstopp SLUT");
                try {
                    $this->db->exec("INSERT INTO tvattlinje_driftstopp (datum, driftstopp_status) VALUES (NOW(), 0)");
                    $this->log('handleCommand', "Driftstopp SLUT sparad i DB");
                } catch (\Exception $e) {
                    $this->log('handleCommand', "DB INSERT driftstopp SLUT MISSLYCKADES", ['error' => $e->getMessage()]);
                }
                break;

            case 0:
                $this->log('handleCommand', "D4015=0 — inget kommando, nollar");
                break;

            default:
                $this->log('handleCommand', "Okänt kommando — nollar", ['command' => $command]);
                break;
        }

        // Nolla D4015 och D4014
        $this->writeRegister('handleCommand/ACK', 4015, 0);
        $this->writeRegister('handleCommand/ACK', 4014, 0);
    }
}
