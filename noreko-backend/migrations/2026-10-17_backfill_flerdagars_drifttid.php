<?php
/**
 * N-bug #1 (BUGGAR-mauserdb.md) — generell backfill för ALLA historiska flerdagars=1
 * skiftrapporter, inte bara engångsfixen för 2026-06-05
 * (se 2026-06-05_backfill_tvattlinje_drifttid.sql).
 *
 * D4007 ("drifttid") ackumulerar över HELA wall-clock-spannet när ett skift sträcker sig
 * över flera dygn eller inte avslutas normalt → fysiskt omöjliga värden (100h+/skift).
 * Detta script ersätter drifttid/rasttime med VERKLIG delta sedan föregående rapport
 * (härlett från tvattlinje_ibc.runtime_plc/rasttime), och fyller
 * tvattlinje_skiftrapport_daglig med en daglig fördelning — samma metod som koden nu kör
 * automatiskt för framtida flerdagars-rapporter (se TvattLinje::reconcileFlerdagarsDrifttid).
 *
 * ANVÄNDNING (körs på servern där db_config.php pekar mot rätt databas — denna miljö har
 * ingen DB-åtkomst och har INTE kunnat testa/köra scriptet):
 *   php 2026-10-17_backfill_flerdagars_drifttid.php            # dry-run, skriver bara ut diff
 *   php 2026-10-17_backfill_flerdagars_drifttid.php --apply    # skriver faktiskt till DB
 *
 * Kör mot DEV FÖRST. Kör ALDRIG mot prod utan Oscars uttryckliga godkännande.
 */

$apply = in_array('--apply', $argv, true);

