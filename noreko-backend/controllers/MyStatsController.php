<?php
/**
 * MyStatsController.php (proxy)
 *
 * Proxy-fil: all logik finns i classes/MyStatsController.php
 * Denna fil existerar i controllers/ enligt konventionen men delegerar till classes/.
 *
 * Ruttning sker via api.php -> classes/ (autoloader).
 * Se classes/MyStatsController.php för full implementation med endpoints:
 *   - run=my-stats&period=7|30|90  -> Personlig statistik (IBC, IBC/h, kvalitet, ranking)
 *   - run=my-trend&period=30|90    -> Daglig trend för operatören vs teamsnitt
 *   - run=my-achievements          -> Milstolpar (karriär-total, bästa dag, streak, förbättring)
 */

// Delegera till huvudcontrollern i classes/
require_once __DIR__ . '/../classes/MyStatsController.php';
