<?php
/**
 * KvalitetsTrendbrottController.php
 *
 * Proxy-fil: all logik finns i classes/KvalitetsTrendbrottController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/KvalitetsTrendbrottController.php for full implementation med endpoints:
 *   - run=overview       -> daglig kassationsgrad med rörligt medelvärde + avvikelser
 *   - run=alerts         -> lista alla trendbrott sorterade efter allvarlighetsgrad
 *   - run=daily-detail   -> drill-down för en specifik dag
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/KvalitetsTrendbrottController.php';