$cfg = require __DIR__ . '/../db_config.php';
$pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'] ?? $cfg['password'] ?? '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec("CREATE TABLE IF NOT EXISTS tvattlinje_skiftrapport_daglig (
    id               INT NOT NULL AUTO_INCREMENT,
    skiftrapport_id  INT NOT NULL,
    dag              DATE NOT NULL,
    antal_ok         INT NOT NULL DEFAULT 0,
    antal_ej_ok      INT NOT NULL DEFAULT 0,
    omtvaatt         INT NOT NULL DEFAULT 0,
    drifttid_min     INT NOT NULL DEFAULT 0,
    rast_min         INT NOT NULL DEFAULT 0,
    kalla            ENUM('plc_event','pro_rata') NOT NULL DEFAULT 'plc_event',
    PRIMARY KEY (id),
    UNIQUE KEY uq_rapport_dag (skiftrapport_id, dag),
    KEY idx_dag (dag),
    KEY idx_skiftrapport_id (skiftrapport_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$rows = $pdo->query("
    SELECT id, datum, created_at, drifttid, antal_ok, antal_ej_ok, omtvaatt
    FROM tvattlinje_skiftrapport
    WHERE flerdagars = 1
    ORDER BY id ASC
")->fetchAll();

if (empty($rows)) {
    echo "Inga flerdagars=1-rapporter hittades. Inget att göra.\n";
    exit(0);
}

echo "Hittade " . count($rows) . " flerdagars-rapport(er).\n";
echo $apply ? "LÄGE: --apply (skriver till DB)\n\n" : "LÄGE: dry-run (ingen skrivning — lägg till --apply för att verkställa)\n\n";

foreach ($rows as $row) {
    $id = (int)$row['id'];

    $prevStmt = $pdo->prepare("SELECT created_at FROM tvattlinje_skiftrapport WHERE id < :id ORDER BY id DESC LIMIT 1");
    $prevStmt->execute(['id' => $id]);
    $prevCreatedAt = $prevStmt->fetchColumn() ?: date('Y-m-d H:i:s', strtotime($row['created_at'] . ' -24 hours'));
    $rca = $row['created_at'];

    $b = $pdo->prepare("
        SELECT COALESCE(MAX(ibc_count), 0) AS ibc,
               COALESCE(MAX(COALESCE(runtime_plc, 0)), 0) AS rt,
               COALESCE(MAX(COALESCE(rasttime, 0)), 0) AS rast
        FROM tvattlinje_ibc WHERE datum <= :prev
    ");
    $b->execute(['prev' => $prevCreatedAt]);
    $base = $b->fetch() ?: ['ibc' => 0, 'rt' => 0, 'rast' => 0];

    $e = $pdo->prepare("
        SELECT COALESCE(MAX(COALESCE(runtime_plc, 0)), :brt) AS rt,
               COALESCE(MAX(COALESCE(rasttime, 0)), :brast) AS rast
        FROM tvattlinje_ibc WHERE datum > :prev AND datum <= :now
    ");
    $e->execute(['brt' => $base['rt'], 'brast' => $base['rast'], 'prev' => $prevCreatedAt, 'now' => $rca]);
    $end = $e->fetch() ?: ['rt' => $base['rt'], 'rast' => $base['rast']];

    $deltaRt   = max(0.0, (float)$end['rt']   - (float)$base['rt']);
    $deltaRast = max(0.0, (float)$end['rast'] - (float)$base['rast']);
    $newDt     = (int)min(600, round($deltaRt));
    $newRast   = (int)round($deltaRast);

    printf(
        "#%d  datum=%s  drifttid: %d min -> %d min  (rast -> %d min)  [fönster %s .. %s]\n",
        $id, $row['datum'], (int)$row['drifttid'], $newDt, $newRast, $prevCreatedAt, $rca
    );

    if (!$apply) continue;

    $pdo->prepare("UPDATE tvattlinje_skiftrapport SET drifttid = :dt, rasttime = :rt WHERE id = :id")
        ->execute(['dt' => $newDt, 'rt' => $newRast, 'id' => $id]);

    $daysStmt = $pdo->prepare("
        SELECT DATE(datum) AS dag,
               MAX(ibc_count) AS max_ibc,
               MAX(COALESCE(runtime_plc, 0)) AS max_rt,
               MAX(COALESCE(rasttime, 0)) AS max_rast
        FROM tvattlinje_ibc WHERE datum > :prev AND datum <= :now
        GROUP BY DATE(datum) ORDER BY dag ASC
    ");
    $daysStmt->execute(['prev' => $prevCreatedAt, 'now' => $rca]);
    $days = $daysStmt->fetchAll();
    if (empty($days)) continue;

    $prevIbc  = (float)$base['ibc'];
    $prevRt   = (float)$base['rt'];
    $prevRast = (float)$base['rast'];
    $dayDeltas = [];
    $totIbcDelta = 0.0;
    foreach ($days as $d) {
        $ibcDelta  = max(0.0, (float)$d['max_ibc'] - $prevIbc);
        $rtDelta   = max(0.0, (float)$d['max_rt']  - $prevRt);
        $rastDelta = max(0.0, (float)$d['max_rast'] - $prevRast);
        $dayDeltas[] = ['dag' => $d['dag'], 'ibc' => $ibcDelta, 'rt' => $rtDelta, 'rast' => $rastDelta];
        $totIbcDelta += $ibcDelta;
        $prevIbc  = (float)$d['max_ibc'];
        $prevRt   = (float)$d['max_rt'];
        $prevRast = (float)$d['max_rast'];
    }
    if ($totIbcDelta <= 0) continue;

    $ins = $pdo->prepare("
        INSERT INTO tvattlinje_skiftrapport_daglig
          (skiftrapport_id, dag, antal_ok, antal_ej_ok, omtvaatt, drifttid_min, rast_min, kalla)
        VALUES (:sid, :dag, :ok, :ejok, :omtv, :dt, :rt, 'plc_event')
        ON DUPLICATE KEY UPDATE
          antal_ok = VALUES(antal_ok), antal_ej_ok = VALUES(antal_ej_ok), omtvaatt = VALUES(omtvaatt),
          drifttid_min = VALUES(drifttid_min), rast_min = VALUES(rast_min), kalla = VALUES(kalla)
    ");
    foreach ($dayDeltas as $dd) {
        $share = $dd['ibc'] / $totIbcDelta;
        $ins->execute([
            'sid'  => $id,
            'dag'  => $dd['dag'],
            'ok'   => (int)round($row['antal_ok']    * $share),
            'ejok' => (int)round($row['antal_ej_ok'] * $share),
            'omtv' => (int)round($row['omtvaatt']    * $share),
            'dt'   => (int)round($dd['rt']),
            'rt'   => (int)round($dd['rast']),
        ]);
    }
}

echo "\nKlart.\n";
