<?php
/**
 * StopptidsanalysController.php
 *
 * Proxy-fil: all logik finns i classes/StopptidsanalysController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/StopptidsanalysController.php for full implementation med endpoints:
 *   - run=overview      -> KPI:er: total stopptid idag, flaskhals-maskin, antal stopp, snitt
 *   - run=per-maskin    -> horisontellt stapeldiagram per maskin
 *   - run=trend         -> linjediagram stopptid per dag per maskin
 *   - run=fordelning    -> doughnut-data: andel per maskin
 *   - run=detaljtabell  -> detaljerad stopptids-tabell
 *   - run=maskiner      -> lista alla maskiner
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/StopptidsanalysController.php';
