<?php
/**
 * AlarmHistorikController.php
 *
 * Proxy-fil: all logik finns i classes/AlarmHistorikController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/AlarmHistorikController.php for full implementation med endpoints:
 *   - run=list     -> Filtrerad lista med alla larm
 *   - run=summary  -> KPI-sammanfattning
 *   - run=timeline -> Tidslinje for Chart.js
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/AlarmHistorikController.php';
