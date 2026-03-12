<?php
/**
 * DrifttidsTimelineController.php
 *
 * Proxy-fil: all logik finns i classes/DrifttidsTimelineController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/DrifttidsTimelineController.php for full implementation med endpoints:
 *   - run=timeline-data  -> Tidssegment for en dag (körning/stopp/ej planerat)
 *   - run=summary        -> KPI:er: drifttid, stopptid, antal stopp, utnyttjandegrad
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/DrifttidsTimelineController.php';
