<?php
/**
 * ProduktionsPrognosController.php
 *
 * Proxy-fil: all logik finns i classes/ProduktionsPrognosController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/ProduktionsPrognosController.php for full implementation med endpoints:
 *   - run=forecast       — aktuellt skift, IBC hittills, takt (IBC/h), prognos vid skiftslut
 *   - run=shift-history  — senaste 10 skiftens faktiska resultat for jämförelse
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/ProduktionsPrognosController.php';
